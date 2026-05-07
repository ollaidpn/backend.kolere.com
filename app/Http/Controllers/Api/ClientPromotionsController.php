<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Card;
use App\Models\Discount;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ClientPromotionsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Récupérer la carte du client
            $card = Card::where('user_id', $user->id)
                           ->with(['cardType'])
                           ->first();

            if (!$card) {
                return response()->json([
                    'message' => 'Aucune carte de fidélité trouvée',
                    'data' => []
                ], 404);
            }

            // Promotions actives pour cette carte
            $promotions = Discount::where('card_id', $card->id)
                               ->where('status', 'active')
                               ->where('expiration', '>', now())
                               ->orderBy('created_at', 'desc')
                               ->get()
                               ->map(function ($discount) use ($card) {
                                   return [
                                       'id' => $discount->id,
                                       'title' => $this->getPromotionTitle($discount),
                                       'description' => $this->getPromotionDescription($discount),
                                       'discount_type' => $discount->discount_type,
                                       'discount_value' => $discount->discount_value,
                                       'discount_amount' => $discount->discount_amount,
                                       'expiration' => Carbon::parse($discount->expiration)->format('d M Y'),
                                       'points_required' => $this->calculatePointsRequired($discount),
                                       'can_redeem' => $card->points >= $this->calculatePointsRequired($discount),
                                       'category' => $this->getPromotionCategory($discount),
                                       'terms' => $this->getPromotionTerms($discount),
                                   ];
                               });

            // Promotions générales disponibles pour tous
            $generalPromotions = $this->getGeneralPromotions($card->points);

            return response()->json([
                'data' => [
                    'card_promotions' => $promotions,
                    'general_promotions' => $generalPromotions,
                    'total_available' => $promotions->count() + count($generalPromotions),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('[ClientPromotionsController@index] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement des promotions'], 500);
        }
    }

    public function show(Request $request, $id): JsonResponse
    {
        try {
            $user = $request->user();
            
            $card = Card::where('user_id', $user->id)->first();
            $discount = Discount::find($id);

            if (!$card || !$discount) {
                return response()->json(['message' => 'Promotion non trouvée'], 404);
            }

            if ($discount->card_id !== $card->id) {
                return response()->json(['message' => 'Promotion non accessible'], 403);
            }

            $promotionDetails = [
                'id' => $discount->id,
                'title' => $this->getPromotionTitle($discount),
                'description' => $this->getPromotionDescription($discount),
                'discount_type' => $discount->discount_type,
                'discount_value' => $discount->discount_value,
                'discount_amount' => $discount->discount_amount,
                'expiration' => Carbon::parse($discount->expiration)->format('d M Y'),
                'points_required' => $this->calculatePointsRequired($discount),
                'can_redeem' => $card->points >= $this->calculatePointsRequired($discount),
                'category' => $this->getPromotionCategory($discount),
                'terms' => $this->getPromotionTerms($discount),
                'usage_limit' => $discount->usage_limit ?? null,
                'usage_count' => $discount->usage_count ?? 0,
                'created_at' => $discount->created_at,
                'updated_at' => $discount->updated_at,
            ];

            return response()->json(['data' => $promotionDetails]);
        } catch (\Exception $e) {
            Log::error('[ClientPromotionsController@show] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement de la promotion'], 500);
        }
    }

    public function getFeatured(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $card = Card::where('user_id', $user->id)->first();

            // Promotions mises en avant selon le type de carte
            $featuredPromotions = $this->getFeaturedByCardType($card->cardType->name ?? 'Bronze');

            return response()->json(['data' => $featuredPromotions]);
        } catch (\Exception $e) {
            Log::error('[ClientPromotionsController@getFeatured] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement des promotions vedettes'], 500);
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
            return "Bénéficiez de {$discount->discount_value}% de réduction sur vos achats.";
        } else {
            return "Bénéficiez de {$discount->discount_amount} FCFA de réduction sur vos achats.";
        }
    }

    private function calculatePointsRequired(Discount $discount): int
    {
        if ($discount->discount_type === 'percentage') {
            return $discount->discount_value * 10; // 1% = 10 points
        } else {
            return ceil($discount->discount_amount / 1000); // 1000 FCFA = 1 point
        }
    }

    private function getPromotionCategory(Discount $discount): string
    {
        // Catégoriser selon le type et la valeur
        if ($discount->discount_type === 'percentage') {
            if ($discount->discount_value <= 5) return 'Petite réduction';
            if ($discount->discount_value <= 10) return 'Réduction standard';
            return 'Réduction importante';
        } else {
            if ($discount->discount_amount <= 2000) return 'Petite réduction';
            if ($discount->discount_amount <= 5000) return 'Réduction standard';
            return 'Réduction importante';
        }
    }

    private function getPromotionTerms(Discount $discount): array
    {
        $terms = [
            'Validité' => Carbon::parse($discount->expiration)->format('d/m/Y'),
            'Non cumulable' => 'Cette promotion ne peut être cumulée avec d\'autres offres',
            'Utilisation unique' => $discount->usage_limit ? 'Limitée à une utilisation' : 'Utilisation illimitée',
        ];

        if ($discount->discount_type === 'percentage') {
            $terms['Montant minimum'] = 'Valable pour tout achat supérieur à 1000 FCFA';
        }

        return $terms;
    }

    private function getGeneralPromotions(int $userPoints): array
    {
        $promotions = [];

        // Promotion de bienvenue
        if ($userPoints < 100) {
            $promotions[] = [
                'id' => 'welcome_bonus',
                'title' => 'Bonus de bienvenue',
                'description' => 'Gagnez 50 points supplémentaires sur votre prochain achat',
                'points_required' => 0,
                'category' => 'Bonus',
                'type' => 'points',
                'can_redeem' => true,
            ];
        }

        // Promotion du mois
        $promotions[] = [
            'id' => 'monthly_special',
            'title' => 'Spécial du mois',
            'description' => 'Doublez vos points sur tous les achats ce mois-ci',
            'points_required' => 0,
            'category' => 'Spécial',
            'type' => 'multiplier',
            'can_redeem' => true,
            'valid_until' => Carbon::now()->endOfMonth()->format('d M Y'),
        ];

        return $promotions;
    }

    private function getFeaturedByCardType(string $cardType): array
    {
        $featured = [];

        switch ($cardType) {
            case 'Bronze':
                $featured[] = [
                    'id' => 'bronze_upgrade',
                    'title' => 'Passez Silver',
                    'description' => 'Accédez à 10% de réduction permanente',
                    'points_required' => 500,
                    'category' => 'Amélioration',
                    'type' => 'upgrade',
                ];
                break;

            case 'Silver':
                $featured[] = [
                    'id' => 'silver_upgrade',
                    'title' => 'Passez Gold',
                    'description' => 'Accédez à 15% de réduction permanente',
                    'points_required' => 1000,
                    'category' => 'Amélioration',
                    'type' => 'upgrade',
                ];
                $featured[] = [
                    'id' => 'silver_monthly',
                    'title' => 'Offre exclusive Silver',
                    'description' => 'Produits wellness à -20% ce mois',
                    'points_required' => 200,
                    'category' => 'Exclusivité',
                    'type' => 'special',
                ];
                break;

            case 'Gold':
                $featured[] = [
                    'id' => 'gold_vip',
                    'title' => 'Service VIP Gold',
                    'description' => 'Livraison gratuite et prioritaire',
                    'points_required' => 0,
                    'category' => 'VIP',
                    'type' => 'benefit',
                ];
                $featured[] = [
                    'id' => 'gold_cashback',
                    'title' => 'Cashback Gold',
                    'description' => '5% de cashback sur tous vos achats',
                    'points_required' => 0,
                    'category' => 'Avantage',
                    'type' => 'cashback',
                ];
                break;
        }

        return $featured;
    }
}
