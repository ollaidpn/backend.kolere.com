<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationsService
{
    protected string $apiUrl;
    protected string $publicKey;
    protected string $secretKey;

    public function __construct(?string $publicKey = null, ?string $secretKey = null)
    {
        $this->apiUrl    = config('services.diotko_sms.api_url', env('DIOTKO_SMS_API_URL', 'https://artefacts.diotko.com/api/v1/send'));
        $this->publicKey = $publicKey ?: config('services.diotko_sms.public_key', env('DIOTKO_SMS_PUBLIC_KEY', ''));
        $this->secretKey = $secretKey ?: config('services.diotko_sms.secret_key', env('DIOTKO_SMS_SECRET_KEY', ''));
    }


    /**
     * Obtenir le solde SMS auprès de Diotko API
     */
    public function getBalance(): array
    {
        try {
            if (empty($this->publicKey) || empty($this->secretKey)) {
                return ['success' => false, 'message' => 'Clés Diotko SMS non configurées'];
            }

            // Endpoint officiel Diotko SMS pour la consultation de solde : /api/v1/balance
            $balanceUrl = str_replace('/send', '/balance', $this->apiUrl);
            $response = Http::timeout(10)
                ->withHeaders([
                    'X-Public-Key' => $this->publicKey,
                    'X-Secret-Key' => $this->secretKey,
                    'Accept'       => 'application/json',
                ])
                ->get($balanceUrl);

            $responseData = $response->json();
            if ($response->successful() && isset($responseData['success']) && $responseData['success'] === true) {
                // La doc Diotko (balance.md) spécifie la propriété "sms_balance" au premier niveau du JSON
                $balance = $responseData['sms_balance'] ?? $responseData['data']['sms_balance'] ?? $responseData['balance'] ?? 0;
                return [
                    'success'     => true,
                    'sms_balance' => $balance,
                    'balance'     => $balance,
                    'data'        => $responseData,
                ];
            }

            return [
                'success' => false,
                'message' => $responseData['error'] ?? $responseData['message'] ?? 'Erreur lors de la récupération du solde',
                'balance' => null,
            ];
        } catch (\Exception $e) {
            Log::error('NotificationsService: Diotko balance check failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Erreur technique lors de la vérification du solde SMS'];
        }
    }

    /**
     * Liste des packages SMS disponibles (`GET /api/v1/packages`)
     */
    public function listPackages(): array
    {
        try {
            if (empty($this->publicKey) || empty($this->secretKey)) {
                return ['success' => false, 'message' => 'Clés Diotko SMS non configurées'];
            }

            $packagesUrl = str_replace('/send', '/packages', $this->apiUrl);
            $response = Http::timeout(10)
                ->withHeaders([
                    'X-Public-Key' => $this->publicKey,
                    'X-Secret-Key' => $this->secretKey,
                    'Accept'       => 'application/json',
                ])
                ->get($packagesUrl);

            $data = $response->json() ?? [];
            if ($response->successful()) {
                $packages = $data['packages'] ?? $data['data']['packages'] ?? $data['data'] ?? [];
                $providers = $data['payment_providers'] ?? $data['data']['payment_providers'] ?? [];

                return [
                    'success' => true,
                    'packages' => $packages,
                    'payment_providers' => $providers,
                    'raw' => $data,
                ];
            }

            return ['success' => false, 'message' => $data['message'] ?? 'Erreur lors de la récupération des packages'];
        } catch (\Exception $e) {
            Log::error('NotificationsService: Diotko listPackages failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Erreur technique lors de la récupération des packages'];
        }
    }


    /**
     * Acheter un package SMS (`POST /api/v1/packages/buy`)
     */
    public function buyPackage(int $packageId): array
    {
        try {
            if (empty($this->publicKey) || empty($this->secretKey)) {
                return ['success' => false, 'message' => 'Clés Diotko SMS non configurées'];
            }

            $buyUrl = str_replace('/send', '/packages/buy', $this->apiUrl);
            $response = Http::timeout(15)
                ->withHeaders([
                    'X-Public-Key' => $this->publicKey,
                    'X-Secret-Key' => $this->secretKey,
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post($buyUrl, ['package_id' => $packageId]);

            return $response->json() ?? ['success' => false];
        } catch (\Exception $e) {
            Log::error('NotificationsService: Diotko buyPackage failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Erreur technique lors de l\'achat du package'];
        }
    }

    /**
     * Historique des achats de packages SMS (`GET /api/v1/packages/purchases`)
     */
    public function listPurchases(): array
    {
        try {
            if (empty($this->publicKey) || empty($this->secretKey)) {
                return ['success' => false, 'message' => 'Clés Diotko SMS non configurées'];
            }

            $purchasesUrl = str_replace('/send', '/packages/purchases', $this->apiUrl);
            $response = Http::timeout(10)
                ->withHeaders([
                    'X-Public-Key' => $this->publicKey,
                    'X-Secret-Key' => $this->secretKey,
                    'Accept'       => 'application/json',
                ])
                ->get($purchasesUrl);

            return $response->json() ?? ['success' => false];
        } catch (\Exception $e) {
            Log::error('NotificationsService: Diotko listPurchases failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Erreur technique lors de la récupération de l\'historique'];
        }
    }



    /**
     * Check if a phone number is a Senegalese number (+221)
     */

    public static function isSenegalNumber(string $phone): bool
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        if (str_starts_with($phone, '+221')) return true;
        if (str_starts_with($phone, '221') && strlen($phone) >= 12) return true;
        if (!str_starts_with($phone, '+') && strlen($phone) === 9) return true;

        return false;
    }

    /**
     * Send SMS immediately via Diotko API (/api/v1/send)
     */
    public function sendSmsNow(array $recipients, string $message): array
    {
        try {
            $normalizedMessage = $this->normalizeSms($message);

            $formattedRecipients = array_map(function ($phone) {
                return $this->formatPhoneNumber($phone);
            }, $recipients);

            $formattedRecipients = array_filter($formattedRecipients, function ($phone) {
                return self::isSenegalNumber($phone);
            });

            if (empty($formattedRecipients)) {
                return ['success' => false, 'message' => 'Aucun numéro sénégalais valide'];
            }

            if (empty($this->publicKey) || empty($this->secretKey)) {
                Log::error('NotificationsService: DIOTKO_SMS_PUBLIC_KEY ou DIOTKO_SMS_SECRET_KEY manquant dans .env');
                return ['success' => false, 'message' => 'Clés API Diotko SMS non configurées'];
            }

            // 1. Vérification préalable du solde SMS
            $balanceCheck = $this->getBalance();
            if (!$balanceCheck['success']) {
                Log::warning('NotificationsService: Échec de la vérification du solde SMS', ['result' => $balanceCheck]);
                return ['success' => false, 'message' => 'Impossible de vérifier le solde SMS. Envoi annulé.'];
            }

            $currentBalance = (int) ($balanceCheck['sms_balance'] ?? 0);
            $requiredSms = count($formattedRecipients);

            if ($currentBalance < $requiredSms) {
                Log::warning('NotificationsService: Solde SMS insuffisant pour procéder à l\'envoi', [
                    'solde_actuel' => $currentBalance,
                    'sms_requis'  => $requiredSms,
                ]);
                return [
                    'success' => false,
                    'message' => "Solde SMS insuffisant ({$currentBalance} SMS disponible(s), {$requiredSms} requis). Veuillez recharger votre compte SMS.",
                    'insufficient_balance' => true,
                    'current_balance' => $currentBalance,
                ];
            }

            $recipientsValues = array_values($formattedRecipients);


            $payload = [
                'to'      => count($recipientsValues) === 1 ? $recipientsValues[0] : $recipientsValues,
                'message' => $normalizedMessage,
            ];

            $response = Http::timeout(30)
                ->withHeaders([
                    'X-Public-Key' => $this->publicKey,
                    'X-Secret-Key' => $this->secretKey,
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post($this->apiUrl, $payload);

            $responseData = $response->json();
            $statusCode   = $response->status();

            Log::info('NotificationsService: Diotko SMS API response', [
                'status_code'    => $statusCode,
                'recipients'     => $recipientsValues,
                'message_length' => strlen($normalizedMessage),
                'response'       => $responseData,
            ]);

            if ($response->successful() && isset($responseData['success']) && $responseData['success'] === true) {
                return [
                    'success' => true,
                    'message' => $responseData['message'] ?? 'SMS envoyé avec succès via Diotko',
                    'data'    => $responseData,
                ];
            }

            $errorMsg = $responseData['message'] ?? $responseData['error'] ?? 'Erreur lors de l\'envoi via Diotko SMS';
            Log::error('NotificationsService: Diotko SMS API returned error', [
                'error_msg'   => $errorMsg,
                'status_code' => $statusCode,
                'response'    => $responseData,
            ]);

            return [
                'success'       => false,
                'message'       => $errorMsg,
                'status_code'   => $statusCode,
                'full_response' => $responseData,
            ];
        } catch (\Exception $e) {
            Log::error('NotificationsService: Diotko SMS failed', [
                'error'      => $e->getMessage(),
                'recipients' => $recipients,
            ]);

            return [
                'success' => false,
                'message' => 'Erreur technique lors de l\'envoi du SMS via Diotko',
                'errors'  => [$e->getMessage()],
            ];
        }
    }

    protected function normalizeSms(string $message): string
    {
        $accents = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'ç' => 'c',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ñ' => 'n',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ý' => 'y', 'ÿ' => 'y',
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
            'Ç' => 'C',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ñ' => 'N',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ý' => 'Y',
            'œ' => 'oe', 'Œ' => 'OE',
            'æ' => 'ae', 'Æ' => 'AE',
            '«' => '"', '»' => '"',
            '–' => '-', '—' => '-',
            '…' => '...',
        ];

        return strtr($message, $accents);
    }

    protected function formatPhoneNumber(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        if (str_starts_with($phone, '0')) {
            $phone = '+221' . substr($phone, 1);
        }

        if (!str_starts_with($phone, '+')) {
            if (strlen($phone) === 9) {
                $phone = '+221' . $phone;
            } else {
                $phone = '+' . $phone;
            }
        }

        return $phone;
    }
}
