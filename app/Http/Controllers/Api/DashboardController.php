<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AppPayment;
use App\Models\AppSuscription;
use App\Models\Entity;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        Log::info('[DashboardController@stats] Fetching dashboard stats');
        try {
            $totalEntities = Entity::count();
            Log::info('[DashboardController@stats] Entities counted', ['total' => $totalEntities]);

            $totalAdmins = Admin::count();

            $activeSubscriptions = AppSuscription::with('pricing')
                ->get()
                ->filter(function ($sub) {
                    if (!$sub->pricing) return false;
                    $expiration = Carbon::parse($sub->created_at)->addMonths($sub->pricing->duration);
                    return $expiration->isFuture();
                })
                ->count();
            Log::info('[DashboardController@stats] Active subscriptions', ['count' => $activeSubscriptions]);

            $totalPaymentsAmount = AppPayment::where('status', 'paid')->sum('amount');

            $recentSubscriptions = AppSuscription::with(['entity.domain', 'pricing'])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            $recentPayments = AppPayment::with([
                'appSuscription.entity',
                'appOrder.entity',
            ])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            $subscriptionsByPricing = AppSuscription::with('pricing')
                ->get()
                ->groupBy(fn($sub) => $sub->pricing?->name ?? 'Sans forfait')
                ->map(fn($group) => $group->count())
                ->toArray();

            Log::info('[DashboardController@stats] Success');

            return response()->json([
                'data' => [
                    'total_entities' => $totalEntities,
                    'total_admins' => $totalAdmins,
                    'active_subscriptions' => $activeSubscriptions,
                    'total_payments_amount' => $totalPaymentsAmount,
                    'recent_subscriptions' => $recentSubscriptions,
                    'recent_payments' => $recentPayments,
                    'subscriptions_by_pricing' => $subscriptionsByPricing,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('[DashboardController@stats] Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }
}
