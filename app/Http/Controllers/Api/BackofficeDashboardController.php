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
    public function getStats(): JsonResponse
    {
        try {
            $today = Carbon::today();
            $thisMonth = Carbon::now()->startOfMonth();
            $lastMonth = Carbon::now()->subMonth()->startOfMonth();
            $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

            // Statistiques clients
            $totalClients = User::count();
            $activeClients = User::whereHas('card', function($query) {
                $query->where('status', 'active');
            })->count();
            $newClientsThisMonth = User::where('created_at', '>=', $thisMonth)->count();

            // Statistiques ventes
            $todaySales = Order::whereDate('created_at', $today)->sum('amount');
            $todaySalesCount = Order::whereDate('created_at', $today)->count();
            $thisMonthSales = Order::where('created_at', '>=', $thisMonth)->sum('amount');
            $thisMonthSalesCount = Order::where('created_at', '>=', $thisMonth)->count();
            $lastMonthSales = Order::whereBetween('created_at', [$lastMonth, $lastMonthEnd])->sum('amount');

            // Statistiques points
            $totalPointsDistributed = Card::sum('points');
            $pointsEarnedThisMonth = Order::where('created_at', '>=', $thisMonth)->sum('points_earned');
            $averagePointsPerClient = $totalClients > 0 ? $totalPointsDistributed / $totalClients : 0;

            // Statistiques promotions
            $activePromotions = Discount::where('status', 'active')
                                   ->where('start_date', '<=', now())
                                   ->where('end_date', '>=', now())
                                   ->count();

            // Ventes récentes (dernières 10)
            $recentSales = Order::with(['user'])
                               ->orderBy('created_at', 'desc')
                               ->limit(10)
                               ->get();

            // Meilleurs clients (top 5 par points)
            $topClients = User::with(['card'])
                             ->whereHas('card')
                             ->orderByDesc('card.points')
                             ->limit(5)
                             ->get();

            // Évolution des ventes (6 derniers mois)
            $salesEvolution = [];
            for ($i = 5; $i >= 0; $i--) {
                $month = Carbon::now()->subMonths($i);
                $monthSales = Order::whereMonth('created_at', $month->month)
                                  ->whereYear('created_at', $month->year)
                                  ->sum('amount');
                $monthCount = Order::whereMonth('created_at', $month->month)
                                  ->whereYear('created_at', $month->year)
                                  ->count();
                
                $salesEvolution[] = [
                    'month' => $month->format('M Y'),
                    'sales' => $monthSales,
                    'count' => $monthCount,
                ];
            }

            // Distribution des clients par tranche de points
            $clientDistribution = [
                'bronze' => Card::where('points', '<', 500)->count(),
                'silver' => Card::whereBetween('points', [500, 1499])->count(),
                'gold' => Card::where('points', '>=', 1500)->count(),
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
                    'growth_vs_last_month' => $growthVsLastMonth,
                ],

                // Ce mois
                'this_month' => [
                    'sales' => $thisMonthSales,
                    'sales_count' => $thisMonthSalesCount,
                    'points_earned' => $pointsEarnedThisMonth,
                    'growth_vs_last_month' => $lastMonthSales > 0 ? 
                        round((($thisMonthSales - $lastMonthSales) / $lastMonthSales * 100, 2) : 0,
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

    public function getQuickStats(): JsonResponse
    {
        try {
            $today = Carbon::today();
            
            $stats = [
                'clients_fidelises' => User::whereHas('card')->count(),
                'ventes_du_jour' => Order::whereDate('created_at', $today)->sum('amount'),
                'points_distribues' => Card::sum('points'),
                'promotions_actives' => Discount::where('status', 'active')
                                           ->where('start_date', '<=', now())
                                           ->where('end_date', '>=', now())
                                           ->count(),
            ];

            return response()->json(['data' => $stats]);
        } catch (\Exception $e) {
            Log::error('[BackofficeDashboardController@getQuickStats] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement des statistiques'], 500);
        }
    }
}
