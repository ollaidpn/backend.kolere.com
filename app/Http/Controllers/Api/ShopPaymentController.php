<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShopPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ShopPaymentController extends Controller
{
    private function currentEntityId(Request $request): ?int
    {
        return $request->attributes->get('current_entity_id');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $query = ShopPayment::with(['order']);

            if ($entityId = $this->currentEntityId($request)) {
                $query->where('entity_id', $entityId);
            }

            if ($request->search) {
                $search = trim((string) $request->search);
                $query->where(function ($q) use ($search) {
                    $q->where('reference', 'like', "%{$search}%")
                        ->orWhere('paid_by', 'like', "%{$search}%")
                        ->orWhereJsonContains('client_infos->name', $search)
                        ->orWhereJsonContains('client_infos->email', $search);
                });
            }

            $payments = $query->orderByDesc('created_at')->get()->map(function ($payment) {
                $payment->client_infos = $payment->client_infos ?? [];
                if ($payment->order) {
                    $payment->order->makeHidden([]);
                }
                return $payment;
            });

            return response()->json(['data' => $payments]);
        } catch (\Exception $e) {
            Log::error('[ShopPaymentController@index] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement des paiements'], 500);
        }
    }
}
