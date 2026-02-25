<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AdminInvitationController extends Controller
{
    public function show(string $token): JsonResponse
    {
        Log::info('[AdminInvitationController@show] Fetching', ['token' => $token]);
        try {
            $invitation = AdminInvitation::where('token', $token)->firstOrFail();
            Log::info('[AdminInvitationController@show] Found', ['id' => $invitation->id]);
            return response()->json(['data' => $invitation]);
        } catch (\Exception $e) {
            Log::error('[AdminInvitationController@show] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Invitation introuvable.'], 404);
        }
    }

    public function accept(Request $request, string $token): JsonResponse
    {
        Log::info('[AdminInvitationController@accept] Accepting', ['token' => $token]);
        try {
            $invitation = AdminInvitation::where('token', $token)->firstOrFail();

            if ($invitation->status !== 'pending') {
                Log::warning('[AdminInvitationController@accept] Already processed', ['status' => $invitation->status]);
                return response()->json(['message' => 'Cette invitation a déjà été traitée.'], 422);
            }

            if (Admin::where('email', $invitation->email)->exists()) {
                Log::warning('[AdminInvitationController@accept] Admin already exists', ['email' => $invitation->email]);
                return response()->json(['message' => 'Un compte administrateur avec cet email existe déjà.'], 422);
            }

            $request->validate([
                'password' => 'required|string|min:1',
                'name' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:20',
                'ccphone' => 'nullable|string|max:10',
            ]);

            Admin::create([
                'name' => $request->name ?: $invitation->name,
                'email' => $invitation->email,
                'password' => Hash::make($request->password),
                'status' => 'active',
                'reference' => 'ADM-' . strtoupper(Str::random(8)),
                'ccphone' => $request->ccphone ?: $invitation->ccphone,
                'phone' => $request->phone ?: $invitation->phone,
            ]);
            Log::info('[AdminInvitationController@accept] Admin created');

            $invitation->update(['status' => 'accepted']);
            Log::info('[AdminInvitationController@accept] Success');

            return response()->json(['message' => 'Compte administrateur créé avec succès.']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[AdminInvitationController@accept] Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }

    public function refuse(string $token): JsonResponse
    {
        Log::info('[AdminInvitationController@refuse] Refusing', ['token' => $token]);
        try {
            $invitation = AdminInvitation::where('token', $token)->firstOrFail();

            if ($invitation->status !== 'pending') {
                Log::warning('[AdminInvitationController@refuse] Already processed');
                return response()->json(['message' => 'Cette invitation a déjà été traitée.'], 422);
            }

            $invitation->update(['status' => 'refused']);
            Log::info('[AdminInvitationController@refuse] Success');

            return response()->json(['message' => 'Invitation refusée.']);
        } catch (\Exception $e) {
            Log::error('[AdminInvitationController@refuse] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }
}
