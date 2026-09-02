<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Order;
use App\Models\Card;
use App\Models\Discount;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BackofficeDashboardController extends Controller
{
    private function entityId(Request $request): ?int
    {
        return $request->attributes->get('current_entity_id');
    }

    public function getStats(Request $request): JsonResponse
    {
        try {
            $entityId = $this->entityId($request);

            $today = Carbon::today();
            $thisMonth = Carbon::now()->startOfMonth();
            $lastMonth = Carbon::now()->subMonth()->startOfMonth();
            $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

            // Statistiques clients
            $clientsQuery = User::query();
            if ($entityId) {
                $clientsQuery->whereHas('card', fn ($query) => $query->where('entity_id', $entityId));
            }

            $totalClients = (clone $clientsQuery)->count();
            $activeClients = (clone $clientsQuery)->whereHas('card', function ($query) use ($entityId) {
                $query->where('status', 'active');
                if ($entityId) {
                    $query->where('entity_id', $entityId);
                }
            })->count();
            $newClientsThisMonth = (clone $clientsQuery)->where('created_at', '>=', $thisMonth)->count();

            // Statistiques ventes
            $ordersQuery = Order::query();
            if ($entityId) {
                $ordersQuery->whereHas('card', fn ($query) => $query->where('entity_id', $entityId));
            }

            $todaySales = (clone $ordersQuery)->whereDate('created_at', $today)->sum('amount');
            $todaySalesCount = (clone $ordersQuery)->whereDate('created_at', $today)->count();
            $thisMonthSales = (clone $ordersQuery)->where('created_at', '>=', $thisMonth)->sum('amount');
            $thisMonthSalesCount = (clone $ordersQuery)->where('created_at', '>=', $thisMonth)->count();
            $lastMonthSales = (clone $ordersQuery)->whereBetween('created_at', [$lastMonth, $lastMonthEnd])->sum('amount');

            // Statistiques points
            $cardsQuery = Card::query();
            if ($entityId) {
                $cardsQuery->where('entity_id', $entityId);
            }

            $totalPointsDistributed = (clone $cardsQuery)->sum('credit');
            $pointsEarnedThisMonth = (clone $ordersQuery)->where('created_at', '>=', $thisMonth)->sum('points_earned');
            $averagePointsPerClient = $totalClients > 0 ? $totalPointsDistributed / $totalClients : 0;

            // Statistiques promotions
            $activePromotions = Discount::query()
                ->when($entityId, fn ($query) => $query->where('entity_id', $entityId))
                ->whereDate('expiration', '>=', now())
                ->count();

            // Ventes récentes (dernières 10)
            $recentSales = (clone $ordersQuery)
                ->with(['user', 'card'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            // Meilleurs clients (top 5 par points)
            $topClients = User::with(['card' => fn ($query) => $entityId ? $query->where('entity_id', $entityId) : $query])
                ->whereHas('card', fn ($query) => $entityId ? $query->where('entity_id', $entityId) : $query)
                ->get()
                ->sortByDesc(fn ($user) => $user->card?->credit ?? 0)
                ->take(5)
                ->values();

            // Évolution des ventes (6 derniers mois)
            $salesEvolution = [];
            for ($i = 5; $i >= 0; $i--) {
                $month = Carbon::now()->subMonths($i);
                $monthSales = (clone $ordersQuery)
                    ->whereMonth('created_at', $month->month)
                    ->whereYear('created_at', $month->year)
                    ->sum('amount');
                $monthCount = (clone $ordersQuery)
                    ->whereMonth('created_at', $month->month)
                    ->whereYear('created_at', $month->year)
                    ->count();
                
                $salesEvolution[] = [
                    'month' => $month->format('M Y'),
                    'sales' => $monthSales,
                    'count' => $monthCount,
                ];
            }

            // Distribution des clients par tranche de points (colonne réelle = credit)
            $clientDistribution = [
                'bronze' => (clone $cardsQuery)->where('credit', '<', 500)->count(),
                'silver' => (clone $cardsQuery)->whereBetween('credit', [500, 1499])->count(),
                'gold' => (clone $cardsQuery)->where('credit', '>=', 1500)->count(),
            ];

            $stats = [
                // Vue d'ensemble
                'overview' => [
                    'total_clients' => $totalClients,
                    'active_clients' => $activeClients,
                    'new_clients_this_month' => $newClientsThisMonth,
                    'total_sales' => $thisMonthSales,
                    'total_sales_count' => $thisMonthSalesCount,
                    'total_points_distributed' => $totalPointsDistributed,
                    'active_promotions' => $activePromotions,
                ],

                // Aujourd'hui
                'today' => [
                    'sales' => $todaySales,
                    'sales_count' => $todaySalesCount,
                    'growth_vs_last_month' => $lastMonthSales > 0 ?
                        round(($todaySales - ($lastMonthSales / 30)) / ($lastMonthSales / 30) * 100, 2) : 0,
                ],

                // Ce mois
                'this_month' => [
                    'sales' => $thisMonthSales,
                    'sales_count' => $thisMonthSalesCount,
                    'points_earned' => $pointsEarnedThisMonth,
                    'growth_vs_last_month' => $lastMonthSales > 0 ?
                        round(($thisMonthSales - $lastMonthSales) / $lastMonthSales * 100, 2) : 0,
                ],

                // Moyennes
                'averages' => [
                    'average_points_per_client' => round($averagePointsPerClient, 2),
                    'average_sale_amount' => $thisMonthSalesCount > 0 ? 
                        round($thisMonthSales / $thisMonthSalesCount, 2) : 0,
                ],

                // Données pour graphiques
                'charts' => [
                    'sales_evolution' => $salesEvolution,
                    'client_distribution' => $clientDistribution,
                ],

                // Données récentes
                'recent_data' => [
                    'recent_sales' => $recentSales,
                    'top_clients' => $topClients,
                ],
            ];

            return response()->json(['data' => $stats]);
        } catch (\Exception $e) {
            Log::error('[BackofficeDashboardController@getStats] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement des statistiques'], 500);
        }
    }

    public function getQuickStats(Request $request): JsonResponse
    {
        $entityId = $this->entityId($request);
        $today = Carbon::today();

        // Chaque compteur est isolé — une erreur n'en bloque pas les autres
        $clientsFidelises = 0;
        $ventesDuJour     = 0;
        $pointsDistribues = 0;

        try { $clientsFidelises = User::whereHas('card', fn ($query) => $entityId ? $query->where('entity_id', $entityId) : $query)->count(); } catch (\Exception $e) {
            Log::error('[getQuickStats] clients_fidelises', ['msg' => $e->getMessage()]);
        }
        try {
            $query = Order::query()->whereDate('created_at', $today);
            if ($entityId) {
                $query->whereHas('card', fn ($cardQuery) => $cardQuery->where('entity_id', $entityId));
            }
            $ventesDuJour = (float) $query->sum('amount');
        } catch (\Exception $e) {
            Log::error('[getQuickStats] ventes_du_jour', ['msg' => $e->getMessage()]);
        }
        try {
            $query = Card::query();
            if ($entityId) {
                $query->where('entity_id', $entityId);
            }
            $pointsDistribues = (int) $query->sum('credit');
        } catch (\Exception $e) {
            Log::error('[getQuickStats] points_distribues', ['msg' => $e->getMessage()]);
        }

        return response()->json(['data' => [
            'clients_fidelises' => $clientsFidelises,
            'ventes_du_jour'    => $ventesDuJour,
            'points_distribues' => $pointsDistribues,
            'promotions_actives' => 0,
        ]]);
    }
}
