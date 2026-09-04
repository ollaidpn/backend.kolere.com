<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Card;
use App\Models\UserHealth;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use App\Services\ShopMailFromResolver;

class ClientProfileController extends Controller
{
    private function entityId(Request $request): ?int
    {
        return $request->attributes->get('current_entity_id');
    }

    public function show(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $entityId = $this->entityId($request);
            
            // Charger les informations complètes du profil
            $profileQuery = User::where('id', $user->id);
            $profileData = $profileQuery
                ->with(['card' => function ($query) use ($entityId) {
                    $query->when($entityId, fn ($q) => $q->where('entity_id', $entityId))
                          ->with(['cardType', 'entity']);
                }])
                ->first();

            if (!$profileData) {
                return response()->json(['message' => 'Profil non trouvé'], 404);
            }

            $fileService = new \App\Services\FileUploadService();
            $avatarUrl = null;
            if ($profileData->avatar) {
                $avatarUrl = str_starts_with($profileData->avatar, 'http')
                    ? $profileData->avatar
                    : $fileService->getUrl($profileData->avatar);
            }

            $profile = [
                'id' => $profileData->id,
                'name' => $profileData->name,
                'email' => $profileData->email,
                'phone' => $profileData->phone,
                'address' => $profileData->address,
                'avatar' => $avatarUrl,
                'created_at' => $profileData->created_at,
                'updated_at' => $profileData->updated_at,
                'email_verified_at' => $profileData->email_verified_at,
            ];

            // Ajouter les informations de carte si existante
            if ($profileData->card) {
                $profile['loyalty_card'] = [
                    'id' => $profileData->card->id,
                    'reference' => $profileData->card->reference,
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
                        'logo_url' => $profileData->card->entity->logo_url ?? $profileData->card->entity->logo,
                        'primary_color' => $profileData->card->entity->primary_color ?? '#0f172a',
                        'secondary_color' => $profileData->card->entity->secondary_color ?? '#f8fafc',
                        'address' => $profileData->card->entity->address,
                        'phone' => $profileData->card->entity->phone,
                    ] : null,

                ];
            }

            $health = UserHealth::where('user_id', $profileData->id)->first();
            if ($health) {
                $profile['health'] = [
                    'id' => $health->id,
                    'user_id' => $health->user_id,
                    'blood_type' => $health->blood_type,
                    'weight_kg' => $health->weight_kg,
                    'height_cm' => $health->height_cm,
                    'medical_history' => $health->medical_history,
                    'chronic_diseases' => $health->chronic_diseases,
                    'current_treatments' => $health->current_treatments,
                    'emergency_notes' => $health->emergency_notes,
                    'emergency_contact_name' => $health->emergency_contact_name,
                    'emergency_contact_phone' => $health->emergency_contact_phone,
                    'emergency_contact_relation' => $health->emergency_contact_relation,
                    'allergies' => $health->allergies ?? [],
                ];
            }

