<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Card;
use App\Models\CardCredit;
use App\Models\Discount;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ClientPointsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Récupérer la carte du client
            $card = Card::where('user_id', $user->id)
                           ->with(['cardType', 'entity'])
                           ->first();

            if (!$card) {
                return response()->json([
                    'message' => 'Aucune carte de fidélité trouvée',
                    'data' => null
                ], 404);
            }

            // Statistiques des points
            $totalEarned = CardCredit::where('card_id', $card->id)
                                       ->where('type', 'earned')
                                       ->sum('amount');
            
            $totalSpent = CardCredit::where('card_id', $card->id)
                                      ->where('type', 'spent')
                                      ->sum('amount');

            // Historique récent des transactions
            $recentTransactions = CardCredit::where('card_id', $card->id)
                                           ->with(['order'])
                                           ->orderBy('created_at', 'desc')
                                           ->limit(10)
                                           ->get()
                                           ->map(function ($transaction) {
                                               return [
                                                   'id' => $transaction->id,
                                                   'type' => $transaction->type,
                                                   'amount' => $transaction->amount,
                                                   'description' => $transaction->description,
                                                   'date' => Carbon::parse($transaction->created_at)->format('d M Y, H:i'),
                                                   'order_id' => $transaction->order_id,
                                                   'order_reference' => $transaction->order ? $transaction->order->reference : null,
                                               ];
                                           });

            // Promotions disponibles
            $availablePromotions = Discount::where('card_id', $card->id)
                                        ->where('status', 'active')
                                        ->where('expiration', '>', now())
                                        ->get()
                                        ->map(function ($discount) {
                                            return [
                                                'id' => $discount->id,
                                                'title' => $this->getPromotionTitle($discount),
                                                'description' => $this->getPromotionDescription($discount),
                                                'discount_type' => $discount->discount_type,
                                                'discount_value' => $discount->discount_value,
                                                'discount_amount' => $discount->discount_amount,
                                                'expiration' => Carbon::parse($discount->expiration)->format('d M Y'),
                                                'points_required' => $this->calculatePointsRequired($discount),
                                            ];
                                        });

            $pointsData = [
                'current_balance' => $card->points,
                'total_earned' => $totalEarned,
                'total_spent' => $totalSpent,
                'card_type' => $card->cardType ? [
                    'name' => $card->cardType->name,
                    'discount' => $card->cardType->discount,
                ] : null,
                'entity' => $card->entity ? [
                    'name' => $card->entity->name,
                    'logo' => $card->entity->logo,
                ] : null,
                'recent_transactions' => $recentTransactions,
                'available_promotions' => $availablePromotions,
                'next_level' => $this->getNextLevel($card->points),
            ];

            return response()->json(['data' => $pointsData]);
        } catch (\Exception $e) {
            Log::error('[ClientPointsController@index] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement des points'], 500);
        }
    }

    public function getRewards(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $card = Card::where('user_id', $user->id)
                           ->with(['cardType'])
                           ->first();

            if (!$card) {
                return response()->json(['message' => 'Aucune carte trouvée'], 404);
            }

            // Récompenses disponibles selon le niveau de points
            $rewards = $this->getAvailableRewards($card->points, $card->cardType);

            return response()->json(['data' => $rewards]);
        } catch (\Exception $e) {
            Log::error('[ClientPointsController@getRewards] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement des récompenses'], 500);
        }
    }

    public function redeemReward(Request $request, $id): JsonResponse
    {
        try {
            $user = $request->user();
            
            $card = Card::where('user_id', $user->id)->first();
            $discount = Discount::find($id);

            if (!$card || !$discount) {
                return response()->json(['message' => 'Carte ou récompense non trouvée'], 404);
            }

            if ($discount->card_id !== $card->id) {
                return response()->json(['message' => 'Récompense non disponible pour cette carte'], 403);
            }

            if ($card->points < $this->calculatePointsRequired($discount)) {
                return response()->json(['message' => 'Points insuffisants'], 400);
            }

            // Déduire les points
            $card->decrement('points', $this->calculatePointsRequired($discount));

            // Marquer la récompense comme utilisée
            $discount->update(['status' => 'used']);

            return response()->json([
                'message' => 'Récompense échangée avec succès',
                'data' => [
                    'points_deducted' => $this->calculatePointsRequired($discount),
                    'new_balance' => $card->fresh()->points,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('[ClientPointsController@redeemReward] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de l\'échange de récompense'], 500);
        }
    }

    private function getPromotionTitle(Discount $discount): string
    {
        if ($discount->discount_type === 'percentage') {
            return "Réduction de {$discount->discount_value}%";
        } else {
            return "Réduction de {$discount->discount_amount} FCFA";
        }
    }

    private function getPromotionDescription(Discount $discount): string
    {
        if ($discount->discount_type === 'percentage') {
            return "Bénéficiez de {$discount->discount_value}% de réduction sur votre prochain achat.";
        } else {
            return "Bénéficiez de {$discount->discount_amount} FCFA de réduction sur votre prochain achat.";
        }
    }

    private function calculatePointsRequired(Discount $discount): int
    {
        // Calculer les points nécessaires (1000 FCFA = 1 point)
        if ($discount->discount_type === 'percentage') {
            return 100; // 10% de réduction = 100 points
        } else {
            return ceil($discount->discount_amount / 1000);
        }
    }

    private function getNextLevel(int $currentPoints): ?array
    {
        if ($currentPoints < 500) {
            return ['name' => 'Silver', 'points_needed' => 500 - $currentPoints, 'discount' => 10];
        } elseif ($currentPoints < 1500) {
            return ['name' => 'Gold', 'points_needed' => 1500 - $currentPoints, 'discount' => 15];
        }
        
        return null; // Déjà au niveau maximum
    }

    private function getAvailableRewards(int $points, $cardType): array
    {
        $rewards = [];

        // Récompenses de base disponibles pour tous
        $rewards[] = [
            'id' => 'basic_10',
            'title' => 'Réduction 10%',
            'description' => '10% de réduction sur votre prochain achat',
            'points_required' => 100,
            'available' => $points >= 100,
        ];

        $rewards[] = [
            'id' => 'basic_20',
            'title' => 'Réduction 2000 FCFA',
            'description' => '2000 FCFA de réduction sur votre prochain achat',
            'points_required' => 200,
            'available' => $points >= 200,
        ];

        // Récompenses supplémentaires selon le type de carte
        if ($cardType && $cardType->name === 'Silver') {
            $rewards[] = [
                'id' => 'silver_special',
                'title' => 'Produit offert',
                'description' => 'Un produit gratuit jusqu\'à 5000 FCFA',
                'points_required' => 500,
                'available' => $points >= 500,
            ];
        }

        if ($cardType && $cardType->name === 'Gold') {
            $rewards[] = [
                'id' => 'gold_vip',
                'title' => 'Service VIP',
                'description' => 'Consultation prioritaire et livraison gratuite',
                'points_required' => 1000,
                'available' => $points >= 1000,
            ];
        }

        return $rewards;
    }
}
