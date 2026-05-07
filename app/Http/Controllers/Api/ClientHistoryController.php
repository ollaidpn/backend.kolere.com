<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ClientHistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $page = $request->get('page', 1);
            $search = $request->get('search', '');
            $limit = $request->get('limit', 10);

            // Récupérer les commandes du client avec les relations
            $query = Order::where('user_id', $user->id)
                          ->with(['card.entity', 'discount'])
                          ->orderBy('created_at', 'desc');

            // Recherche
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('reference', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('name', 'like', "%{$search}%")
                      ->orWhereHas('card.entity', function($subQuery) use ($search) {
                          $subQuery->where('name', 'like', "%{$search}%");
                      });
                });
            }

            $orders = $query->paginate($limit, ['*'], 'page', $page);

            // Formatter les données
            $formattedOrders = $orders->getCollection()->map(function ($order) {
                $storeName = 'Boutique par défaut';
                if ($order->card && $order->card->entity) {
                    $storeName = $order->card->entity->name;
                }
                
                return [
                    'id' => $order->reference ?? 'TR-' . str_pad($order->id, 4, '0', STR_PAD_LEFT),
                    'storeName' => $storeName,
                    'date' => Carbon::parse($order->created_at)->format('d M Y, H:i'),
                    'amount' => $order->amount,
                    'points' => $order->points_earned ?? 0,
                    'status' => $this->getStatusLabel($order->status),
                    'items' => $order->description ?? $this->formatItems($order->items),
                    'payment_method' => $order->payment_method ?? 'Espèces',
                    'discount' => $order->discount ?? 0,
                    'total' => $order->total ?? $order->amount,
                ];
            });

            return response()->json([
                'data' => $formattedOrders,
                'meta' => [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('[ClientHistoryController@index] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement de l\'historique'], 500);
        }
    }

    public function show(Request $request, $id): JsonResponse
    {
        try {
            $user = $request->user();
            
            $order = Order::where('user_id', $user->id)
                           ->where('id', $id)
                           ->with(['card.entity', 'discount', 'user'])
                           ->first();

            if (!$order) {
                return response()->json(['message' => 'Commande non trouvée'], 404);
            }

            $storeName = 'Boutique par défaut';
            if ($order->card && $order->card->entity) {
                $storeName = $order->card->entity->name;
            }

            return response()->json([
                'data' => [
                    'id' => $order->reference ?? 'TR-' . str_pad($order->id, 4, '0', STR_PAD_LEFT),
                    'storeName' => $storeName,
                    'date' => Carbon::parse($order->created_at)->format('d M Y, H:i'),
                    'amount' => $order->amount,
                    'points' => $order->points_earned ?? 0,
                    'status' => $this->getStatusLabel($order->status),
                    'items' => $order->description ?? $this->formatItems($order->items),
                    'payment_method' => $order->payment_method ?? 'Espèces',
                    'discount' => $order->discount ?? 0,
                    'total' => $order->total ?? $order->amount,
                    'created_at' => $order->created_at,
                    'updated_at' => $order->updated_at,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('[ClientHistoryController@show] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement de la commande'], 500);
        }
    }

    public function getStats(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Statistiques générales
            $totalOrders = Order::where('user_id', $user->id)->count();
            $totalAmount = Order::where('user_id', $user->id)->sum('amount');
            $totalPoints = Order::where('user_id', $user->id)->sum('points_earned');
            
            // Ce mois
            $thisMonth = Carbon::now()->startOfMonth();
            $thisMonthOrders = Order::where('user_id', $user->id)
                                  ->where('created_at', '>=', $thisMonth)
                                  ->count();
            $thisMonthAmount = Order::where('user_id', $user->id)
                                 ->where('created_at', '>=', $thisMonth)
                                 ->sum('amount');

            // Dernière commande
            $lastOrder = Order::where('user_id', $user->id)
                             ->orderBy('created_at', 'desc')
                             ->first();

            $stats = [
                'total_orders' => $totalOrders,
                'total_amount' => $totalAmount,
                'total_points' => $totalPoints,
                'this_month_orders' => $thisMonthOrders,
                'this_month_amount' => $thisMonthAmount,
                'last_order_date' => $lastOrder ? Carbon::parse($lastOrder->created_at)->format('d M Y') : null,
                'average_order_amount' => $totalOrders > 0 ? round($totalAmount / $totalOrders, 2) : 0,
            ];

            return response()->json(['data' => $stats]);
        } catch (\Exception $e) {
            Log::error('[ClientHistoryController@getStats] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement des statistiques'], 500);
        }
    }

    private function formatItems($items): string
    {
        if (is_string($items)) {
            return $items;
        }
        
        if (is_array($items)) {
            $formattedItems = [];
            foreach ($items as $item) {
                if (is_string($item)) {
                    $formattedItems[] = $item;
                } elseif (is_array($item) && isset($item['name'])) {
                    $quantity = isset($item['quantity']) ? $item['quantity'] : 1;
                    $formattedItems[] = $item['name'] . ($quantity > 1 ? " x{$quantity}" : '');
                }
            }
            return implode(', ', $formattedItems);
        }
        
        return 'Articles divers';
    }

    private function getStatusLabel(string $status): string
    {
        $labels = [
            'pending' => 'En attente',
            'processing' => 'En cours',
            'completed' => 'Complété',
            'cancelled' => 'Annulé',
            'refunded' => 'Remboursé',
        ];

        return $labels[$status] ?? $status;
    }
}
