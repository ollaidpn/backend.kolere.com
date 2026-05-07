<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ClientHistoryController extends Controller
{
    private function formatOrder(Order $order): array
    {
        return [
            'id'             => $order->reference ?? 'TR-' . str_pad($order->id, 4, '0', STR_PAD_LEFT),
            'storeName'      => 'Pharmacie',
            'date'           => Carbon::parse($order->created_at)->format('d M Y, H:i'),
            'amount'         => $order->amount,
            'points'         => $order->points_earned ?? 0,
            'status'         => $this->statusLabel($order->status ?? 'completed'),
            'items'          => $order->description ?? $this->formatItems($order->items),
            'payment_method' => 'Espèces',
            'discount'       => 0,
            'total'          => $order->amount,
            'created_at'     => $order->created_at,
            'updated_at'     => $order->updated_at,
        ];
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $user   = $request->user();
            $page   = $request->get('page', 1);
            $search = $request->get('search', '');
            $limit  = $request->get('limit', 10);

            $query = Order::where('user_id', $user->id)->orderBy('created_at', 'desc');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('reference', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $orders = $query->paginate($limit, ['*'], 'page', $page);

            return response()->json([
                'data' => $orders->getCollection()->map(fn($o) => $this->formatOrder($o)),
                'meta' => [
                    'current_page' => $orders->currentPage(),
                    'last_page'    => $orders->lastPage(),
                    'per_page'     => $orders->perPage(),
                    'total'        => $orders->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('[ClientHistoryController@index]', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement de l\'historique'], 500);
        }
    }

    public function show(Request $request, $id): JsonResponse
    {
        try {
            $order = Order::where('user_id', $request->user()->id)
                ->where(function ($q) use ($id) {
                    $q->where('id', $id)->orWhere('reference', $id);
                })
                ->first();

            if (!$order) {
                return response()->json(['message' => 'Commande non trouvée'], 404);
            }

            return response()->json(['data' => $this->formatOrder($order)]);
        } catch (\Exception $e) {
            Log::error('[ClientHistoryController@show]', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement de la commande'], 500);
        }
    }

    public function getStats(Request $request): JsonResponse
    {
        try {
            $user      = $request->user();
            $thisMonth = Carbon::now()->startOfMonth();

            return response()->json(['data' => [
                'total_orders'        => Order::where('user_id', $user->id)->count(),
                'total_amount'        => (float) Order::where('user_id', $user->id)->sum('amount'),
                'total_points'        => (int) Order::where('user_id', $user->id)->sum('points_earned'),
                'this_month_orders'   => Order::where('user_id', $user->id)->where('created_at', '>=', $thisMonth)->count(),
                'this_month_amount'   => (float) Order::where('user_id', $user->id)->where('created_at', '>=', $thisMonth)->sum('amount'),
            ]]);
        } catch (\Exception $e) {
            Log::error('[ClientHistoryController@getStats]', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur'], 500);
        }
    }

    private function formatItems($items): string
    {
        if (is_string($items)) return $items;
        if (is_array($items)) {
            return implode(', ', array_map(function ($item) {
                if (is_string($item)) return $item;
                $qty = $item['quantity'] ?? 1;
                return ($item['name'] ?? '') . ($qty > 1 ? " x{$qty}" : '');
            }, $items));
        }
        return 'Articles divers';
    }

    private function statusLabel(string $status): string
    {
        return ['pending' => 'En attente', 'processing' => 'En cours', 'completed' => 'Complété',
                'cancelled' => 'Annulé', 'refunded' => 'Remboursé'][$status] ?? $status;
    }
}