            return response()->json(['data' => $profile]);
        } catch (\Exception $e) {
            Log::error('[ClientProfileController@show] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement du profil'], 500);
        }
    }

    public function updateHealth(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $validated = $request->validate([
                'blood_type' => 'nullable|string|max:10',
                'weight_kg' => 'nullable|numeric',
                'height_cm' => 'nullable|integer',
                'medical_history' => 'nullable|string',
                'chronic_diseases' => 'nullable|string',
                'current_treatments' => 'nullable|string',
                'emergency_notes' => 'nullable|string',
                'emergency_contact_name' => 'nullable|string',
                'emergency_contact_phone' => 'nullable|string',
                'emergency_contact_relation' => 'nullable|string',
                'allergies' => 'nullable|array',
            ]);

            $health = UserHealth::where('user_id', $user->id)->first();
            if (!$health) {
                $health = new UserHealth();
                $health->user_id = $user->id;
            }

            $health->fill($validated);
            $health->save();

            return response()->json([
                'message' => 'Fiche médicale mise à jour',
                'data' => [
                    'id' => $health->id,
                    'user_id' => $health->user_id,
                    'blood_type' => $health->blood_type,
                    'weight_kg' => $health->weight_kg,
                    'height_cm' => $health->height_cm,
                    'medical_history' => $health->medical_history,
                    'chronic_diseases' => $health->chronic_diseases,
                    'current_treatments' => $health->current_treatments,
                    'emergency_notes' => $health->emergency_notes,
                    'emergency_contact_name' => $health->emergency_contact_name,
                    'emergency_contact_phone' => $health->emergency_contact_phone,
                    'emergency_contact_relation' => $health->emergency_contact_relation,
                    'allergies' => $health->allergies ?? [],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('[ClientProfileController@updateHealth] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la mise à jour de la fiche médicale'], 500);
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

            $freshUser = User::where('id', $user->id)
                ->with(['card' => function ($query) use ($request) {
                    $entityId = $this->entityId($request);
                    $query->when($entityId, fn ($q) => $q->where('entity_id', $entityId))
                          ->with(['cardType', 'entity']);
                }])
                ->first();

            if (!$freshUser) {
                return response()->json(['message' => 'Profil non trouvé'], 404);
            }

            $fileService = new \App\Services\FileUploadService();
            $avatarUrl = null;
            if ($freshUser->avatar) {
                $avatarUrl = str_starts_with($freshUser->avatar, 'http')
                    ? $freshUser->avatar
                    : $fileService->getUrl($freshUser->avatar);
            }

            $profile = [
                'id' => $freshUser->id,
                'name' => $freshUser->name,
                'email' => $freshUser->email,
                'phone' => $freshUser->phone,
                'address' => $freshUser->address,
                'avatar' => $avatarUrl,
                'created_at' => $freshUser->created_at,
                'updated_at' => $freshUser->updated_at,
                'email_verified_at' => $freshUser->email_verified_at,
            ];

            if ($freshUser->card) {
                $profile['loyalty_card'] = [
                    'id' => $freshUser->card->id,
                    'reference' => $freshUser->card->reference,
                    'points' => $freshUser->card->points,
                    'status' => $freshUser->card->status,
                    'created_at' => $freshUser->card->created_at,
                    'card_type' => $freshUser->card->cardType ? [
                        'name' => $freshUser->card->cardType->name,
                        'discount' => $freshUser->card->cardType->discount,
                    ] : null,
                    'entity' => $freshUser->card->entity ? [
                        'name' => $freshUser->card->entity->name,
                        'logo' => $freshUser->card->entity->logo,
                        'logo_url' => $freshUser->card->entity->logo_url ?? $freshUser->card->entity->logo,
                        'primary_color' => $freshUser->card->entity->primary_color ?? '#0f172a',
                        'secondary_color' => $freshUser->card->entity->secondary_color ?? '#f8fafc',
                        'address' => $freshUser->card->entity->address,
                        'phone' => $freshUser->card->entity->phone,
                    ] : null,

                ];
            }

            return response()->json([
                'message' => 'Profil mis à jour avec succès',
                'data' => $profile
            ]);
        } catch (\Exception $e) {
            Log::error('[ClientProfileController@update] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la mise à jour du profil'], 500);
        }
    }

    public function updateAvatar(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                'avatar' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Fichier invalide',
                    'errors' => $validator->errors()
                ], 422);
            }

            $fileService = new \App\Services\FileUploadService();
            // Supprimer l'ancien avatar
            if ($user->avatar && !str_starts_with($user->avatar, 'http')) {
                $fileService->delete($user->avatar);
            }

            $uploaded = $fileService->upload($request->file('avatar'), 'avatars');
            $url = $uploaded['url'];

            $user->update(['avatar' => $uploaded['path']]);

            return response()->json([
                'message' => 'Photo mise à jour avec succès',
                'data' => ['avatar' => $url]
            ]);
        } catch (\Exception $e) {
            Log::error('[ClientProfileController@updateAvatar] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du changement de photo'], 500);
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
                'otp' => 'required|string|size:6',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Données invalides',
                    'errors' => $validator->errors()
                ], 422);
            }

            $cacheKey = 'client_delete_otp:' . $user->id;
            $storedOtp = Cache::get($cacheKey);

            if (!$storedOtp || !hash_equals((string) $storedOtp, (string) $request->input('otp'))) {
                return response()->json(['message' => 'Code OTP invalide ou expiré'], 400);
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

            Cache::forget($cacheKey);

            return response()->json(['message' => 'Compte supprimé avec succès']);
        } catch (\Exception $e) {
            Log::error('[ClientProfileController@deleteAccount] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la suppression du compte'], 500);
        }
    }

    public function requestDeleteOtp(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $otp = (string) random_int(100000, 999999);
            $cacheKey = 'client_delete_otp:' . $user->id;

            Cache::put($cacheKey, $otp, now()->addMinutes(10));

            try {
                Mail::raw(
                    "Votre code OTP de suppression de compte est : {$otp}\nCe code expire dans 10 minutes.",
                    function ($message) use ($user, $request) {
                        $message->to($user->email)->subject('Code OTP de suppression de compte');
                        app(ShopMailFromResolver::class)->applyTo(function (string $address, string $name) use ($message) {
                            $message->from($address, $name);
                        }, null, $request);
                    }
                );
            } catch (\Throwable $mailError) {
                Log::warning('[ClientProfileController@requestDeleteOtp] Mail send failed', ['message' => $mailError->getMessage()]);
            }

            return response()->json(['message' => 'Code OTP envoyé par email']);
        } catch (\Exception $e) {
            Log::error('[ClientProfileController@requestDeleteOtp] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de l\'envoi du code OTP'], 500);
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
