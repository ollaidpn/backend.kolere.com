<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Entity;
use App\Models\Link;
use App\Models\Manager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Services\ShopMailFromResolver;
use App\Services\NotificationsService;

class InvitationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $entityId = $request->attributes->get('current_entity_id') ?? $request->input('entity_id');

        Log::info('[InvitationController@index] Listing invitations', ['entity_id' => $entityId]);

        try {
            $query = Invitation::query()->with(['entity.domain'])->orderByDesc('created_at');

            if ($entityId) {
                $query->where('entity_id', $entityId);
            }

            return response()->json([
                'data' => $query->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('[InvitationController@index] Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $entityId = $request->attributes->get('current_entity_id') ?? $request->input('entity_id');
        $inviteType = $request->input('invite_type', 'email');

        Log::info('[InvitationController@store] Creating invitation', [
            'email' => $request->email,
            'invite_type' => $inviteType,
            'entity_id' => $entityId,
        ]);
        try {
            $request->merge(['entity_id' => $entityId, 'invite_type' => $inviteType]);

            $rules = [
                'entity_id'   => 'required|exists:entities,id',
                'invite_type' => 'nullable|in:email,phone',
                'name'        => 'required|string|max:255',
                'ccphone'     => 'nullable|string|max:10',
                'phone'       => 'nullable|string|max:20',
                'is_admin'    => 'boolean',
            ];

            if ($inviteType === 'phone') {
                $rules['email'] = 'nullable|email|max:255';
                $rules['ccphone'] = 'required|string|max:10';
                $rules['phone'] = 'required|string|max:20';
            } else {
                $rules['email'] = 'required|email|max:255';
            }

            $request->validate($rules);

            $managerExists = $inviteType === 'phone'
                ? Manager::where('ccphone', $request->input('ccphone'))
                    ->where('phone', $request->input('phone'))
                    ->exists()
                : Manager::whereRaw('LOWER(email) = ?', [mb_strtolower(trim((string) $request->input('email')))])
                    ->exists();

            if ($managerExists) {
                return response()->json([
                    'message' => $inviteType === 'phone'
                        ? 'Un manager avec ce numéro existe déjà.'
                        : 'Un manager avec cet email existe déjà.',
                ], 422);
            }

            $email = $inviteType === 'phone'
                ? 'invite-' . Str::uuid()->toString() . '@kolere.local'
                : mb_strtolower(trim((string) $request->input('email')));

            $invitation = Invitation::create([
                'entity_id' => $request->input('entity_id'),
                'email'     => $email,
                'name'      => $request->input('name'),
                'ccphone'   => $request->input('ccphone'),
                'phone'     => $request->input('phone'),
                'token'     => Str::uuid()->toString(),
                'status'    => 'pending',
                'invite_type' => $inviteType,
                'is_admin'  => $request->input('is_admin', false),
            ]);
            Log::info('[InvitationController@store] Invitation created', ['invitation_id' => $invitation->id]);

            $deliveryResult = $this->deliverInvitation($invitation, $request, $entityId);

            if (!$deliveryResult['success']) {
                return response()->json([
                    'message' => $deliveryResult['message'],
                    'data' => $invitation->load('entity.domain'),
                ], 500);
            }

            return response()->json([
                'message' => 'Invitation envoyée.',
                'data'    => $invitation->load('entity.domain'),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[InvitationController@store] Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }

    public function resend(Request $request, Invitation $invitation): JsonResponse
    {
        $entityId = $request->attributes->get('current_entity_id') ?? $request->input('entity_id');

        if ($entityId && (int) $invitation->entity_id !== (int) $entityId) {
            return response()->json(['message' => 'Invitation introuvable.'], 404);
        }

        Log::info('[InvitationController@resend] Resending invitation', [
            'invitation_id' => $invitation->id,
            'entity_id' => $entityId,
        ]);

        try {
            if ($invitation->status !== 'pending') {
                return response()->json(['message' => 'Cette invitation a déjà été traitée.'], 422);
            }

            $deliveryResult = $this->deliverInvitation($invitation, $request, $entityId);

            if (!$deliveryResult['success']) {
                return response()->json([
                    'message' => $deliveryResult['message'],
                ], 500);
            }

            return response()->json(['message' => 'Invitation renvoyée.']);
        } catch (\Exception $e) {
            Log::error('[InvitationController@resend] Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }

    public function destroy(Request $request, Invitation $invitation): JsonResponse
    {
        $entityId = $request->attributes->get('current_entity_id') ?? $request->input('entity_id');

        if ($entityId && (int) $invitation->entity_id !== (int) $entityId) {
            return response()->json(['message' => 'Invitation introuvable.'], 404);
        }

        Log::info('[InvitationController@destroy] Deleting invitation', [
            'invitation_id' => $invitation->id,
            'entity_id' => $entityId,
        ]);

        try {
            $invitation->delete();

            return response()->json(['message' => 'Invitation supprimée.']);
        } catch (\Exception $e) {
            Log::error('[InvitationController@destroy] Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }

    public function show(string $token): JsonResponse
    {
        Log::info('[InvitationController@show] Fetching invitation', ['token' => $token]);
        try {
            $invitation = Invitation::where('token', $token)
                ->with('entity.domain')
                ->firstOrFail();

            Log::info('[InvitationController@show] Found', ['invitation_id' => $invitation->id]);
            return response()->json(['data' => $invitation]);
        } catch (\Exception $e) {
            Log::error('[InvitationController@show] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Invitation introuvable.'], 404);
        }
    }

    public function accept(Request $request, string $token): JsonResponse
    {
        Log::info('[InvitationController@accept] Accepting invitation', ['token' => $token]);
        try {
            $invitation = Invitation::where('token', $token)
                ->where('status', 'pending')
                ->firstOrFail();

            $manager = $invitation->invite_type === 'phone'
                ? Manager::where('ccphone', $invitation->ccphone)
                    ->where('phone', $invitation->phone)
                    ->first()
                : Manager::whereRaw('LOWER(email) = ?', [mb_strtolower(trim((string) $invitation->email))])
                    ->first();

            if (!$manager) {
                if (!$request->filled('password')) {
                    return response()->json([
                        'message' => 'Compte à créer.',
                        'manager_existed' => false,
                    ]);
                }

                $rules = [
                    'password' => 'required|string|min:6',
                    'name'     => 'nullable|string|max:255',
                    'phone'    => 'nullable|string|max:20',
                    'ccphone'  => 'nullable|string|max:10',
                ];

                if ($invitation->invite_type === 'phone') {
                    $rules['email'] = 'required|email|max:255';
                }

                $request->validate($rules);

                $manager = Manager::create([
                    'name'      => $request->input('name', $invitation->name),
                    'email'     => $invitation->invite_type === 'phone'
                        ? mb_strtolower(trim((string) $request->input('email')))
                        : $invitation->email,
                    'ccphone'   => $request->input('ccphone', $invitation->ccphone),
                    'phone'     => $request->input('phone', $invitation->phone),
                    'password'  => Hash::make($request->input('password')),
                    'reference' => 'MGR-' . strtoupper(Str::random(8)),
                    'status'    => 'active',
                ]);
                Log::info('[InvitationController@accept] Manager created', ['manager_id' => $manager->id]);
            } else {
                Log::info('[InvitationController@accept] Manager already exists', ['manager_id' => $manager->id]);
            }

            Link::updateOrCreate(
                ['manager_id' => $manager->id],
                [
                    'entity_id' => $invitation->entity_id,
                    'is_admin'  => $invitation->is_admin,
                ]
            );
            Log::info('[InvitationController@accept] Link upserted');

            $invitation->update(['status' => 'accepted']);
            Log::info('[InvitationController@accept] Success');

            return response()->json([
                'message'         => 'Invitation acceptée.',
                'manager_existed' => $manager->wasRecentlyCreated === false,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[InvitationController@accept] Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }

    private function deliverInvitation(Invitation $invitation, Request $request, ?int $entityId = null): array
    {
        $entity = $entityId ? Entity::find($entityId) : $invitation->entity;
        $frontendBase = rtrim((string) ($request->headers->get('origin') ?: env('FRONTEND_URL', config('app.url'))), '/');
        $inviteLink = $frontendBase . '/invitation/' . $invitation->token;
        $shopName = $entity ? $entity->name : 'votre boutique';
        $deliveryErrors = [];
        $deliveredChannels = [];

        if (filter_var((string) $invitation->email, FILTER_VALIDATE_EMAIL)) {
            try {
                Mail::send('emails.manager_invitation', [
                    'invitation' => $invitation,
                    'inviteLink' => $inviteLink,
                    'shopName'   => $shopName,
                ], function ($message) use ($invitation, $shopName, $entity, $request) {
                    $message->to($invitation->email)->subject("Invitation manager - {$shopName}");
                    app(ShopMailFromResolver::class)->applyTo(function (string $address, string $name) use ($message) {
                        $message->from($address, $name);
                    }, $entity, $request);
                });

                $deliveredChannels[] = 'email';
            } catch (\Throwable $mailError) {
                Log::warning('[InvitationController@deliverInvitation] Mail send failed', [
                    'invitation_id' => $invitation->id,
                    'message' => $mailError->getMessage(),
                ]);
                $deliveryErrors[] = 'Impossible d\'envoyer l\'email d\'invitation.';
            }
        }

        $phone = trim((string) ($invitation->ccphone ?: '') . (string) ($invitation->phone ?: ''));
        if ($phone !== '') {
            try {
                $pubKey = $entity?->diotko_public_key ?: env('DIOTKO_SMS_PUBLIC_KEY');
                $secKey = $entity?->diotko_secret_key ?: env('DIOTKO_SMS_SECRET_KEY');
                $smsService = new NotificationsService($pubKey, $secKey);
                $smsMessage = "Invitation Kolere pour {$shopName}. Ouvrez ce lien pour accepter: {$inviteLink}";
                $smsResult = $smsService->sendSmsNow([$phone], $smsMessage);

                if (!($smsResult['success'] ?? false)) {
                    Log::warning('[InvitationController@deliverInvitation] SMS send failed', [
                        'invitation_id' => $invitation->id,
                        'message' => $smsResult['message'] ?? 'Erreur SMS inconnue',
                    ]);
                    $deliveryErrors[] = $smsResult['message'] ?? 'Impossible d\'envoyer le SMS d\'invitation.';
                } else {
                    $deliveredChannels[] = 'sms';
                }
            } catch (\Throwable $smsError) {
                Log::warning('[InvitationController@deliverInvitation] SMS send exception', [
                    'invitation_id' => $invitation->id,
                    'message' => $smsError->getMessage(),
                ]);
                $deliveryErrors[] = 'Impossible d\'envoyer le SMS d\'invitation.';
            }
        }

        if (empty($deliveredChannels)) {
            return [
                'success' => false,
                'message' => $deliveryErrors[0] ?? 'Aucun canal de notification disponible.',
            ];
        }

        return [
            'success' => true,
            'message' => 'Invitation envoyée via ' . implode(' et ', $deliveredChannels) . '.',
            'errors' => $deliveryErrors,
        ];
    }

    public function refuse(string $token): JsonResponse
    {
        Log::info('[InvitationController@refuse] Refusing invitation', ['token' => $token]);
        try {
            $invitation = Invitation::where('token', $token)
                ->where('status', 'pending')
                ->firstOrFail();

            $invitation->update(['status' => 'refused']);
            Log::info('[InvitationController@refuse] Success');

            return response()->json(['message' => 'Invitation refusée.']);
        } catch (\Exception $e) {
            Log::error('[InvitationController@refuse] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }
}
