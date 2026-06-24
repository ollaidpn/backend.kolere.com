<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSuscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        Log::info('[SubscriptionController@index] Fetching subscriptions');
        try {
            $subscriptions = AppSuscription::with(['entity.domain', 'pricing', 'appPayments'])
                ->when($request->search, function ($query, $search) {
                    $query->whereHas('entity', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
                })
                ->orderBy('created_at', 'desc')
                ->get();

            Log::info('[SubscriptionController@index] Success', ['count' => $subscriptions->count()]);
            return response()->json(['data' => $subscriptions]);
        } catch (\Exception $e) {
            Log::error('[SubscriptionController@index] Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }
}
