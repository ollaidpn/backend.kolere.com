<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Link;
use App\Models\Manager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Services\ShopMailFromResolver;

class InvitationController extends Controller
{
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

            if ($inviteType === 'email') {
                $frontendBase = rtrim((string) ($request->headers->get('origin') ?: env('FRONTEND_URL', config('app.url'))), '/');
                $inviteLink = $frontendBase . '/invitation/' . $invitation->token;
                $entity = \App\Models\Entity::find($entityId);
                $shopName = $entity ? $entity->name : 'votre boutique';

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
                } catch (\Throwable $mailError) {
                    Log::warning('[InvitationController@store] Mail send failed', [
                        'message' => $mailError->getMessage(),
                    ]);
                }
            }

            return response()->json([
                'message' => 'Invitation envoyée.',
                'data'    => $invitation->load('entity'),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[InvitationController@store] Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
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
