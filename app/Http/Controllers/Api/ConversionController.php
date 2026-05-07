<?php

namespace App\Http\Controllers\Api;

use App\Models\Card;
use App\Models\Reward;
use App\Models\CardCredit;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ConversionController extends Controller
{
    /**
     * Liste des conversions (CardCredit de type redeemed).
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = CardCredit::with(['card.user', 'card.cardType'])
                ->where('type', 'redeemed')
                ->orderBy('created_at', 'desc');

            if ($request->search) {
                $search = $request->search;
                $query->whereHas('card.user', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $perPage = $request->get('per_page', 20);
            $items = $query->paginate($perPage);

            $data = collect($items->items())->map(function ($cc) {
                return [
                    'id'             => $cc->id,
                    'points_deducted'=> abs($cc->points ?? $cc->credit ?? 0),
                    'description'    => $cc->description,
                    'reward_id'      => $cc->reward_id,
                    'created_at'     => $cc->created_at,
                    'client' => $cc->card && $cc->card->user ? [
                        'id'        => $cc->card->user->id,
                        'name'      => $cc->card->user->name,
                        'email'     => $cc->card->user->email,
                        'phone'     => $cc->card->user->phone,
                    ] : null,
                    'card' => $cc->card ? [
                        'reference' => $cc->card->reference,
                        'points'    => $cc->card->points,
                    ] : null,
                ];
            });

            return response()->json([
                'data' => $data,
                'meta' => [
                    'current_page' => $items->currentPage(),
                    'last_page'    => $items->lastPage(),
                    'total'        => $items->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('[ConversionController@index] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement des conversions'], 500);
        }
    }

    /**
     * Enregistrer une conversion (échange de points contre une récompense).
     * Body: { card_reference: string, reward_id: int }
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'card_reference' => 'required|string',
                'reward_id'      => 'required|integer|exists:rewards,id',
            ]);

            return DB::transaction(function () use ($validated) {
                // Trouver la carte par référence
                $card = Card::with('user')
                    ->where('reference', $validated['card_reference'])
                    ->firstOrFail();

                // Trouver la récompense
                $reward = Reward::findOrFail($validated['reward_id']);

                if ($reward->status !== 'active') {
                    throw ValidationException::withMessages([
                        'reward_id' => ['Cette récompense n\'est plus disponible'],
                    ]);
                }

                // Vérifier les points suffisants
                if ($card->points < $reward->points_required) {
                    throw ValidationException::withMessages([
                        'card_reference' => ['Points insuffisants sur cette carte'],
                    ]);
                }

                // Vérifier le stock
                if ($reward->stock !== null && $reward->stock <= 0) {
                    throw ValidationException::withMessages([
                        'reward_id' => ['Stock épuisé pour cette récompense'],
                    ]);
                }

                // Déduire les points (colonne credit)
                $card->decrement('credit', $reward->points_required);

                // Créer l'entrée dans l'historique
                $cc = CardCredit::create([
                    'card_id'     => $card->id,
                    'reward_id'   => $reward->id,
                    'points'      => -$reward->points_required,
                    'credit'      => -$reward->points_required,
                    'type'        => 'redeemed',
                    'description' => "Conversion : {$reward->name}",
                ]);

                // Diminuer le stock si limité
                if ($reward->stock !== null) {
                    $reward->decrement('stock');
                }

                Log::info('[ConversionController@store] Conversion created', [
                    'card_id'   => $card->id,
                    'reward_id' => $reward->id,
                    'points'    => $reward->points_required,
                ]);

                return response()->json([
                    'message' => 'Conversion enregistrée avec succès',
                    'data'    => [
                        'id'             => $cc->id,
                        'reward'         => $reward,
                        'points_deducted'=> $reward->points_required,
                        'new_balance'    => $card->fresh()->points,
                        'client'         => [
                            'id'    => $card->user->id,
                            'name'  => $card->user->name,
                            'email' => $card->user->email,
                        ],
                    ],
                ], 201);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[ConversionController@store] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la conversion'], 500);
        }
    }

    /**
     * Statistiques rapides des conversions.
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = [
                'total_conversions'       => CardCredit::where('type', 'redeemed')->count(),
                'total_points_converted'  => abs(CardCredit::where('type', 'redeemed')->sum('points')),
                'conversions_this_month'  => CardCredit::where('type', 'redeemed')
                    ->where('created_at', '>=', now()->startOfMonth())
                    ->count(),
            ];

            return response()->json(['data' => $stats]);
        } catch (\Exception $e) {
            Log::error('[ConversionController@stats] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur'], 500);
        }
    }
}
