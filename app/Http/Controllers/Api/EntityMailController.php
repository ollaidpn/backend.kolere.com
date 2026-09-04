<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EntityMail;
use App\Services\NotificationsService;
use App\Services\ShopMailFromResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EntityMailController extends Controller
{
    private function entityId(Request $request): ?int
    {
        return $request->attributes->get('current_entity_id');
    }

    private function normalizeDomain(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $domain = trim((string) $value);
        if ($domain === '') {
            return null;
        }

        return str_starts_with($domain, '@') ? $domain : '@' . $domain;
    }

    public function index(Request $request): JsonResponse
    {
        $entityId = $this->entityId($request);
        if (!$entityId) {
            return response()->json(['message' => 'Entité introuvable'], 400);
        }

        $mails = EntityMail::query()
            ->where('entity_id', $entityId)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $mails]);
    }

    public function store(Request $request): JsonResponse
    {
        $entityId = $this->entityId($request);
        if (!$entityId) {
            return response()->json(['message' => 'Entité introuvable'], 400);
        }

        $normalizedDomain = $this->normalizeDomain($request->input('at_domain'));
        if (!$normalizedDomain) {
            return response()->json(['message' => 'Suffixe email invalide'], 422);
        }

        $validated = $request->validate([
            'username' => [
                'required',
                'string',
                'max:120',
                'regex:/^[a-zA-Z0-9._-]+$/',
                Rule::unique('entity_mails')->where(fn ($query) => $query
                    ->where('entity_id', $entityId)
                    ->where('at_domain', $normalizedDomain)),
            ],
            'at_domain' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
        ]);

        $mail = EntityMail::create([
            'entity_id' => $entityId,
            'username' => strtolower(trim($validated['username'])),
            'at_domain' => $normalizedDomain,
            'status' => 'requested',
            'password' => $validated['password'] ?? null,
            'requested_at' => Carbon::now(),
        ]);

        $entity = null;
        try {
            $entity = \App\Models\Entity::with('domain')->find($entityId);
            $mailAddress = $mail->email_address;
            $domainName = $entity?->domain?->name ? '@' . ltrim((string) $entity->domain->name, '@') : $normalizedDomain;
            $passwordLine = $mail->password
                ? "Mot de passe demandé par la boutique : {$mail->password}"
                : "Mot de passe non défini par la boutique. Il devra être attribué par un admin.";

            $subject = "Nouvelle demande de création email - {$mailAddress}";
            $body = implode("\n", [
                "Une nouvelle demande de boîte email a été créée.",
                "",
                "Boutique : " . ($entity?->name ?? 'Boutique'),
                "Adresse demandée : {$mailAddress}",
                "Username : {$mail->username}",
                "Suffixe : {$domainName}",
                "Statut : {$mail->status}",
                $passwordLine,
                "Demandée le : " . Carbon::now()->toDateTimeString(),
            ]);

            Mail::raw($body, function ($message) use ($subject, $request) {
                $message->to('dev@ollaid.com')
                    ->cc('ollaidpn@gmail.com')
                    ->subject($subject);

                app(ShopMailFromResolver::class)->applyTo(function (string $address, string $name) use ($message) {
                    $message->from($address, $name);
                }, null, $request);
            });
        } catch (\Throwable $mailError) {
            Log::warning('[EntityMailController@store] Admin email notification failed', [
                'message' => $mailError->getMessage(),
            ]);
        }

        try {
            $smsMessage = "Nouvelle demande email: {$mail->email_address} pour " . ($entity?->name ?? 'une boutique') . ".";
            if (mb_strlen($smsMessage) > 160) {
                $smsMessage = mb_substr($smsMessage, 0, 160);
            }

            $smsService = new NotificationsService();
            $smsService->sendSmsNow(['+221786080939'], $smsMessage);
        } catch (\Throwable $smsError) {
            Log::warning('[EntityMailController@store] Admin SMS notification failed', [
                'message' => $smsError->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'Demande de boîte email enregistrée.',
            'data' => $mail,
        ], 201);
    }

    public function update(Request $request, EntityMail $entityMail): JsonResponse
    {
        $entityId = $this->entityId($request);
        if (!$entityId || (int) $entityMail->entity_id !== (int) $entityId) {
            return response()->json(['message' => 'Ressource introuvable'], 404);
        }

        $validated = $request->validate([
            'new_password' => ['required', 'string', 'min:6', 'max:255'],
        ]);

        $newPassword = trim($validated['new_password']);

        $entityMail->update([
            'new_password' => $newPassword,
            'status' => 'requested',
        ]);

        $entity = null;
        try {
            $entity = \App\Models\Entity::with('domain')->find($entityId);
            $mailAddress = $entityMail->email_address;
            $oldPasswordText = $entityMail->password ? $entityMail->password : "Non défini";

            $subject = "Demande de changement de mot de passe email - {$mailAddress}";
            $body = implode("\n", [
                "Une demande de modification de mot de passe de boîte email a été faite par la boutique.",
                "",
                "Boutique : " . ($entity?->name ?? 'Boutique'),
                "Adresse email : {$mailAddress}",
                "Ancien mot de passe : {$oldPasswordText}",
                "Nouveau mot de passe demandé : {$newPassword}",
                "Nouveau statut : requested (Demandé)",
                "Date de demande : " . Carbon::now()->toDateTimeString(),
            ]);

            Mail::raw($body, function ($message) use ($subject, $request) {
                $message->to('dev@ollaid.com')
                    ->cc('ollaidpn@gmail.com')
                    ->subject($subject);

                app(ShopMailFromResolver::class)->applyTo(function (string $address, string $name) use ($message) {
                    $message->from($address, $name);
                }, null, $request);
            });
        } catch (\Throwable $mailError) {
            Log::warning('[EntityMailController@update] Admin email notification failed', [
                'message' => $mailError->getMessage(),
            ]);
        }

        try {
            $smsMessage = "Changement MDP demandé pour {$entityMail->email_address} (" . ($entity?->name ?? 'Boutique') . ").";
            if (mb_strlen($smsMessage) > 160) {
                $smsMessage = mb_substr($smsMessage, 0, 160);
            }

            $smsService = new NotificationsService();
            $smsService->sendSmsNow(['+221786080939'], $smsMessage);
        } catch (\Throwable $smsError) {
            Log::warning('[EntityMailController@update] Admin SMS notification failed', [
                'message' => $smsError->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'Demande de changement de mot de passe envoyée.',
            'data' => $entityMail->refresh(),
        ]);
    }

    public function destroy(Request $request, EntityMail $entityMail): JsonResponse
    {
        $entityId = $this->entityId($request);
        if (!$entityId || (int) $entityMail->entity_id !== (int) $entityId) {
            return response()->json(['message' => 'Ressource introuvable'], 404);
        }

        $request->validate([
            'admin_password' => ['required', 'string'],
        ]);

        $user = $request->user();
        if (!\Illuminate\Support\Facades\Hash::check($request->input('admin_password'), $user->password)) {
            return response()->json(['message' => 'Mot de passe administrateur incorrect.'], 422);
        }

        $entityMail->update([
            'status' => 'deleted',
        ]);

        return response()->json([
            'message' => 'Boîte email marquée comme supprimée.',
            'data' => $entityMail->refresh(),
        ]);
    }
}
