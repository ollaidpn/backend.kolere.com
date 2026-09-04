<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Card;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Services\FileUploadService;
use App\Services\ShopMailFromResolver;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ClientController extends Controller
{
    private function entityId(Request $request): ?int
    {
        return $request->attributes->get('current_entity_id');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $entityId = $this->entityId($request);
            $query = User::query();
            
            // Recherche par nom ou email
            if ($request->search) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            if ($entityId) {
                $query->whereHas('card', fn ($cardQuery) => $cardQuery->where('entity_id', $entityId));
            }
            
            // Pagination
            $perPage = $request->get('per_page', 15);
            $clients = $query->with('card')
                           ->orderBy('created_at', 'desc')
                           ->paginate($perPage);

            $items = collect($clients->items())->map(function ($client) {
                $client->points = $client->card ? ($client->card->points ?? $client->card->credit ?? 0) : 0;
                $client->card_reference = $client->card ? $client->card->reference : null;
                $client->card_status = $client->card ? $client->card->status : 'inactive';
                return $client;
            });

            return response()->json([
                'data' => $items,
                'meta' => [
                    'current_page' => $clients->currentPage(),
                    'last_page' => $clients->lastPage(),
                    'per_page' => $clients->perPage(),
                    'total' => $clients->total(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('[ClientController@index] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement des clients'], 500);
        }
    }

    private function findClientByIdOrRef($id, ?int $entityId = null): ?User
    {
        $query = User::with(['card.cardCredits', 'orders']);
        if ($entityId) {
            $query->whereHas('card', fn ($cq) => $cq->where('entity_id', $entityId));
        }

        if (is_numeric($id)) {
            $client = (clone $query)->where('id', $id)->first();
            if ($client) return $client;
        }

        $client = (clone $query)->whereHas('card', fn ($cq) => $cq->where('reference', $id))->first();
        if ($client) return $client;

        $cardQuery = Card::where('reference', $id);
        if ($entityId) {
            $cardQuery->where('entity_id', $entityId);
        }
        $cardObj = $cardQuery->first();
        if ($cardObj && $cardObj->user_id) {
            return User::with(['card.cardCredits', 'orders'])->find($cardObj->user_id);
        }

        return null;
    }

    public function show($id): JsonResponse
    {
        try {
            $entityId = $this->entityId(request());
            
            // 1. Chercher la carte par sa référence si $id n'est pas numérique
            $cardObj = null;
            if (!is_numeric($id)) {
                $cardQuery = Card::with(['cardCredits', 'user']);
                if ($entityId) {
                    $cardQuery->where('entity_id', $entityId);
                }
                $cardObj = $cardQuery->where('reference', $id)->first();
            }

            // 2. Récupérer le client correspondant
            $client = null;
            if ($cardObj && $cardObj->user) {
                $client = $cardObj->user;
                $client->load('orders');
            } else {
                $query = User::with(['card.cardCredits', 'orders']);
                if ($entityId) {
                    $query->whereHas('card', fn ($cq) => $cq->where('entity_id', $entityId));
                }
                if (is_numeric($id)) {
                    $client = $query->where('id', $id)->first();
                } else {
                    $client = $query->whereHas('card', fn ($cq) => $cq->where('reference', $id))->first();
                }
            }

            if (!$client) {
                return response()->json(['message' => 'Client non trouvé'], 404);
            }

            // 3. S'assurer que la carte associée dans les données de retour correspond bien à la carte demandée
            if (!$cardObj) {
                $cardQuery = Card::with('cardCredits')->where('user_id', $client->id);
                if ($entityId) {
                    $cardQuery->where('entity_id', $entityId);
                }
                $cardObj = $cardQuery->first();
            }

            $data = $client->toArray();
            if ($cardObj) {
                $data['card'] = $cardObj->toArray();
            }

            // 4. Charger la fiche de santé liée à cette carte
            $health = null;
            if ($cardObj) {
                $healthQuery = \App\Models\UserHealth::query();
                if (Schema::hasColumn('user_health', 'card_id')) {
                    $healthQuery->where('card_id', $cardObj->id);
                } else {
                    $healthQuery->where('user_id', $client->id);
                }
                $health = $healthQuery->first();
            }
            if (!$health) {
                $health = \App\Models\UserHealth::where('user_id', $client->id)->first();
            }
            $data['health'] = $health;

            if ($client->avatar) {
                if (str_starts_with($client->avatar, 'http')) {
                    $data['avatar_url'] = $client->avatar;
                } elseif (Schema::hasColumn('users', 'avatar')) {
                    try {
                        $fileService = new FileUploadService();
                        $data['avatar_url'] = $fileService->getUrl($client->avatar);
                    } catch (\Throwable $avatarError) {
                        Log::warning('[ClientController@show] Avatar resolution fallback', [
                            'id' => $id,
                            'message' => $avatarError->getMessage(),
                        ]);
                        $data['avatar_url'] = $client->avatar;
                    }
                } else {
                    $data['avatar_url'] = $client->avatar;
                }
            } else {
                $data['avatar_url'] = null;
            }

            return response()->json(['data' => $data]);
        } catch (\Exception $e) {
            Log::error('[ClientController@show] Error', ['id' => $id, 'message' => $e->getMessage()]);
            return response()->json(['message' => 'Client non trouvé'], 404);
        }
    }

    public function updateHealth(Request $request, $id): JsonResponse
    {
        try {
            $entityId = $this->entityId($request);
            $client = $this->findClientByIdOrRef($id, $entityId);
            if (!$client) {
                return response()->json(['message' => 'Client non trouvé'], 404);
            }
            $user = $client;
            $cardQuery = Card::where('user_id', $user->id);
            if ($entityId) {
                $cardQuery->where('entity_id', $entityId);
            }
            $cardObj = $cardQuery->first();

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

            $health = null;
            if ($cardObj) {
                $healthQuery = \App\Models\UserHealth::query();
                if (Schema::hasColumn('user_health', 'card_id')) {
                    $healthQuery->where('card_id', $cardObj->id);
                } else {
                    $healthQuery->where('user_id', $user->id);
                }
                $health = $healthQuery->first();
            }
            if (!$health) {
                $health = \App\Models\UserHealth::where('user_id', $user->id)->first();
            }

            if (!$health) {
                $health = new \App\Models\UserHealth();
                $health->user_id = $user->id;
                if ($cardObj && Schema::hasColumn('user_health', 'card_id')) {
                    $health->card_id = $cardObj->id;
                }
            }

            if ($cardObj && Schema::hasColumn('user_health', 'card_id') && !$health->card_id) {
                $health->card_id = $cardObj->id;
            }

            $health->fill($validated);
            $health->save();

            return response()->json([
                'message' => 'Fiche de santé enregistrée',
                'data' => $health,
            ]);
        } catch (\Exception $e) {
            Log::error('[ClientController@updateHealth] Error', ['id' => $id, 'message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de l\'enregistrement de la santé : ' . $e->getMessage()], 500);
        }
    }

    public function checkAvailability(Request $request): JsonResponse
    {
        try {
            $type = $request->input('type'); // 'email' ou 'phone'
            $value = $request->input('value');
            $ccphone = $request->input('ccphone', '+221');
            $entityId = $this->entityId($request);

            if (!$entityId) {
                return response()->json(['message' => 'Entité courante introuvable'], 422);
            }

            if ($type === 'email') {
                if (!$value || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return response()->json(['message' => 'Email invalide'], 422);
                }
                $existingUser = User::where('email', $value)->first();
            } else {
                if (!$value) {
                    return response()->json(['message' => 'Numéro de téléphone obligatoire'], 422);
                }
                $cleanValue = preg_replace('/\D/', '', $value);
                $existingUser = User::where(function ($q) use ($value, $cleanValue, $ccphone) {
                    $q->where('phone', $value)
                      ->orWhere('phone', $cleanValue);
                    if ($ccphone) {
                        $full = preg_replace('/\D/', '', $ccphone . $value);
                        $q->orWhere('phone', $full);
                    }
                })->first();
            }

            if ($existingUser) {
                // 1. Si le client est déjà lié à une carte de CETTE boutique
                $hasCardInThisShop = Card::where('user_id', $existingUser->id)
                    ->where('entity_id', $entityId)
                    ->exists();

                if ($hasCardInThisShop) {
                    $fieldLabel = $type === 'email' ? "cette adresse e-mail" : "ce numéro de téléphone";
                    return response()->json([
                        'exists' => true,
                        'has_card' => true,
                        'message' => "Un utilisateur possède déjà {$fieldLabel} sur cette boutique. Veuillez choisir un autre " . ($type === 'email' ? 'e-mail' : 'numéro de téléphone') . ".",
                        'user' => [
                            'name' => $existingUser->name,
                            'email' => $existingUser->email,
                            'phone' => $existingUser->phone,
                            'ccphone' => $existingUser->ccphone,
                        ]
                    ], 422);
                }

                // 2. Le client existe sur la plateforme globale mais n'a pas de carte sur CETTE boutique
                return response()->json([
                    'exists' => true,
                    'has_card' => false,
                    'message' => 'Client trouvé sur la plateforme. Une nouvelle carte pour votre boutique lui sera attribuée.',
                    'user' => [
                        'name' => $existingUser->name,
                        'email' => $existingUser->email,
                        'phone' => $existingUser->phone,
                        'ccphone' => $existingUser->ccphone,
                    ]
                ]);
            }

            return response()->json([
                'exists' => false,
                'has_card' => false,
                'message' => 'Nouveau client'
            ]);
        } catch (\Exception $e) {
            Log::error('[ClientController@checkAvailability] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la vérification'], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            // Validation des entrées
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'nullable|email',
                'ccphone' => 'nullable|string|max:10',
                'phone' => 'required|string|max:20',
                'address' => 'nullable|string|max:500',
                'password' => 'nullable|string|min:6',
                'card_id' => 'nullable|exists:cards,id',
            ]);

            $entityId = $this->entityId($request);
            if (!$entityId) {
                return response()->json(['message' => 'Entité courante introuvable'], 422);
            }

            $ccphone = $validated['ccphone'] ?? '+221';
            $phone = $validated['phone'];
            $email = $validated['email'] ?? null;

            // 1. Chercher si un utilisateur existe déjà soit par email (si renseigné) soit par (ccphone + phone)
            $existingUser = User::where(function ($q) use ($ccphone, $phone, $email) {
                $q->where(function ($phoneQ) use ($ccphone, $phone) {
                    $phoneQ->where('phone', $phone);
                    if ($ccphone) {
                        $phoneQ->where('ccphone', $ccphone);
                    }
                });
                if (!empty($email)) {
                    $q->orWhere('email', $email);
                }
            })->first();

            if ($existingUser) {
                // Vérifier s'il a DÉJÀ une carte sur cette boutique (entity_id)
                $hasCardInThisShop = Card::where('user_id', $existingUser->id)
                    ->where('entity_id', $entityId)
                    ->exists();

                if ($hasCardInThisShop) {
                    return response()->json([
                        'message' => 'Ce client possède déjà un compte et une carte de fidélité active sur cette boutique.',
                        'errors' => [
                            'phone' => ['Ce client possède déjà une carte sur cette boutique.']
                        ]
                    ], 422);
                }

                // Le client existe sur la plateforme mais n'a pas encore de carte sur cette boutique => On réutilise cet utilisateur
                $client = $existingUser;
                // Mettre à jour les infos si fournies
                $client->update(array_filter([
                    'name' => $validated['name'] ?: $client->name,
                    'address' => $validated['address'] ?: $client->address,
                ]));
            } else {
                // Le client n'existe pas du tout => Création d'un nouvel utilisateur
                $dummyEmail = 'client_' . time() . '_' . rand(100, 999) . '@boutique.local';
                $client = User::create([
                    'name' => $validated['name'],
                    'email' => $email ?: $dummyEmail,
                    'ccphone' => $ccphone,
                    'phone' => $phone,
                    'address' => $validated['address'] ?? null,
                    'password' => $validated['password'] ? Hash::make($validated['password']) : Hash::make('password123'),
                ]);
            }
            $entity = \App\Models\Entity::find($entityId);

            // Liaison de la carte de fidélité (Physique sélectionnée OU Virtuelle automatique)
            if (!empty($validated['card_id'])) {
                $card = Card::where('entity_id', $entityId)
                    ->where('id', $validated['card_id'])
                    ->whereNull('user_id')
                    ->first();

                if ($card) {
                    $card->update([
                        'user_id' => $client->id,
                        'status' => 'active',
                    ]);
                } else {
                    // Si carte introuvable ou déjà prise, fallback création automatique
                    $card = Card::create([
                        'user_id' => $client->id,
                        'entity_id' => $entityId,
                        'card_type_id' => 1,
                        'credit' => 0,
                        'status' => 'active',
                    ]);
                }
            } else {
                // Créer la carte de fidélité virtuelle automatique par défaut
                $card = Card::create([
                    'user_id' => $client->id,
                    'entity_id' => $entityId,
                    'card_type_id' => 1,
                    'credit' => 0,
                    'status' => 'active',
                ]);
            }

            $cardRef = $card->reference ?? $card->number;
            $shopName = $entity ? $entity->name : 'votre boutique';


            // 1. Envoi de l'Email de bienvenue (Automatique)
            if ($client->email && !str_contains($client->email, '@boutique.local')) {
                try {
                    $subject = "Bienvenue chez {$shopName} ! 🎁";
                    Mail::send('emails.welcome_client', [
                        'client' => $client,
                        'shopName' => $shopName,
                        'cardRef' => $cardRef,
                    ], function ($msg) use ($client, $subject) {
                        $msg->to($client->email)->subject($subject);
                    });
                } catch (\Throwable $mailErr) {
                    Log::warning('[ClientController@store] Échec envoi email de bienvenue', ['error' => $mailErr->getMessage()]);
                }
            }

            // 2. Envoi du SMS de bienvenue (si option coché)
            if ($request->boolean('send_sms') && $client->phone) {
                $pubKey = $entity?->diotko_public_key ?: env('DIOTKO_SMS_PUBLIC_KEY');
                $secKey = $entity?->diotko_secret_key ?: env('DIOTKO_SMS_SECRET_KEY');

                if ($pubKey && $secKey) {
                    $cc = $request->input('ccphone', '+221');
                    $fullPhone = $cc . preg_replace('/^\+221/', '', $client->phone);

                    // SMS <= 160 caractères
                    $smsMessage = "Bienvenue sur {$shopName}. Votre carte {$cardRef} est activée ! Téléchargez l'appli mobile {$shopName} sur Playstore/Appstore pour vos cadeaux. Présentez votre carte à chaque achat.";
                    
                    if (mb_strlen($smsMessage) > 160) {
                        $smsMessage = mb_substr($smsMessage, 0, 160);
                    }

                    try {
                        $smsService = new \App\Services\NotificationsService($pubKey, $secKey);
                        $smsService->sendSmsNow([$fullPhone], $smsMessage);
                        Log::info("[ClientController@store] SMS de bienvenue envoyé à {$fullPhone}");
                    } catch (\Throwable $sErr) {
                        Log::error('[ClientController@store] Échec envoi SMS de bienvenue', ['error' => $sErr->getMessage()]);
                    }
                }
            }

            Log::info('[ClientController@store] Client created', ['client_id' => $client->id]);

            return response()->json([
                'message' => 'Client créé avec succès',
                'data' => $client->load('card')
            ], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[ClientController@store] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la création du client'], 500);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $query = User::query();
            if ($entityId = $this->entityId($request)) {
                $query->whereHas('card', fn ($cardQuery) => $cardQuery->where('entity_id', $entityId));
            }
            $client = $query->findOrFail($id);
            
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email,' . $id,
                'phone' => 'required|string|max:20',
                'address' => 'required|string|max:500',
            ]);

            $client->update($validated);

            Log::info('[ClientController@update] Client updated', ['client_id' => $client->id]);

            return response()->json([
                'message' => 'Client modifié avec succès',
                'data' => $client
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[ClientController@update] Error', ['id' => $id, 'message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la modification du client'], 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $query = User::query();
            if ($entityId = request()->attributes->get('current_entity_id')) {
                $query->whereHas('card', fn ($cardQuery) => $cardQuery->where('entity_id', $entityId));
            }
            $client = $query->findOrFail($id);
            
            // Vérifier si le client a des commandes
            if ($client->orders()->count() > 0) {
                return response()->json([
                    'message' => 'Impossible de supprimer ce client car il a des commandes associées'
                ], 422);
            }

            // Supprimer la carte associée
            if ($client->card) {
                $client->card->delete();
            }

            $client->delete();

            Log::info('[ClientController@destroy] Client deleted', ['client_id' => $id]);

            return response()->json(['message' => 'Client supprimé avec succès']);
        } catch (\Exception $e) {
            Log::error('[ClientController@destroy] Error', ['id' => $id, 'message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la suppression du client'], 500);
        }
    }

    public function getStats(): JsonResponse
    {
        try {
            $entityId = request()->attributes->get('current_entity_id');

            $clientsQuery = User::query();
            if ($entityId) {
                $clientsQuery->whereHas('card', fn ($cardQuery) => $cardQuery->where('entity_id', $entityId));
            }

            $cardsQuery = Card::query();
            if ($entityId) {
                $cardsQuery->where('entity_id', $entityId);
            }

            $stats = [
                'total_clients' => $clientsQuery->count(),
                'active_clients' => (clone $clientsQuery)->whereHas('card', function($query) use ($entityId) {
                    $query->where('status', 'active');
                    if ($entityId) {
                        $query->where('entity_id', $entityId);
                    }
                })->count(),
                'total_points' => $cardsQuery->sum('credit'),
                'new_this_month' => (clone $clientsQuery)->whereMonth('created_at', now()->month)
                                       ->whereYear('created_at', now()->year)
                                       ->count(),
            ];

            return response()->json(['data' => $stats]);
        } catch (\Exception $e) {
            Log::error('[ClientController@getStats] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement des statistiques'], 500);
        }
    }
}
