<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Card;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ClientProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Charger les informations complètes du profil
            $profileData = User::where('id', $user->id)
                               ->with(['card.cardType', 'card.entity'])
                               ->first();

            if (!$profileData) {
                return response()->json(['message' => 'Profil non trouvé'], 404);
            }

            $profile = [
                'id' => $profileData->id,
                'name' => $profileData->name,
                'email' => $profileData->email,
                'phone' => $profileData->phone,
                'address' => $profileData->address,
                'created_at' => $profileData->created_at,
                'updated_at' => $profileData->updated_at,
                'email_verified_at' => $profileData->email_verified_at,
            ];

            // Ajouter les informations de carte si existante
            if ($profileData->card) {
                $profile['loyalty_card'] = [
                    'id' => $profileData->card->id,
                    'points' => $profileData->card->points,
                    'status' => $profileData->card->status,
                    'created_at' => $profileData->card->created_at,
                    'card_type' => $profileData->card->cardType ? [
                        'name' => $profileData->card->cardType->name,
                        'discount' => $profileData->card->cardType->discount,
                    ] : null,
                    'entity' => $profileData->card->entity ? [
                        'name' => $profileData->card->entity->name,
                        'logo' => $profileData->card->entity->logo,
                        'address' => $profileData->card->entity->address,
                        'phone' => $profileData->card->entity->phone,
                    ] : null,
                ];
            }

            return response()->json(['data' => $profile]);
        } catch (\Exception $e) {
            Log::error('[ClientProfileController@show] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement du profil'], 500);
        }
    }

    public function update(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Validation des données
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'phone' => 'sometimes|string|max:20',
                'address' => 'sometimes|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Données invalides',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Mise à jour des informations
            $updateData = [];
            
            if ($request->has('name')) {
                $updateData['name'] = $request->input('name');
            }
            
            if ($request->has('phone')) {
                $updateData['phone'] = $request->input('phone');
            }
            
            if ($request->has('address')) {
                $updateData['address'] = $request->input('address');
            }

            $user->update($updateData);

            // Retourner le profil mis à jour
            return response()->json([
                'message' => 'Profil mis à jour avec succès',
                'data' => $user->fresh()->load(['card.cardType', 'card.entity'])
            ]);
        } catch (\Exception $e) {
            Log::error('[ClientProfileController@update] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la mise à jour du profil'], 500);
        }
    }

    public function updatePassword(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Validation
            $validator = Validator::make($request->all(), [
                'current_password' => 'required|string',
                'password' => 'required|string|min:8|confirmed',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Données invalides',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Vérifier le mot de passe actuel
            if (!Hash::check($request->input('current_password'), $user->password)) {
                return response()->json(['message' => 'Mot de passe actuel incorrect'], 400);
            }

            // Mettre à jour le mot de passe
            $user->update([
                'password' => Hash::make($request->input('password'))
            ]);

            return response()->json(['message' => 'Mot de passe mis à jour avec succès']);
        } catch (\Exception $e) {
            Log::error('[ClientProfileController@updatePassword] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la mise à jour du mot de passe'], 500);
        }
    }

    public function updateEmail(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Validation
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|unique:users,email,' . $user->id,
                'password' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Données invalides',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Vérifier le mot de passe
            if (!Hash::check($request->input('password'), $user->password)) {
                return response()->json(['message' => 'Mot de passe incorrect'], 400);
            }

            // Mettre à jour l'email
            $user->update(['email' => $request->input('email')]);

            return response()->json([
                'message' => 'Email mis à jour avec succès',
                'data' => ['email' => $user->email]
            ]);
        } catch (\Exception $e) {
            Log::error('[ClientProfileController@updateEmail] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la mise à jour de l\'email'], 500);
        }
    }

    public function deleteAccount(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Validation
            $validator = Validator::make($request->all(), [
                'password' => 'required|string',
                'confirmation' => 'required|string|in:DELETE,DELETE-MY-ACCOUNT',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Données invalides',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Vérifier le mot de passe
            if (!Hash::check($request->input('password'), $user->password)) {
                return response()->json(['message' => 'Mot de passe incorrect'], 400);
            }

            // Supprimer le compte et toutes les données associées
            DB::transaction(function () use ($user) {
                // Supprimer la carte de fidélité
                if ($user->card) {
                    $user->card->delete();
                }

                // Supprimer les commandes
                $user->orders()->delete();

                // Supprimer l'utilisateur
                $user->delete();
            });

            return response()->json(['message' => 'Compte supprimé avec succès']);
        } catch (\Exception $e) {
            Log::error('[ClientProfileController@deleteAccount] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la suppression du compte'], 500);
        }
    }

    public function getPreferences(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Préférences de notification (à étendre)
            $preferences = [
                'notifications' => [
                    'email' => true,
                    'sms' => false,
                    'promotions' => true,
                ],
                'privacy' => [
                    'profile_visible' => true,
                    'share_data' => false,
                ],
                'display' => [
                    'language' => 'fr',
                    'theme' => 'light',
                    'currency' => 'XOF',
                ],
            ];

            return response()->json(['data' => $preferences]);
        } catch (\Exception $e) {
            Log::error('[ClientProfileController@getPreferences] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement des préférences'], 500);
        }
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Validation des préférences
            $validator = Validator::make($request->all(), [
                'notifications.email' => 'sometimes|boolean',
                'notifications.sms' => 'sometimes|boolean',
                'notifications.promotions' => 'sometimes|boolean',
                'privacy.profile_visible' => 'sometimes|boolean',
                'privacy.share_data' => 'sometimes|boolean',
                'display.language' => 'sometimes|string|in:fr,en',
                'display.theme' => 'sometimes|string|in:light,dark',
                'display.currency' => 'sometimes|string|in:XOF,FCFA',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Préférences invalides',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Pour l'instant, on retourne les préférences validées
            // (À implémenter avec une table preferences dans la BDD)
            $preferences = $request->only([
                'notifications.email', 'notifications.sms', 'notifications.promotions',
                'privacy.profile_visible', 'privacy.share_data',
                'display.language', 'display.theme', 'display.currency'
            ]);

            return response()->json([
                'message' => 'Préférences mises à jour avec succès',
                'data' => $preferences
            ]);
        } catch (\Exception $e) {
            Log::error('[ClientProfileController@updatePreferences] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la mise à jour des préférences'], 500);
        }
    }
}
