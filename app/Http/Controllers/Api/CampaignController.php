<?php

namespace App\Http\Controllers\Api;

use App\Models\Campaign;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Services\ShopMailFromResolver;
use Illuminate\Support\Carbon;

class CampaignController extends Controller
{
    private function entityId(Request $request): ?int
    {
        return $request->attributes->get('current_entity_id');
    }

    /**
     * Liste toutes les campagnes
     */
    public function index(Request $request): JsonResponse
    {
        $entityId = $this->entityId($request);
        
        $query = Campaign::query();
        if ($entityId) {
            $query->where('entity_id', $entityId);
        }
        
        if ($request->type) {
            $query->where('type', $request->type);
        }

        $campaigns = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'data' => $campaigns
        ]);
    }

    /**
     * Obtenir le solde SMS de la boutique (Diotko SMS)
     */
    public function getSmsBalance(Request $request): JsonResponse
    {
        $entityId = $this->entityId($request);
        $entity = $entityId ? \App\Models\Entity::find($entityId) : null;

        $pubKey = $entity?->diotko_public_key ?: env('DIOTKO_SMS_PUBLIC_KEY');
        $secKey = $entity?->diotko_secret_key ?: env('DIOTKO_SMS_SECRET_KEY');

        if (empty($pubKey) || empty($secKey)) {
            return response()->json([
                'connected' => false,
                'message'   => 'Clés API Diotko SMS non configurées',
                'balance'   => null,
            ]);
        }

        $service = new \App\Services\NotificationsService($pubKey, $secKey);
        $balanceRes = $service->getBalance();

        return response()->json([
            'connected' => true,
            'balance'   => $balanceRes['balance'] ?? null,
            'details'   => $balanceRes,
        ]);
    }

    /**
     * Liste des packages SMS Diotko
     */
    public function getSmsPackages(Request $request): JsonResponse
    {
        $entityId = $this->entityId($request);
        $entity = $entityId ? \App\Models\Entity::find($entityId) : null;
        $pubKey = $entity?->diotko_public_key ?: env('DIOTKO_SMS_PUBLIC_KEY');
        $secKey = $entity?->diotko_secret_key ?: env('DIOTKO_SMS_SECRET_KEY');

        if (empty($pubKey) || empty($secKey)) {
            return response()->json(['success' => false, 'message' => 'Clés Diotko SMS non configurées'], 400);
        }

        $service = new \App\Services\NotificationsService($pubKey, $secKey);
        return response()->json($service->listPackages());
    }

    /**
     * Achat d'un package SMS Diotko
     */
    public function buySmsPackage(Request $request): JsonResponse
    {
        $request->validate([
            'package_id' => 'required|integer',
        ]);

        $entityId = $this->entityId($request);
        $entity = $entityId ? \App\Models\Entity::find($entityId) : null;
        $pubKey = $entity?->diotko_public_key ?: env('DIOTKO_SMS_PUBLIC_KEY');
        $secKey = $entity?->diotko_secret_key ?: env('DIOTKO_SMS_SECRET_KEY');

        if (empty($pubKey) || empty($secKey)) {
            return response()->json(['success' => false, 'message' => 'Clés Diotko SMS non configurées'], 400);
        }

        $service = new \App\Services\NotificationsService($pubKey, $secKey);
        return response()->json($service->buyPackage((int) $request->package_id));
    }

    /**
     * Historique des achats SMS Diotko
     */
    public function getSmsPurchases(Request $request): JsonResponse
    {
        $entityId = $this->entityId($request);
        $entity = $entityId ? \App\Models\Entity::find($entityId) : null;
        $pubKey = $entity?->diotko_public_key ?: env('DIOTKO_SMS_PUBLIC_KEY');
        $secKey = $entity?->diotko_secret_key ?: env('DIOTKO_SMS_SECRET_KEY');

        if (empty($pubKey) || empty($secKey)) {
            return response()->json(['success' => false, 'message' => 'Clés Diotko SMS non configurées'], 400);
        }

        $service = new \App\Services\NotificationsService($pubKey, $secKey);
        return response()->json($service->listPurchases());
    }



    /**
     * Crée et envoie/programme une nouvelle campagne
     */
    public function store(Request $request): JsonResponse
    {
        $entityId = $this->entityId($request);
        if (!$entityId) {
            return response()->json(['message' => 'Entité introuvable'], 400);
        }

        $request->validate([
            'type' => 'required|in:email,sms,app',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'targetMode' => 'required|in:all,selected,manual',
            'selectedRecipientIds' => 'nullable|array',
            'manualRecipients' => 'nullable|string',
            'isScheduled' => 'boolean',
            'scheduledDate' => 'nullable|string',
            'scheduledTime' => 'nullable|string',
        ]);

        $type = $request->type;
        $recipients = [];

        // 1. Détermination de la liste des destinataires structurée en JSON
        if ($request->targetMode === 'all') {
            $users = User::whereHas('card', fn($q) => $q->where('entity_id', $entityId))->get();
            foreach ($users as $user) {
                $recipients[] = $this->formatRecipient($type, $user);
            }
        } elseif ($request->targetMode === 'selected') {
            $ids = $request->input('selectedRecipientIds', []);
            $users = User::whereIn('id', $ids)->get();
            foreach ($users as $user) {
                $recipients[] = $this->formatRecipient($type, $user);
            }
        } elseif ($request->targetMode === 'manual') {
            $manualInput = $request->input('manualRecipients', '');
            $items = array_filter(array_map('trim', explode(',', $manualInput)));
            foreach ($items as $item) {
                $recipients[] = $this->formatManualRecipient($type, $item);
            }
        }

        // 2. Programmation ou Envoi direct (dans les 2 cas, enregistrement en 'Programmé' pour traitement Cron rapide)
        $status = 'Programmé';
        $scheduledAt = now();

        if ($request->isScheduled && $request->scheduledDate && $request->scheduledTime) {
            try {
                $scheduledAt = Carbon::parse($request->scheduledDate . ' ' . $request->scheduledTime);
            } catch (\Exception $e) {
                return response()->json(['message' => 'Format de date de planification invalide.'], 422);
            }
        }

        // 3. Création de la Campagne
        $campaign = Campaign::create([
            'entity_id' => $entityId,
            'type' => $type,
            'title' => $request->title,
            'message' => $request->message,
            'send_to' => $recipients,
            'status' => $request->isScheduled ? 'Programmé' : 'Envoyé',
            'scheduled_at' => $scheduledAt,
        ]);

        // 4. Si l'envoi est immédiat (non programmé), traiter l'envoi tout de suite
        if (!$request->isScheduled) {
            $entity = \App\Models\Entity::find($entityId);

            // A. EMAIL IMMÉDIAT
            if ($type === 'email') {
                $fromAddress = ($entity && !empty($entity->email)) 
                    ? $entity->email 
                    : config('mail.from.address', env('MAIL_FROM_ADDRESS', 'noreply@kolere.sn'));
                $fromName = ($entity && !empty($entity->name)) 
                    ? $entity->name 
                    : config('mail.from.name', 'KOLERE');
                $emailSubject = $request->title;

                foreach ($recipients as $idx => $recipient) {
                    if (!empty($recipient['email'])) {
                        try {
                            \Illuminate\Support\Facades\Mail::raw($request->message, function ($msg) use ($recipient, $emailSubject, $fromAddress, $fromName) {
                                $msg->to($recipient['email'])
                                    ->subject($emailSubject)
                                    ->from($fromAddress, $fromName);
                            });
                            $recipients[$idx]['status'] = 'success';
                            $recipients[$idx]['sent_at'] = now()->toDateTimeString();
                        } catch (\Throwable $mErr) {
                            $recipients[$idx]['status'] = 'failed';
                            $recipients[$idx]['error'] = $mErr->getMessage();
                            \Illuminate\Support\Facades\Log::error("[CampaignController] ❌ Échec envoi email direct à {$recipient['email']}: " . $mErr->getMessage());
                        }
                    } else {
                        $recipients[$idx]['status'] = 'failed';
                        $recipients[$idx]['error'] = 'Adresse email manquante';
                    }
                }
            }


            // B. SMS IMMÉDIAT
            if ($type === 'sms') {
                $pubKey = $entity?->diotko_public_key ?: env('DIOTKO_SMS_PUBLIC_KEY');
                $secKey = $entity?->diotko_secret_key ?: env('DIOTKO_SMS_SECRET_KEY');

                $smsService = new \App\Services\NotificationsService($pubKey, $secKey);
                foreach ($recipients as $idx => $recipient) {
                    if (!empty($recipient['phone'])) {
                        $fullPhone = ($recipient['ccphone'] ?? '+221') . $recipient['phone'];
                        try {
                            $smsService->sendSmsNow([$fullPhone], $request->message);
                            $recipients[$idx]['status'] = 'success';
                            $recipients[$idx]['sent_at'] = now()->toDateTimeString();
                        } catch (\Throwable $sErr) {
                            $recipients[$idx]['status'] = 'failed';
                            $recipients[$idx]['error'] = $sErr->getMessage();
                            \Illuminate\Support\Facades\Log::error("[CampaignController] ❌ Échec SMS direct à {$fullPhone}: " . $sErr->getMessage());
                        }
                    } else {
                        $recipients[$idx]['status'] = 'failed';
                        $recipients[$idx]['error'] = 'Numéro de téléphone manquant';
                    }
                }
            }

            // C. PUSH IMMÉDIAT
            if ($type === 'app') {
                foreach ($recipients as $idx => $recipient) {
                    if (!empty($recipient['user_id'])) {
                        \App\Models\Notification::create([
                            'entity_id' => $entityId,
                            'user_id'   => $recipient['user_id'],
                            'title'     => $request->title,
                            'message'   => $request->message,
                            'is_read'   => false,
                        ]);

                        try {
                            \App\Services\FirebaseNotificationService::notify(
                                $recipient['user_id'],
                                $request->title,
                                $request->message,
                                [
                                    'type'        => 'campaign',
                                    'campaign_id' => $campaign->id,
                                ]
                            );
                            $recipients[$idx]['status'] = 'success';
                            $recipients[$idx]['sent_at'] = now()->toDateTimeString();
                        } catch (\Throwable $fErr) {
                            $recipients[$idx]['status'] = 'failed';
                            $recipients[$idx]['error'] = $fErr->getMessage();
                            \Illuminate\Support\Facades\Log::error("[CampaignController] ❌ Échec Push direct user #{$recipient['user_id']}: " . $fErr->getMessage());
                        }
                    } else {
                        $recipients[$idx]['status'] = 'failed';
                        $recipients[$idx]['error'] = 'Utilisateur introuvable pour notification App';
                    }
                }
            }

            // Mise à jour de la campagne avec les statuts exacts de chaque destinataire
            $campaign->update([
                'send_to' => $recipients,
            ]);
        }

        return response()->json([
            'message' => $request->isScheduled ? 'Campagne programmée avec succès' : 'Campagne envoyée avec succès',
            'data' => $campaign
        ], 201);
    }

    /**
     * Relance manuellement les destinataires en échec (failed) d'une campagne
     */
    public function retry(Request $request, $id): JsonResponse
    {
        $entityId = $this->entityId($request);
        $campaign = Campaign::where('id', $id)->where('entity_id', $entityId)->first();

        if (!$campaign) {
            return response()->json(['message' => 'Campagne non trouvée'], 404);
        }

        $recipients = $campaign->send_to ?? [];
        if (!is_array($recipients)) {
            $recipients = json_decode($recipients, true) ?? [];
        }

        $entity = \App\Models\Entity::find($entityId);
        $retriedCount = 0;
        $successCount = 0;

        foreach ($recipients as $idx => $recipient) {
            // Seuls les destinataires en échec sont relancés
            if (($recipient['status'] ?? '') === 'failed') {
                $retriedCount++;

                // A. RELANCE EMAIL
                if ($campaign->type === 'email' && !empty($recipient['email'])) {
                    try {
                        \Illuminate\Support\Facades\Mail::raw($campaign->message, function ($msg) use ($recipient, $campaign, $entity) {
                            $msg->to($recipient['email'])
                                ->subject($campaign->title);
                            app(ShopMailFromResolver::class)->applyTo(function (string $address, string $name) use ($msg) {
                                $msg->from($address, $name);
                            }, $entity, request());
                        });
                        $recipients[$idx]['status'] = 'success';
                        $recipients[$idx]['sent_at'] = now()->toDateTimeString();
                        unset($recipients[$idx]['error']);
                        $successCount++;
                    } catch (\Throwable $mErr) {
                        $recipients[$idx]['error'] = $mErr->getMessage();
                    }
                }

                // B. RELANCE SMS
                if ($campaign->type === 'sms' && !empty($recipient['phone'])) {
                    $pubKey = $entity?->diotko_public_key ?: env('DIOTKO_SMS_PUBLIC_KEY');
                    $secKey = $entity?->diotko_secret_key ?: env('DIOTKO_SMS_SECRET_KEY');

                    $smsService = new \App\Services\NotificationsService($pubKey, $secKey);
                    $fullPhone = ($recipient['ccphone'] ?? '+221') . $recipient['phone'];
                    try {
                        $smsService->sendSmsNow([$fullPhone], $campaign->message);
                        $recipients[$idx]['status'] = 'success';
                        $recipients[$idx]['sent_at'] = now()->toDateTimeString();
                        unset($recipients[$idx]['error']);
                        $successCount++;
                    } catch (\Throwable $sErr) {
                        $recipients[$idx]['error'] = $sErr->getMessage();
                    }
                }

                // C. RELANCE PUSH
                if ($campaign->type === 'app' && !empty($recipient['user_id'])) {
                    try {
                        \App\Services\FirebaseNotificationService::notify(
                            $recipient['user_id'],
                            $campaign->title,
                            $campaign->message,
                            [
                                'type'        => 'campaign',
                                'campaign_id' => $campaign->id,
                            ]
                        );
                        $recipients[$idx]['status'] = 'success';
                        $recipients[$idx]['sent_at'] = now()->toDateTimeString();
                        unset($recipients[$idx]['error']);
                        $successCount++;
                    } catch (\Throwable $fErr) {
                        $recipients[$idx]['error'] = $fErr->getMessage();
                    }
                }
            }
        }

        $campaign->update([
            'send_to' => $recipients,
            'status' => 'Envoyé',
        ]);

        return response()->json([
            'message' => "Relance effectuée : {$successCount} / {$retriedCount} renvois réussis.",
            'data' => $campaign,
        ]);
    }


    /**
     * Formate un utilisateur existant pour le canal sélectionné
     */
    private function formatRecipient(string $type, User $user): array
    {
        $base = [
            'user_id' => $user->id,
            'name' => $user->name,
            'status' => 'success', // En simulation ou avec passerelles réelles
            'sent_at' => now()->toDateTimeString()
        ];

        if ($type === 'email') {
            $base['email'] = $user->email;
        } elseif ($type === 'sms') {
            $base['ccphone'] = '+221';
            // Retire l'éventuel indicatif déjà présent
            $phone = preg_replace('/^\+221/', '', $user->phone);
            $base['phone'] = $phone;
        }

        return $base;
    }

    /**
     * Formate un destinataire saisi manuellement
     */
    private function formatManualRecipient(string $type, string $input): array
    {
        $base = [
            'user_id' => null,
            'name' => null,
            'status' => 'success',
            'sent_at' => now()->toDateTimeString()
        ];

        if ($type === 'email') {
            $base['email'] = $input;
        } elseif ($type === 'sms') {
            $base['ccphone'] = '+221';
            $phone = preg_replace('/^\+221/', '', $input);
            $base['phone'] = $phone;
        } elseif ($type === 'app') {
            // Pour l'app on a besoin d'un ID de user, on cherche s'il y a un user correspondant par email ou téléphone
            $user = User::where('email', $input)->orWhere('phone', $input)->first();
            if ($user) {
                $base['user_id'] = $user->id;
                $base['name'] = $user->name;
            } else {
                $base['status'] = 'failed';
            }
        }

        return $base;
    }
}
