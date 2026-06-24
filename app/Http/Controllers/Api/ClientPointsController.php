<?php

namespace App\Http\Controllers\Api;

use App\Models\Card;
use App\Models\CardCredit;
use App\Models\Reward;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ClientPointsController extends Controller
{
    private function entityId(Request $request): ?int
    {
        return $request->attributes->get('current_entity_id');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $cardQuery = Card::where('user_id', $user->id)->with(['cardType']);
            if ($entityId = $this->entityId($request)) {
                $cardQuery->where('entity_id', $entityId);
            }

            $card = $cardQuery->first();

            if (!$card) {
                return response()->json(['message' => 'Aucune carte de fidélité trouvée', 'data' => null], 404);
            }

            $points = $card->credit ?? 0;

            // Points gagnés et dépensés — résilients si colonne type absente
            $totalEarned = 0;
            $totalSpent  = 0;
            try {
                $totalEarned = (int) CardCredit::where('card_id', $card->id)->where('type', 'earned')->sum('credit');
                $totalSpent  = abs((int) CardCredit::where('card_id', $card->id)->where('type', 'redeemed')->sum('credit'));
            } catch (\Exception $e) {
                $totalEarned = (int) CardCredit::where('card_id', $card->id)->where('credit', '>', 0)->sum('credit');
                $totalSpent  = abs((int) CardCredit::where('card_id', $card->id)->where('credit', '<', 0)->sum('credit'));
            }

            // Historique récent
            $recentTransactions = [];
            try {
                $recentTransactions = CardCredit::where('card_id', $card->id)
                    ->orderBy('created_at', 'desc')
                    ->limit(15)
                    ->get()
                    ->map(function ($t) {
                        $pts   = $t->points ?? $t->credit ?? 0;
                        $type  = $t->type ?? ($pts >= 0 ? 'earned' : 'redeemed');
                        return [
                            'id'              => $t->id,
                            'type'            => $type,
                            'amount'          => $pts,
                            'description'     => $t->description ?? ($pts >= 0 ? 'Points gagnés' : 'Points utilisés'),
                            'date'            => Carbon::parse($t->created_at)->format('d M Y, H:i'),
                            'order_reference' => null,
                        ];
                    })->toArray();
            } catch (\Exception $e) {
                Log::warning('[ClientPointsController] recentTransactions failed: ' . $e->getMessage());
            }

            return response()->json(['data' => [
                'current_balance'      => $points,
                'total_earned'         => $totalEarned,
                'total_spent'          => $totalSpent,
                'card_type'            => $card->cardType ? ['name' => $card->cardType->name, 'discount' => $card->cardType->discount ?? 0] : null,
                'recent_transactions'  => $recentTransactions,
                'available_promotions' => [],
                'next_level'           => $this->getNextLevel($points),
            ]]);
        } catch (\Exception $e) {
            Log::error('[ClientPointsController@index] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement des points'], 500);
        }
    }

    public function getRewards(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $cardQuery = Card::where('user_id', $user->id);
            if ($entityId = $this->entityId($request)) {
                $cardQuery->where('entity_id', $entityId);
            }
            $card = $cardQuery->first();
            $points = $card ? ($card->credit ?? 0) : 0;

            $rewardsQuery = Reward::where('status', 'active');
            if ($entityId = $this->entityId($request)) {
                $rewardsQuery->where('entity_id', $entityId);
            }

            $rewards = $rewardsQuery
                ->orderBy('points_required')
                ->get()
                ->map(fn($r) => [
                    'id'               => (string) $r->id,
                    'title'            => $r->name,
                    'description'      => $r->description ?? '',
                    'points_required'  => $r->points_required,
                    'available'        => $points >= $r->points_required && ($r->stock === null || $r->stock > 0),
                ])->toArray();

            return response()->json(['data' => $rewards]);
        } catch (\Exception $e) {
            Log::error('[ClientPointsController@getRewards] Error', ['message' => $e->getMessage()]);
            return response()->json(['data' => []]);
        }
    }

    public function redeemReward(Request $request, $id): JsonResponse
    {
        try {
            $user   = $request->user();
            $cardQuery = Card::where('user_id', $user->id);
            if ($entityId = $this->entityId($request)) {
                $cardQuery->where('entity_id', $entityId);
            }
            $card = $cardQuery->firstOrFail();

            $rewardQuery = Reward::where('status', 'active');
            if ($entityId = $this->entityId($request)) {
                $rewardQuery->where('entity_id', $entityId);
            }
            $reward = $rewardQuery->findOrFail($id);

            $points = $card->credit ?? 0;
            if ($points < $reward->points_required) {
                return response()->json(['message' => 'Points insuffisants'], 400);
            }

            $card->decrement('credit', $reward->points_required);

            try {
                CardCredit::create([
                    'entity_id' => $card->entity_id,
                    'card_id'     => $card->id,
                    'order_id'    => null,
                    'amount'      => $reward->points_required,
                    'credit'      => -$reward->points_required,
                    'type'        => 'redeemed',
                    'description' => "Échange : {$reward->name}",
                ]);
            } catch (\Exception $e) {
                $cc = new CardCredit();
                $cc->entity_id = $card->entity_id;
                $cc->card_id  = $card->id;
                $cc->order_id = null;
                $cc->amount   = $reward->points_required;
                $cc->credit   = -$reward->points_required;
                $cc->save();
            }

            if ($reward->stock !== null) $reward->decrement('stock');

            return response()->json([
                'message' => 'Récompense échangée avec succès',
                'data'    => ['points_deducted' => $reward->points_required, 'new_balance' => $card->fresh()->credit],
            ]);
        } catch (\Exception $e) {
            Log::error('[ClientPointsController@redeemReward] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de l\'échange'], 500);
        }
    }

    private function getNextLevel(int $points): ?array
    {
        if ($points < 500)  return ['name' => 'Silver', 'points_needed' => 500  - $points, 'target' => 500];
        if ($points < 1500) return ['name' => 'Gold',   'points_needed' => 1500 - $points, 'target' => 1500];
        return null;
    }
}
