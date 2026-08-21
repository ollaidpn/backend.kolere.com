<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FaykoPaymentService
{
    protected string $baseUrl;
    protected string $publicKey;
    protected string $secretKey;
    protected ?string $webhookSecret;

    public function __construct(?string $publicKey = null, ?string $secretKey = null, ?string $webhookSecret = null)
    {
        $this->baseUrl = $this->normalizeGatewayBaseUrl(
            config('services.fayko.api_url', env('FAYKO_API_URL', 'https://artefacts.fayko.sn/api/v2/gateway'))
        );

        $this->publicKey = $publicKey ?: config('services.fayko.public_key', env('FAYKO_PUBLIC_KEY', ''));
        $this->secretKey = $secretKey ?: config('services.fayko.secret_key', env('FAYKO_SECRET_KEY', ''));
        $this->webhookSecret = $webhookSecret ?: config('services.fayko.webhook_key', env('FAYKO_WEBHOOK_KEY', ''));
    }

    /**
     * Force l'usage de la base v2 du gateway, même si l'env fournit une variante
     * du style /api/v1 ou une racine sans suffixe.
     */
    protected function normalizeGatewayBaseUrl(string $baseUrl): string
    {
        $baseUrl = rtrim(trim($baseUrl), '/');

        if ($baseUrl === '') {
            return 'https://artefacts.fayko.sn/api/v2/gateway';
        }

        $baseUrl = preg_replace('#/api/v\d+/gateway$#', '', $baseUrl) ?? $baseUrl;
        $baseUrl = preg_replace('#/api/v\d+$#', '', $baseUrl) ?? $baseUrl;
        $baseUrl = preg_replace('#/gateway$#', '', $baseUrl) ?? $baseUrl;

        return rtrim($baseUrl, '/') . '/api/v2/gateway';
    }

    /**
     * Map les slugs vers les identifiants Fayko v2 (wave_sn, orange_money_sn)
     */
    protected function mapProviderSlug(string $paidBy): string
    {
        $clean = strtolower(trim($paidBy));
        if (in_array($clean, ['wave', 'wave_senegal', 'wave_sn'], true)) {
            return 'wave_sn';
        }
        if (in_array($clean, ['orange_money', 'orange_money_senegal', 'orange_money_sn', 'om'], true)) {
            return 'orange_money_sn';
        }
        return $clean;
    }

    /**
     * Effectue une requête HTTP authentifiée vers l'API Fayko v2 Gateway
     */
    protected function request(string $method, string $endpoint, array $data = [])
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');

        $headers = [
            'public-key' => $this->publicKey,
            'secret-key' => $this->secretKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        Log::info('[FaykoPaymentService] Request', [
            'method' => $method,
            'url' => $url,
            'data' => $data,
        ]);

        $response = Http::withHeaders($headers)
            ->timeout(30)
            ->send($method, $url, [
                'json' => $data,
            ]);

        if ($response->failed()) {
            Log::error('[FaykoPaymentService] API Error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException($response->json('message') ?? $response->body() ?: 'Erreur lors de la communication avec Fayko API v2.');
        }

        return $response->json();
    }

    /**
     * 1. Obtenir le solde de la boutique / credential (/api/v2/gateway/balance)
     */
    public function getBalance(): array
    {
        return $this->request('POST', '/balance');
    }

    /**
     * 2. Initier un checkout / paiement client (/api/v2/gateway/checkouts/make)
     */
    public function createCheckout(array $payload): array
    {
        $paidBy = $this->mapProviderSlug($payload['paid_by'] ?? 'wave_sn');

        $body = [
            'paid_by' => $paidBy,
            'amount' => (int) $payload['amount'],
            'qty' => (int) ($payload['qty'] ?? 1),
            'client_name' => $payload['client_name'],
            'name' => $payload['name'] ?? 'Commande boutique',
            'description' => $payload['description'] ?? 'Paiement de commande',
            'success_url' => $payload['success_url'] ?? config('app.url'),
            'error_url' => $payload['error_url'] ?? config('app.url'),
            'ccphone' => $payload['ccphone'] ?? '+221',
            'phone' => $payload['phone'] ?? '',
            'email' => $payload['email'] ?? null,
            'webhook_secret' => $this->webhookSecret,
            'extra_data' => is_array($payload['extra_data'] ?? null)
                ? json_encode($payload['extra_data'], JSON_UNESCAPED_UNICODE)
                : ($payload['extra_data'] ?? null),
        ];

        $res = $this->request('POST', '/checkouts/make', $body);
        $data = $res['data'] ?? [];

        return [
            'payment_link' => $data['payment_url'] ?? $data['payment_link'] ?? null,
            'payment_qrcode_base64' => $data['payment_qrcode_base64_image'] ?? $data['payment_qrcode_base64'] ?? null,
            'when_expires' => $data['when_expires'] ?? null,
            'gateway_reference' => $data['reference'] ?? null,
            'status' => $data['status'] ?? 'pending',
            'raw' => $res,
        ];
    }

    /**
     * 3. Retrouver un checkout par référence (/api/v2/gateway/checkouts/find/{reference})
     */
    public function findCheckout(string $reference): array
    {
        return $this->request('GET', "/checkouts/find/{$reference}");
    }

    /**
     * 4. Payout - Étape 1 : Initier et récupérer le solde & référence (/api/v2/gateway/payouts/request)
     */
    public function initPayout(): array
    {
        $res = $this->request('POST', '/payouts/request', []);
        $data = $res['data'] ?? [];

        return [
            'success' => $res['success'] ?? true,
            'message' => $res['message'] ?? null,
            'data' => array_merge($data, [
                'payout_reference' => $data['payout_reference'] ?? $data['request_reference'] ?? $data['reference'] ?? null,
            ]),
            'raw' => $res,
        ];
    }

    /**
     * 5. Payout - Étape 2 : Confirmer et exécuter le virement (/api/v2/gateway/payouts/request)
     */
    public function confirmPayout(array $payload): array
    {
        $body = [
            'reference' => $payload['reference'],
            'provider' => $this->mapProviderSlug($payload['provider']),
            'amount' => (int) $payload['amount'],
            'phone' => $payload['phone'],
            'ccphone' => $payload['ccphone'] ?? '+221',
            'name' => $payload['name'],
            'email' => $payload['email'] ?? null,
            'note' => $payload['note'] ?? 'Demande de retrait',
        ];

        $res = $this->request('POST', '/payouts/request', $body);
        $data = $res['data'] ?? [];

        return [
            'success' => $res['success'] ?? true,
            'message' => $res['message'] ?? null,
            'data' => array_merge($data, [
                'reference' => $data['reference'] ?? $body['reference'],
                'request_reference' => $data['request_reference'] ?? $body['reference'],
                'payout_reference' => $data['payout_reference'] ?? null,
            ]),
            'raw' => $res,
        ];
    }

    /**
     * 6. Lister les retraits (/api/v2/gateway/payouts/list)
     */
    public function listPayouts(): array
    {
        return $this->request('POST', '/payouts/list', []);
    }

    /**
     * 7. Annuler un retrait en attente (/api/v2/gateway/payouts/cancel)
     */
    public function cancelPayout(string $reference): array
    {
        return $this->request('POST', '/payouts/cancel', [
            'reference' => $reference,
        ]);
    }

    /**
     * 8. Lister les checkouts (/api/v2/gateway/checkouts/list)
     */
    public function listCheckouts(): array
    {
        return $this->request('POST', '/checkouts/list', []);
    }


    /**
     * 7. Récupérer la liste dynamique des providers pour checkout (/api/v2/gateway/checkouts/providers)
     */
    public function getCheckoutProviders(): array
    {
        $url = rtrim($this->baseUrl, '/') . '/checkouts/providers';
        $res = Http::acceptJson()->timeout(15)->get($url);
        return $res->json('data.providers') ?? [];
    }

    /**
     * 8. Récupérer la liste dynamique des providers pour payout (/api/v2/gateway/payouts/providers)
     */
    public function getPayoutProviders(): array
    {
        $url = rtrim($this->baseUrl, '/') . '/payouts/providers';
        $res = Http::acceptJson()->timeout(15)->get($url);
        return $res->json('data.providers') ?? [];
    }
}
