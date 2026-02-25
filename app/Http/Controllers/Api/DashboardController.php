<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AppPayment;
use App\Models\AppSuscription;
use App\Models\Entity;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        $totalEntities = Entity::count();
        $totalAdmins = Admin::count();

        // Active subscriptions: created_at + pricing.duration months > now
        $activeSubscriptions = AppSuscription::with('pricing')
            ->get()
            ->filter(function ($sub) {
                if (!$sub->pricing) return false;
                $expiration = Carbon::parse($sub->created_at)->addMonths($sub->pricing->duration);
                return $expiration->isFuture();
            })
            ->count();

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

        // Subscriptions grouped by pricing name
        $subscriptionsByPricing = AppSuscription::with('pricing')
            ->get()
            ->groupBy(fn($sub) => $sub->pricing?->name ?? 'Sans forfait')
            ->map(fn($group) => $group->count())
            ->toArray();

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
    }
}
