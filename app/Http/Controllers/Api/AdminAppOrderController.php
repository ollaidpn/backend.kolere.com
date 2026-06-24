<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminAppOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Log::info('[AdminAppOrderController@index] Fetching app orders');

        try {
            $query = AppOrder::with(['entity.domain', 'appPayments']);

            if ($request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('reference', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%");
                });
            }

            $appOrders = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 15));

            return response()->json($appOrders);
        } catch (\Exception $e) {
            Log::error('[AdminAppOrderController@index] Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }
}
