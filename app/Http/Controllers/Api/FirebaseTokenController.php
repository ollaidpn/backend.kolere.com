<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FirebaseToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FirebaseTokenController extends Controller
{
    /**
     * POST /api/fcm/token (ou route enregistrée)
     * Enregistre un token FCM pour l'utilisateur/manager authentifié.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'fcm_token' => 'required|string|max:500',
            'device_type' => 'nullable|string|max:20',
            'device_id' => 'nullable|string|max:255',
            'device_name' => 'nullable|string|max:255',
            'app_version' => 'nullable|string|max:20',
            'app_platform' => 'nullable|string|max:10',
        ]);

        $user = $request->user();

        Log::info('[FCM-TOKEN] 📥 Réception token FCM', [
            'user_id' => $user->id ?? null,
            'token_preview' => substr($request->input('fcm_token'), 0, 16) . '...',
            'device_type' => $request->input('device_type'),
            'device_name' => $request->input('device_name'),
        ]);

        $firebaseToken = FirebaseToken::registerToken(
            authenticatable: $user,
            token: $request->input('fcm_token'),
            deviceType: $request->input('device_type'),
            deviceId: $request->input('device_id'),
            deviceName: $request->input('device_name'),
            appVersion: $request->input('app_version'),
            appPlatform: $request->input('app_platform'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Token FCM enregistré avec succès.',
            'data' => $firebaseToken,
        ]);
    }

    /**
     * POST /api/fcm/check-token
     * Vérifie si un token FCM existe et est actif pour cet utilisateur.
     */
    public function check(Request $request): JsonResponse
    {
        $request->validate([
            'fcm_token' => 'required|string|max:500',
        ]);

        $user = $request->user();
        $tokens = FirebaseToken::getActiveTokensForTarget($user);
        $exists = in_array($request->input('fcm_token'), $tokens);

        Log::info('[FCM-TOKEN] 🔍 Check token', [
            'user_id' => $user->id ?? null,
            'token_preview' => substr($request->input('fcm_token'), 0, 16) . '...',
            'exists' => $exists,
        ]);

        return response()->json([
            'success' => true,
            'exists' => $exists,
        ]);
    }

    /**
     * POST /api/fcm/deactivate-token
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'fcm_token' => 'required|string|max:500',
        ]);

        $user = $request->user();

        Log::info('[FCM-TOKEN] 🔴 Désactivation token', [
            'user_id' => $user->id ?? null,
            'token_preview' => substr($request->input('fcm_token'), 0, 16) . '...',
        ]);

        $deactivated = FirebaseToken::deactivateToken($user, $request->input('fcm_token'));

        return response()->json([
            'success' => $deactivated,
            'message' => $deactivated ? 'Token FCM désactivé.' : 'Token non trouvé.',
        ]);
    }
}
