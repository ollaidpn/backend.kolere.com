<?php

namespace App\Http\Controllers\Api;

use App\Models\Card;
use App\Models\User;
use App\Models\CardCredit;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CardController extends Controller
{
    private function entityId(Request $request): ?int
    {
        return $request->attributes->get('current_entity_id');
    }

    public function scanByReference(Request $request, string $reference): JsonResponse
    {
        try {
            $query = Card::with(['user', 'cardType'])->where('reference', $reference);
            if ($entityId = $this->entityId($request)) {
                $query->where('entity_id', $entityId);
            }
            $card = $query->firstOrFail();

            return response()->json([
                'data' => [
                    'id'         => $card->id,
                    'reference'  => $card->reference,
                    'points'     => $card->points,
                    'status'     => $card->status,
                    'card_type'  => $card->cardType
                        ? ['name' => $card->cardType->name, 'discount' => $card->cardType->discount]
                        : null,
                    'client'     => [
                        'id'    => $card->user->id,
                        'name'  => $card->user->name,
                        'email' => $card->user->email,
                        'phone' => $card->user->phone,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('[CardController@scanByReference] Error', ['ref' => $reference, 'message' => $e->getMessage()]);
            return response()->json(['message' => 'Carte introuvable'], 404);
        }
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $query = Card::with(['user', 'cardType', 'cardCredits']);
            if ($entityId = $this->entityId($request)) {
                $query->where('entity_id', $entityId);
            }
            
            // Filtrer par statut
            if ($request->status) {
                $query->where('status', $request->status);
            }
            
            // Filtrer par client
            if ($request->user_id) {
                $query->where('user_id', $request->user_id);
            }
            
            // Pagination
            $perPage = $request->get('per_page', 15);
            $cards = $query->orderBy('created_at', 'desc')
                          ->paginate($perPage);

            return response()->json([
                'data' => $cards->items(),
                'meta' => [
                    'current_page' => $cards->currentPage(),
                    'last_page' => $cards->lastPage(),
                    'per_page' => $cards->perPage(),
                    'total' => $cards->total(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('[CardController@index] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement des cartes'], 500);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $query = Card::with(['user', 'cardType', 'cardCredits', 'orders']);
            if ($entityId = request()->attributes->get('current_entity_id')) {
                $query->where('entity_id', $entityId);
            }
            $card = $query->findOrFail($id);
            
            return response()->json(['data' => $card]);
        } catch (\Exception $e) {
            Log::error('[CardController@show] Error', ['id' => $id, 'message' => $e->getMessage()]);
            return response()->json(['message' => 'Carte non trouvée'], 404);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'card_type_id' => 'required|exists:card_types,id',
                'initial_points' => 'nullable|integer|min:0',
            ]);

            return DB::transaction(function () use ($validated, $request) {
                // Vérifier si l'utilisateur n'a pas déjà une carte
                $existingCardQuery = Card::where('user_id', $validated['user_id']);
                if ($entityId = $this->entityId($request)) {
                    $existingCardQuery->where('entity_id', $entityId);
                }
                $existingCard = $existingCardQuery->first();
                if ($existingCard) {
                    throw ValidationException::withMessages([
                        'user_id' => ['Cet utilisateur possède déjà une carte de fidélité']
                    ]);
                }

                $entityId = $this->entityId($request);
                if (!$entityId) {
                    throw ValidationException::withMessages([
                        'entity_id' => ['Entité courante introuvable']
                    ]);
                }

                $card = Card::create([
                    'user_id' => $validated['user_id'],
                    'entity_id' => $entityId,
                    'card_type_id' => $validated['card_type_id'],
                    'number' => 'CARD-' . str_pad($validated['user_id'], 8, '0', STR_PAD_LEFT),
                    'points' => $validated['initial_points'] ?? 0,
                    'status' => 'active',
                ]);

                // Créer un crédit de points si des points initiaux sont fournis
                if (($validated['initial_points'] ?? 0) > 0) {
                    CardCredit::create([
                        'entity_id' => $card->entity_id,
                        'card_id' => $card->id,
                        'points' => $validated['initial_points'],
                        'type' => 'initial',
                        'description' => 'Points initiaux',
                    ]);
                }

                Log::info('[CardController@store] Card created', [
                    'card_id' => $card->id,
                    'user_id' => $validated['user_id']
                ]);

                return response()->json([
                    'message' => 'Carte créée avec succès',
                    'data' => $card->load(['user', 'cardType'])
                ], 201);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[CardController@store] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la création de la carte'], 500);
        }
    }

    public function addPoints(Request $request, $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'points' => 'required|integer|min:1',
                'description' => 'required|string|max:255',
                'type' => 'required|in:manual,bonus,refund',
            ]);

            return DB::transaction(function () use ($validated, $id) {
                $query = Card::query()->whereKey($id);
                if ($entityId = request()->attributes->get('current_entity_id')) {
                    $query->where('entity_id', $entityId);
                }
                $card = $query->firstOrFail();
                
                $card->increment('credit', $validated['points']);

                // Créer un crédit de points
                CardCredit::create([
                    'entity_id' => $card->entity_id,
                    'card_id' => $card->id,
                    'points' => $validated['points'],
                    'credit' => $validated['points'],
                    'type' => $validated['type'],
                    'description' => $validated['description'],
                ]);

                Log::info('[CardController@addPoints] Points added', [
                    'card_id' => $card->id,
                    'points' => $validated['points'],
                    'type' => $validated['type']
                ]);

                return response()->json([
                    'message' => 'Points ajoutés avec succès',
                    'data' => $card->fresh()
                ]);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[CardController@addPoints] Error', ['id' => $id, 'message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de l\'ajout des points'], 500);
        }
    }

    public function redeemPoints(Request $request, $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'points' => 'required|integer|min:1',
                'description' => 'required|string|max:255',
                'reward_id' => 'nullable|integer',
            ]);

            return DB::transaction(function () use ($validated, $id) {
                $query = Card::query()->whereKey($id);
                if ($entityId = request()->attributes->get('current_entity_id')) {
                    $query->where('entity_id', $entityId);
                }
                $card = $query->firstOrFail();
                
                // Vérifier si la carte a suffisamment de points
                if ($card->points < $validated['points']) {
                    throw ValidationException::withMessages([
                        'points' => ['Points insuffisants sur cette carte']
                    ]);
                }

                $card->decrement('credit', $validated['points']);

                // Créer un débit de points
                CardCredit::create([
                    'entity_id' => $card->entity_id,
                    'card_id' => $card->id,
                    'points' => -$validated['points'],
                    'credit' => -$validated['points'],
                    'type' => 'redeemed',
                    'description' => $validated['description'],
                    'reward_id' => $validated['reward_id'] ?? null,
                ]);

                Log::info('[CardController@redeemPoints] Points redeemed', [
                    'card_id' => $card->id,
                    'points' => $validated['points'],
                    'description' => $validated['description']
                ]);

                return response()->json([
                    'message' => 'Points échangés avec succès',
                    'data' => $card->fresh()
                ]);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[CardController@redeemPoints] Error', ['id' => $id, 'message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de l\'échange des points'], 500);
        }
    }

    public function getStats(): JsonResponse
    {
        try {
            $entityId = request()->attributes->get('current_entity_id');

            $cardsQuery = Card::query();
            if ($entityId) {
                $cardsQuery->where('entity_id', $entityId);
            }

            $stats = [
                'total_cards' => (clone $cardsQuery)->count(),
                'active_cards' => (clone $cardsQuery)->where('status', 'active')->count(),
                'total_points' => (clone $cardsQuery)->sum('credit'),
                'average_points' => (clone $cardsQuery)->avg('credit'),
                'cards_by_type' => (clone $cardsQuery)->join('card_types', 'cards.card_type_id', '=', 'card_types.id')
                                    ->selectRaw('card_types.name, COUNT(*) as count')
                                    ->groupBy('card_types.name')
                                    ->get(),
            ];

            return response()->json(['data' => $stats]);
        } catch (\Exception $e) {
            Log::error('[CardController@getStats] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement des statistiques'], 500);
        }
    }

    public function getHistory($id): JsonResponse
    {
        try {
            $query = Card::query();
            if ($entityId = request()->attributes->get('current_entity_id')) {
                $query->where('entity_id', $entityId);
            }
            $card = $query->findOrFail($id);

            $creditsQuery = CardCredit::where('card_id', $id);
            if ($entityId = request()->attributes->get('current_entity_id')) {
                $creditsQuery->where('entity_id', $entityId);
            }
            $credits = $creditsQuery
                                ->orderBy('created_at', 'desc')
                                ->get();

            return response()->json(['data' => $credits]);
        } catch (\Exception $e) {
            Log::error('[CardController@getHistory] Error', ['id' => $id, 'message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement de l\'historique'], 500);
        }
    }
}
