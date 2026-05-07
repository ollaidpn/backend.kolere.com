<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\User;
use App\Models\Card;
use App\Models\CardCredit;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Order::with(['user', 'card', 'discounts']);
            
            // Filtrer par date
            if ($request->date_from) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->date_to) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }
            
            // Filtrer par client
            if ($request->client_id) {
                $query->where('user_id', $request->client_id);
            }
            
            // Pagination
            $perPage = $request->get('per_page', 15);
            $sales = $query->orderBy('created_at', 'desc')
                           ->paginate($perPage);

            return response()->json([
                'data' => $sales->items(),
                'meta' => [
                    'current_page' => $sales->currentPage(),
                    'last_page' => $sales->lastPage(),
                    'per_page' => $sales->perPage(),
                    'total' => $sales->total(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('[SaleController@index] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement des ventes'], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'client_id' => 'required|exists:users,id',
                'amount' => 'required|numeric|min:0',
                'items' => 'required|array|min:1',
                'items.*.name' => 'required|string|max:255',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.price' => 'required|numeric|min:0',
                'discount_code' => 'nullable|string|max:50',
            ]);

            return DB::transaction(function () use ($validated) {
                // Récupérer le client et sa carte
                $client = User::findOrFail($validated['client_id']);
                $card = $client->card;
                
                if (!$card) {
                    throw ValidationException::withMessages([
                        'client_id' => ['Ce client n\'a pas de carte de fidélité']
                    ]);
                }

                // Calculer les points (1000 FCFA = 1 point)
                $pointsEarned = floor($validated['amount'] / 1000);

                // Créer la commande
                $order = Order::create([
                    'user_id' => $client->id,
                    'card_id' => $card->id,
                    'amount' => $validated['amount'],
                    'items' => json_encode($validated['items']),
                    'points_earned' => $pointsEarned,
                    'status' => 'completed',
                    'reference' => 'SALE-' . date('YmdHis') . '-' . rand(1000, 9999),
                ]);

                // Ajouter les points à la carte (colonne credit)
                $card->increment('credit', $pointsEarned);

                // Créer un crédit de points pour l'historique
                CardCredit::create([
                    'card_id' => $card->id,
                    'order_id' => $order->id,
                    'points' => $pointsEarned,
                    'credit' => $pointsEarned,
                    'type' => 'earned',
                    'description' => "Points gagnés - Vente {$order->reference}",
                ]);

                Log::info('[SaleController@store] Sale created', [
                    'order_id' => $order->id,
                    'client_id' => $client->id,
                    'amount' => $validated['amount'],
                    'points_earned' => $pointsEarned
                ]);

                return response()->json([
                    'message' => 'Vente enregistrée avec succès',
                    'data' => $order->load(['user', 'card'])
                ], 201);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[SaleController@store] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de l\'enregistrement de la vente'], 500);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $sale = Order::with(['user', 'card', 'discounts', 'cardCredits'])
                        ->findOrFail($id);
            
            return response()->json(['data' => $sale]);
        } catch (\Exception $e) {
            Log::error('[SaleController@show] Error', ['id' => $id, 'message' => $e->getMessage()]);
            return response()->json(['message' => 'Vente non trouvée'], 404);
        }
    }

    public function getStats(): JsonResponse
    {
        try {
            $today = now()->format('Y-m-d');
            $thisMonth = now()->startOfMonth();
            
            $stats = [
                'today_sales' => Order::whereDate('created_at', $today)->sum('amount'),
                'today_sales_count' => Order::whereDate('created_at', $today)->count(),
                'this_month_sales' => Order::where('created_at', '>=', $thisMonth)->sum('amount'),
                'this_month_sales_count' => Order::where('created_at', '>=', $thisMonth)->count(),
                'total_points_distributed' => Order::sum('points_earned'),
                'total_sales' => Order::sum('amount'),
                'total_sales_count' => Order::count(),
            ];

            return response()->json(['data' => $stats]);
        } catch (\Exception $e) {
            Log::error('[SaleController@getStats] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement des statistiques'], 500);
        }
    }

    public function getRecentSales(): JsonResponse
    {
        try {
            $recentSales = Order::with(['user'])
                               ->orderBy('created_at', 'desc')
                               ->limit(10)
                               ->get();

            return response()->json(['data' => $recentSales]);
        } catch (\Exception $e) {
            Log::error('[SaleController@getRecentSales] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement des ventes récentes'], 500);
        }
    }
}
