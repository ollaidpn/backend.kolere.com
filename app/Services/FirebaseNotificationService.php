<?php

namespace App\Services;

use App\Models\FirebaseToken;
use App\Models\User;
use App\Models\Manager;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\Messaging\InvalidMessage;
use Illuminate\Support\Facades\Log;

class FirebaseNotificationService
{
    protected $messaging;
    protected bool $ready = false;
    protected ?string $credentialsPathUsed = null;

    public function __construct()
    {
        $resolvedPath = storage_path('app/firebase-credentials.json');

        if (file_exists($resolvedPath)) {
            try {
                $factory = (new Factory)->withServiceAccount($resolvedPath);
                $this->messaging = $factory->createMessaging();
                $this->ready = true;
                $this->credentialsPathUsed = $resolvedPath;
                Log::info('[FCM] ✅ Service initialisé avec credentials', [
                    'credentials_path' => $resolvedPath,
                ]);
            } catch (\Throwable $e) {
                Log::error('[FCM] ❌ Erreur chargement credentials Firebase: ' . $e->getMessage());
            }
        } else {
            Log::warning('[FCM] ⚠️ Firebase credentials introuvables ou invalides, service non prêt', [
                'checked_path' => $resolvedPath,
            ]);
        }
    }

    /**
     * Envoyer une notification à tous les appareils d'un utilisateur / manager.
     */
    public function sendToTarget($target, string $title, string $body, array $data = []): array
    {
        if (!$this->ready) {
            Log::warning('[FCM] ❌ Service non prêt, notification ignorée.', [
                'title' => $title,
            ]);
            return ['sent' => 0, 'failed' => 0, 'skipped' => true, 'reason' => 'no_credentials'];
        }

        $tokens = FirebaseToken::getActiveTokensForTarget($target);

        if (empty($tokens)) {
            Log::info('[FCM] ⏭️ Aucun token actif pour la cible', [
                'title' => $title, 'type' => $data['type'] ?? 'unknown',
            ]);
            return ['sent' => 0, 'failed' => 0, 'no_tokens' => true];
        }

        Log::info('[FCM] 📤 Envoi notification', [
            'title' => $title,
            'type' => $data['type'] ?? 'unknown',
            'tokens_count' => count($tokens),
        ]);

        $invalidTokens = [];
        $failed = 0;
        $sent = 0;

        foreach ($tokens as $token) {
            $result = $this->sendToToken($token, $title, $body, $data);
            if ($result['sent']) $sent++;
            else $failed++;
            if ($result['invalid']) $invalidTokens[] = $token;
        }

        if (!empty($invalidTokens)) {
            FirebaseToken::removeInvalidTokens($invalidTokens);
            Log::info('[FCM] 🧹 Supprimé ' . count($invalidTokens) . ' token(s) invalide(s)');
        }

        Log::info('[FCM] ✅ Résultat envoi', [
            'sent' => $sent, 'failed' => $failed,
        ]);

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * Envoyer une notification à un token spécifique avec AndroidConfig + ApnsConfig.
     */
    protected function sendToToken(string $token, string $title, string $body, array $data = [], int $badgeCount = 1): array
    {
        $normalizedData = $this->normalizeDataPayload($data);

        try {
            $message = CloudMessage::withTarget('token', $token)
                ->withNotification(Notification::create($title, $body))
                ->withData($normalizedData)
                ->withAndroidConfig($this->getAndroidConfig())
                ->withApnsConfig($this->getApnsConfig($title, $body, $badgeCount));

            $this->messaging->send($message);
            Log::info('[FCM] ✅ Envoyé à token ' . substr($token, 0, 12) . '...');
            return ['sent' => true, 'invalid' => false];
        } catch (NotFound | InvalidMessage $e) {
            Log::warning('[FCM] ❌ Token invalide: ' . substr($token, 0, 12) . '...', [
                'error' => $e->getMessage(),
            ]);
            return ['sent' => false, 'invalid' => true];
        } catch (\Throwable $e) {
            Log::error('[FCM] ❌ Erreur envoi push:', [
                'error' => $e->getMessage(),
                'token' => substr($token, 0, 12) . '...',
            ]);
            return ['sent' => false, 'invalid' => false];
        }
    }

    /**
     * Android configuration.
     */
    protected function getAndroidConfig(): AndroidConfig
    {
        return AndroidConfig::fromArray([
            'priority' => 'high',
            'notification' => [
                'sound' => 'default',
                'color' => '#8b5cf6',
                'default_vibrate_timings' => true,
                'default_light_settings' => true,
                'channel_id' => 'default_channel_id',
            ],
        ]);
    }

    /**
     * iOS/APNS configuration.
     */
    protected function getApnsConfig(string $title, string $body, int $badgeCount = 1): ApnsConfig
    {
        return ApnsConfig::fromArray([
            'headers' => [
                'apns-priority' => '10',
                'apns-push-type' => 'alert',
            ],
            'payload' => [
                'aps' => [
                    'alert' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'sound' => 'default',
                    'badge' => $badgeCount,
                    'thread-id' => 'kolere-notifications',
                    'interruption-level' => 'active',
                ],
            ],
        ]);
    }

    protected function normalizeDataPayload(array $data): array
    {
        $normalized = [];
        foreach ($data as $key => $value) {
            if (is_null($value)) {
                $normalized[(string) $key] = '';
                continue;
            }
            if (is_scalar($value)) {
                $normalized[(string) $key] = (string) $value;
                continue;
            }
            $normalized[(string) $key] = json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        return $normalized;
    }

    /**
     * Helper statique — envoi rapide à un compte target.
     */
    public static function notify($target, string $title, string $body, array $data = []): array
    {
        try {
            Log::info('[FCM] 🔔 notify() appelée', [
                'title' => $title,
                'type' => $data['type'] ?? 'unknown',
            ]);
            $instance = app(self::class);
            return $instance->sendToTarget($target, $title, $body, $data);
        } catch (\Throwable $e) {
            Log::error('[FCM] ❌ notify() erreur fatale', [
                'error' => $e->getMessage(),
            ]);
            return ['sent' => 0, 'failed' => 0, 'error' => $e->getMessage()];
        }
    }
}
