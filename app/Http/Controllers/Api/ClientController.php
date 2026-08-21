<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Card;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Services\FileUploadService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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
                $client->points = $client->card ? $client->card->credit : 0;
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

    public function show($id): JsonResponse
    {
        try {
            $query = User::with(['card.cardCredits', 'orders']);
            if ($entityId = request()->attributes->get('current_entity_id')) {
                $query->whereHas('card', fn ($cardQuery) => $cardQuery->where('entity_id', $entityId));
            }
            $client = $query->findOrFail($id);

            $data = $client->toArray();
            $fileService = new FileUploadService();
            if ($client->avatar) {
                $data['avatar_url'] = str_starts_with($client->avatar, 'http')
                    ? $client->avatar
                    : $fileService->getUrl($client->avatar);
            } else {
                $data['avatar_url'] = null;
            }

            return response()->json(['data' => $data]);
        } catch (\Exception $e) {
            Log::error('[ClientController@show] Error', ['id' => $id, 'message' => $e->getMessage()]);
            return response()->json(['message' => 'Client non trouvé'], 404);
        }
    }

    public function store(Request $request): JsonResponse
    {
            // Validation des entrées
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'nullable|email|unique:users,email',
                'phone' => 'required|string|max:20',
                'address' => 'nullable|string|max:500',
                'password' => 'nullable|string|min:6',
                'card_id' => 'nullable|exists:cards,id',
            ]);

            // Créer le client (email par défaut si vide)
            $dummyEmail = 'client_' . time() . '_' . rand(100, 999) . '@boutique.local';
            $client = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'] ?: $dummyEmail,
                'phone' => $validated['phone'],
                'address' => $validated['address'] ?? null,
                'password' => $validated['password'] ? Hash::make($validated['password']) : Hash::make('password123'),
            ]);

            $entityId = $this->entityId($request);
            if (!$entityId) {
                return response()->json(['message' => 'Entité courante introuvable'], 422);
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
                        'number' => 'CARD-' . str_pad($client->id, 8, '0', STR_PAD_LEFT),
                        'points' => 0,
                        'status' => 'active',
                    ]);
                }
            } else {
                // Créer la carte de fidélité virtuelle automatique par défaut
                $card = Card::create([
                    'user_id' => $client->id,
                    'entity_id' => $entityId,
                    'card_type_id' => 1,
                    'number' => 'CARD-' . str_pad($client->id, 8, '0', STR_PAD_LEFT),
                    'points' => 0,
                    'status' => 'active',
                ]);
            }

            $cardRef = $card->reference ?? $card->number;
            $shopName = $entity ? $entity->name : 'votre boutique';


            // 1. Envoi de l'Email de bienvenue (Automatique)
            if ($client->email) {
                try {
                    $subject = "Bienvenue chez {$shopName} ! 🎁";
                    $body = "Bonjour {$client->name},\n\n"
                        . "Votre compte client et votre carte de fidélité chez {$shopName} viennent d'être créés avec succès !\n"
                        . "Référence de votre carte : {$cardRef}\n\n"
                        . "Téléchargez l'application mobile {$shopName} pour suivre vos points et profiter de vos cadeaux !\n\n"
                        . "À très bientôt,\nL'équipe {$shopName}";

                    \Illuminate\Support\Facades\Mail::raw($body, function ($msg) use ($client, $subject, $entity, $shopName) {
                        $msg->to($client->email)->subject($subject);
                        if ($entity && $entity->email) {
                            $msg->from($entity->email, $shopName);
                        }
                    });
                } catch (\Throwable $mErr) {
                    Log::error('[ClientController@store] Échec envoi email de bienvenue', ['error' => $mErr->getMessage()]);
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
