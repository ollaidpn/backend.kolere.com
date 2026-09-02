<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShopOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ShopOrderController extends Controller
{
    private function currentEntityId(Request $request): ?int
    {
        return $request->attributes->get('current_entity_id');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $query = ShopOrder::query();

            if ($entityId = $this->currentEntityId($request)) {
                $query->where('entity_id', $entityId);
            }

            if ($request->search) {
                $search = trim((string) $request->search);
                $query->where(function ($q) use ($search) {
                    $q->where('reference', 'like', "%{$search}%")
                        ->orWhere('status_payment', 'like', "%{$search}%")
                        ->orWhere('status_delivery', 'like', "%{$search}%")
                        ->orWhere('status_order', 'like', "%{$search}%")
                        ->orWhere('note', 'like', "%{$search}%");
                });
            }

            $orders = $query->orderByDesc('created_at')->get();

            return response()->json(['data' => $orders]);
        } catch (\Exception $e) {
            Log::error('[ShopOrderController@index] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement des commandes'], 500);
        }
    }

    public function show(Request $request, ShopOrder $order): JsonResponse
    {
        try {
            if ($entityId = $this->currentEntityId($request)) {
                abort_unless((int) $order->entity_id === (int) $entityId, 404);
            }

            return response()->json(['data' => $order]);
        } catch (\Exception $e) {
            Log::error('[ShopOrderController@show] Error', ['id' => $order->id, 'message' => $e->getMessage()]);
            return response()->json(['message' => 'Commande non trouvée'], 404);
        }
    }

    public function update(Request $request, ShopOrder $order): JsonResponse
    {
        try {
            if ($entityId = $this->currentEntityId($request)) {
                abort_unless((int) $order->entity_id === (int) $entityId, 404);
            }

            $validated = $request->validate([
                'status_payment' => 'required|in:pending,paid,refunded',
                'status_delivery' => 'required|in:pending,preparing,shipped,delivered,cancelled',
                'status_order' => 'required|in:pending,confirmed,processing,completed,cancelled',
            ]);

            $order->update($validated);

            return response()->json(['message' => 'Commande mise à jour', 'data' => $order]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[ShopOrderController@update] Error', ['id' => $order->id, 'message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la mise à jour de la commande'], 500);
        }
    }
}
