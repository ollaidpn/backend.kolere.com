<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminInvitation;
use App\Services\NotificationsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AdminUserController extends Controller
{
    public function index(): JsonResponse
    {
        Log::info('[AdminUserController@index] Fetching admins');
        try {
            $admins = Admin::orderBy('created_at', 'desc')->get();
            Log::info('[AdminUserController@index] Success', ['count' => $admins->count()]);
            return response()->json(['data' => $admins]);
        } catch (\Exception $e) {
            Log::error('[AdminUserController@index] Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        Log::info('[AdminUserController@store] Creating admin invitation', ['email' => $request->email]);
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'ccphone' => 'nullable|string|max:10',
                'phone' => 'nullable|string|max:20',
            ]);

            if (Admin::where('email', $request->email)->exists()) {
                Log::warning('[AdminUserController@store] Email already exists', ['email' => $request->email]);
                return response()->json(['message' => 'Un administrateur avec cet email existe déjà.'], 422);
            }

            $invitation = AdminInvitation::create([
                'name' => $request->name,
                'email' => $request->email,
                'ccphone' => $request->ccphone,
                'phone' => $request->phone,
                'token' => Str::uuid()->toString(),
                'status' => 'pending',
            ]);
            Log::info('[AdminUserController@store] Invitation created', ['invitation_id' => $invitation->id]);

            $frontendBase = rtrim((string) env('FRONTEND_URL', config('app.url')), '/');
            $inviteLink = $frontendBase . '/admin-invitations/' . $invitation->token;
            $notificationErrors = [];

            try {
                Mail::raw(
                    "Bonjour {$invitation->name},\n\nVous avez été invité(e) à rejoindre Kolere en tant qu'administrateur.\n\nOuvrez ce lien pour accepter l'invitation : {$inviteLink}\n",
                    function ($message) use ($invitation) {
                        $message->to($invitation->email)->subject('Invitation administrateur Kolere');
                    }
                );
            } catch (\Throwable $mailError) {
                Log::warning('[AdminUserController@store] Mail send failed', ['message' => $mailError->getMessage()]);
                $notificationErrors[] = 'Email non envoyé.';
            }

            $phone = trim((string) ($invitation->ccphone ?: '') . (string) ($invitation->phone ?: ''));
            if ($phone !== '') {
                try {
                    $smsService = new NotificationsService();
                    $smsResult = $smsService->sendSmsNow([$phone], "Invitation administrateur Kolere. Ouvrez ce lien pour accepter: {$inviteLink}");

                    if (!($smsResult['success'] ?? false)) {
                        Log::warning('[AdminUserController@store] SMS send failed', ['message' => $smsResult['message'] ?? 'Erreur SMS inconnue']);
                        $notificationErrors[] = 'SMS non envoyé.';
                    }
                } catch (\Throwable $smsError) {
                    Log::warning('[AdminUserController@store] SMS send exception', ['message' => $smsError->getMessage()]);
                    $notificationErrors[] = 'SMS non envoyé.';
                }
            }

            return response()->json([
                'message' => empty($notificationErrors)
                    ? 'Invitation envoyée avec succès.'
                    : 'Invitation créée, mais la notification n\'a pas pu être envoyée entièrement.',
                'data' => $invitation,
                'notification_errors' => $notificationErrors,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[AdminUserController@store] Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }

    public function update(Request $request, Admin $admin): JsonResponse
    {
        Log::info('[AdminUserController@update] Updating admin', ['admin_id' => $admin->id]);
        try {
            $request->validate([
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|max:255',
                'ccphone' => 'nullable|string|max:10',
                'phone' => 'nullable|string|max:20',
            ]);

            $admin->update($request->only(['name', 'email', 'ccphone', 'phone']));
            Log::info('[AdminUserController@update] Success');

            return response()->json([
                'message' => 'Administrateur mis à jour.',
                'data' => $admin->fresh(),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[AdminUserController@update] Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }

    public function toggleStatus(Admin $admin): JsonResponse
    {
        Log::info('[AdminUserController@toggleStatus] Toggling', ['admin_id' => $admin->id, 'current' => $admin->status]);
        try {
            $newStatus = $admin->status === 'active' ? 'inactive' : 'active';
            $admin->update(['status' => $newStatus]);
            Log::info('[AdminUserController@toggleStatus] Success', ['new_status' => $newStatus]);

            return response()->json([
                'message' => "Compte {$newStatus}.",
                'data' => $admin->fresh(),
            ]);
        } catch (\Exception $e) {
            Log::error('[AdminUserController@toggleStatus] Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }

    public function resetPassword(Admin $admin): JsonResponse
    {
        Log::info('[AdminUserController@resetPassword] Resetting', ['admin_id' => $admin->id]);
        try {
            $tempPassword = Str::random(12);
            $admin->update(['password' => Hash::make($tempPassword)]);
            Log::info('[AdminUserController@resetPassword] Success');

            return response()->json([
                'message' => 'Mot de passe réinitialisé. Un email a été envoyé.',
            ]);
        } catch (\Exception $e) {
            Log::error('[AdminUserController@resetPassword] Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }

    public function destroy(Request $request, Admin $admin): JsonResponse
    {
        Log::info('[AdminUserController@destroy] Deleting admin', ['admin_id' => $admin->id]);
        try {
            $request->validate([
                'password' => 'required|string',
            ]);

            $currentAdmin = $request->user();

            if ($currentAdmin && $currentAdmin->id === $admin->id) {
                Log::warning('[AdminUserController@destroy] Self-deletion attempt');
                return response()->json(['message' => 'Vous ne pouvez pas supprimer votre propre compte.'], 403);
            }

            if (!Hash::check($request->password, $currentAdmin->password)) {
                Log::warning('[AdminUserController@destroy] Wrong password');
                return response()->json(['message' => 'Mot de passe incorrect.'], 403);
            }

            $admin->delete();
            Log::info('[AdminUserController@destroy] Success');

            return response()->json(['message' => 'Compte administrateur supprimé.']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[AdminUserController@destroy] Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }
}
