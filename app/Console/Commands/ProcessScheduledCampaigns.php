<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\Notification;
use App\Services\FirebaseNotificationService;
use App\Services\NotificationsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessScheduledCampaigns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'campaigns:process-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Traite et envoie les campagnes de notifications programmées ou en attente d\'envoi';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Log::info('[Cron-Campaigns] 🔄 Vérification des campagnes à envoyer...');

        // Récupérer toutes les campagnes avec statut 'Programmé' dont la date est échue ou nulle
        $campaigns = Campaign::where('status', 'Programmé')
            ->where(function ($query) {
                $query->whereNull('scheduled_at')
                    ->orWhere('scheduled_at', '<=', now());
            })
            ->get();

        if ($campaigns->isEmpty()) {
            Log::info('[Cron-Campaigns] ⏭️ Aucune campagne à traiter.');
            return 0;
        }

        Log::info("[Cron-Campaigns] 🚀 {$campaigns->count()} campagne(s) trouvée(s) pour traitement.");

        foreach ($campaigns as $campaign) {
            try {
                Log::info("[Cron-Campaigns] 📤 Traitement campagne #{$campaign->id} ({$campaign->type}) - {$campaign->title}");

                $recipients = $campaign->send_to ?? [];
                if (!is_array($recipients)) {
                    $recipients = json_decode($recipients, true) ?? [];
                }

                // 1. Traitement PUSH (App)
                if ($campaign->type === 'app') {
                    foreach ($recipients as $recipient) {
                        if (isset($recipient['user_id']) && $recipient['user_id']) {
                            Notification::create([
                                'entity_id' => $campaign->entity_id,
                                'user_id'   => $recipient['user_id'],
                                'title'     => $campaign->title,
                                'message'   => $campaign->message,
                                'is_read'   => false,
                            ]);

                            FirebaseNotificationService::notify(
                                $recipient['user_id'],
                                $campaign->title,
                                $campaign->message,
                                [
                                    'type'        => 'campaign',
                                    'campaign_id' => $campaign->id,
                                ]
                            );
                        }
                    }
                }

                // 2. Traitement SMS
                if ($campaign->type === 'sms') {
                    $entity = \App\Models\Entity::find($campaign->entity_id);
                    $pubKey = $entity?->diotko_public_key ?: env('DIOTKO_SMS_PUBLIC_KEY');
                    $secKey = $entity?->diotko_secret_key ?: env('DIOTKO_SMS_SECRET_KEY');

                    $smsService = new NotificationsService($pubKey, $secKey);
                    $phones = [];
                    foreach ($recipients as $recipient) {
                        if (!empty($recipient['phone'])) {
                            $phones[] = ($recipient['ccphone'] ?? '') . $recipient['phone'];
                        }
                    }
                    if (!empty($phones)) {
                        $smsService->sendSmsNow($phones, $campaign->message);
                    }
                }

                // 3. Traitement E-MAIL
                if ($campaign->type === 'email') {
                    $emails = [];
                    foreach ($recipients as $recipient) {
                        if (!empty($recipient['email'])) {
                            $emails[] = $recipient['email'];
                        }
                    }

                    if (!empty($emails)) {
                        foreach ($emails as $email) {
                            try {
                                \Illuminate\Support\Facades\Mail::raw($campaign->message, function ($msg) use ($email, $campaign) {
                                    $msg->to($email)
                                        ->subject($campaign->title);
                                });
                            } catch (\Throwable $mErr) {
                                Log::error("[Cron-Campaigns] ❌ Échec envoi email à {$email}: " . $mErr->getMessage());
                            }
                        }
                    }
                }

                // Marquer la campagne comme envoyée
                $campaign->update([
                    'status' => 'Envoyé',
                ]);

                Log::info("[Cron-Campaigns] ✅ Campagne #{$campaign->id} envoyée avec succès.");
            } catch (\Throwable $e) {
                Log::error("[Cron-Campaigns] ❌ Erreur campagne #{$campaign->id}: " . $e->getMessage());
            }
        }

        return 0;
    }
}
