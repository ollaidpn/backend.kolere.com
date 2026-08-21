<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoDaddyDomainService
{
    private string $apiKey;
    private string $apiSecret;
    private bool   $testMode;
    private float  $usdToXofRate;
    private float  $marginPercent;

    private const API_PROD = 'https://api.godaddy.com/v1';
    private const API_OTE  = 'https://api.ote-godaddy.com/v1';

    public const SUPPORTED_EXTENSIONS = [
        '.com', '.net', '.shop', '.co', '.store', '.org', '.io', '.online', '.info',
    ];

    private const DEFAULT_PRICES_FCFA = [
        '.com'    =>  9000,
        '.net'    => 10000,
        '.shop'   => 18000,
        '.co'     => 15000,
        '.store'  => 20000,
        '.org'    => 10000,
        '.io'     => 32000,
        '.online' =>  8000,
        '.info'   =>  8000,
    ];

    public function __construct()
    {
        $this->apiKey        = env('GD_API_KEY', '');
        $this->apiSecret     = env('GD_API_SECRET', '');
        $apiMode             = strtolower(trim(env('GD_API_MODE', 'production')));
        $secretMode          = env('GD_API_SECRET_MODE', null);
        if ($secretMode !== null) {
            $this->testMode = filter_var($secretMode, FILTER_VALIDATE_BOOLEAN);
        } else {
            $this->testMode = in_array($apiMode, ['ote', 'test', 'sandbox']);
        }
        $rawRate             = str_replace(',', '.', env('GD_USD_TO_XOF_RATE', '600'));
        $this->usdToXofRate  = (float) $rawRate;
        $this->marginPercent = (float) env('GD_MARGIN_PERCENT', 0);
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->apiSecret);
    }

    public function getSupportedExtensions(): array
    {
        return self::SUPPORTED_EXTENSIONS;
    }

    private function getBasePrice(string $ext): int
    {
        $envKey  = 'GD_PRICE_' . strtoupper(ltrim($ext, '.'));
        $default = self::DEFAULT_PRICES_FCFA[$ext] ?? 10000;
        return (int) env($envKey, $default);
    }

    public function searchDomains(string $query, array $extensions = []): array
    {
        $query = strtolower(trim($query));

        $supported  = self::SUPPORTED_EXTENSIONS;
        $extensions = !empty($extensions)
            ? array_values(array_intersect(
                array_map(fn($e) => '.' . ltrim($e, '.'), $extensions),
                $supported
              ))
            : $supported;

        if (empty($extensions)) {
            $extensions = $supported;
        }

        $results = [];

        // Si non configuré, retour simulation basée sur tarifs locaux
        if (!$this->isConfigured()) {
            foreach ($extensions as $ext) {
                $domain = $query . $ext;
                $basePrice  = $this->getBasePrice($ext);
                $finalPrice = (int) round($basePrice * (1 + $this->marginPercent / 100));
                $priceUsd   = $this->usdToXofRate > 0 ? round($finalPrice / $this->usdToXofRate, 2) : 0;
                $results[] = [
                    'domain'     => $domain,
                    'extension'  => $ext,
                    'available'  => true,
                    'price'      => $finalPrice,
                    'price_base' => $basePrice,
                    'price_usd'  => $priceUsd,
                    'currency'   => 'FCFA',
                    'period'     => 1,
                    'definitive' => true,
                ];
            }
            return $results;
        }

        foreach ($extensions as $ext) {
            $domain = $query . $ext;
            try {
                $results[] = $this->checkOneDomain($domain, $ext);
            } catch (\Exception $e) {
                Log::warning('GoDaddy check failed', ['domain' => $domain, 'error' => $e->getMessage()]);
                $basePrice  = $this->getBasePrice($ext);
                $finalPrice = (int) round($basePrice * (1 + $this->marginPercent / 100));
                $priceUsd   = $this->usdToXofRate > 0 ? round($finalPrice / $this->usdToXofRate, 2) : 0;
                $results[] = [
                    'domain'     => $domain,
                    'extension'  => $ext,
                    'available'  => false,
                    'price'      => $finalPrice,
                    'price_base' => $basePrice,
                    'price_usd'  => $priceUsd,
                    'currency'   => 'FCFA',
                    'period'     => 1,
                    'definitive' => false,
                ];
            }
        }

        return $results;
    }

    private function checkOneDomain(string $domain, string $ext): array
    {
        $baseUrl = $this->testMode ? self::API_OTE : self::API_PROD;

        $response = Http::withHeaders([
            'Authorization' => 'sso-key ' . $this->apiKey . ':' . $this->apiSecret,
            'Accept'        => 'application/json',
        ])->timeout(10)->get($baseUrl . '/domains/available', [
            'domain'    => $domain,
            'checkType' => 'FAST',
        ]);

        if (!$response->successful()) {
            throw new \Exception("GoDaddy API error {$response->status()}");
        }

        $data = $response->json();
        $available = (bool) ($data['available'] ?? false);

        $basePrice  = $this->getBasePrice($ext);
        $finalPrice = (int) round($basePrice * (1 + $this->marginPercent / 100));
        $priceUsd   = $this->usdToXofRate > 0 ? round($finalPrice / $this->usdToXofRate, 2) : 0;

        return [
            'domain'     => $domain,
            'extension'  => $ext,
            'available'  => $available,
            'price'      => $finalPrice,
            'price_base' => $basePrice,
            'price_usd'  => $priceUsd,
            'currency'   => 'FCFA',
            'period'     => (int) ($data['period'] ?? 1),
            'definitive' => (bool) ($data['definitive'] ?? false),
        ];
    }
}
