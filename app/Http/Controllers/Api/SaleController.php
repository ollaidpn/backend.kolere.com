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
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    private function entityId(Request $request): ?int
    {
        return $request->attributes->get('current_entity_id');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $query = Order::with(['user', 'card']);
            if ($entityId = $this->entityId($request)) {
                $query->where('entity_id', $entityId);
            }

            if ($request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('reference', 'like', "%{$search}%")
                      ->orWhereHas('user', function ($u) use ($search) {
                          $u->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                      });
                });
            }

            $perPage = $request->get('per_page', 15);
            $sales = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'data' => $sales->items(),
                'meta' => [
                    'current_page' => $sales->currentPage(),
                    'last_page'    => $sales->lastPage(),
                    'per_page'     => $sales->perPage(),
                    'total'        => $sales->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('[SaleController@index] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement des ventes'], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            // Si envoyé en multipart/form-data, items arrive comme chaîne JSON
            if (is_string($request->items)) {
                $request->merge(['items' => json_decode($request->items, true) ?? []]);
            }

            $validated = $request->validate([
                'client_id'   => 'required|exists:users,id',
                'amount'      => 'required|numeric|min:0.01',
                'description' => 'nullable|string|max:500',
                'items'       => 'required|array|min:1',
                'items.*.name'     => 'required|string|max:255',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.price'    => 'required|numeric|min:0',
                'prescription' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            ]);

            return DB::transaction(function () use ($validated, $request) {
                $client = User::findOrFail($validated['client_id']);
                $entityId = $this->entityId($request);

                $card = Card::where('user_id', $client->id)
                    ->where('entity_id', $entityId)
                    ->first();

                if (!$card) {
                    throw ValidationException::withMessages([
                        'client_id' => ['Ce client n\'a pas de carte de fidélité'],
                    ]);
                }

                if (!$entityId) {
                    throw ValidationException::withMessages([
                        'entity_id' => ['Entité courante introuvable'],
                    ]);
                }

                if ((int) $card->entity_id !== (int) $entityId) {
                    throw ValidationException::withMessages([
                        'client_id' => ['Ce client n\'appartient pas à la boutique active'],
                    ]);
                }

                // Points : 1 point par 1000 FCFA
                $pointsEarned = (int) floor($validated['amount'] / 1000);

                // Stocker l'ordonnance si fournie
                $prescriptionPath = null;
                if ($request->hasFile('prescription')) {
                    $prescriptionPath = $request->file('prescription')->store('prescriptions', 'public');
                }

                $order = Order::create([
                    'entity_id'       => $entityId,
                    'user_id'          => $client->id,
                    'card_id'          => $card->id,
                    'reference'        => 'SALE-' . date('YmdHis') . '-' . rand(1000, 9999),
                    'name'             => 'Achat Pharmacie',
                    'description'      => $validated['description'] ?? null,
                    'items'            => $validated['items'],
                    'amount'           => $validated['amount'],
                    'price'            => $validated['amount'],
                    'discount'         => 0,
                    'total'            => $validated['amount'],
                    'points_earned'    => $pointsEarned,
                    'status'           => 'completed',
                    'prescription_photo' => $prescriptionPath,
                ]);

                // Ajouter les points à la carte
                $card->increment('credit', $pointsEarned);

                // Historique de points (résilient si colonnes pas encore migrées)
                try {
                    CardCredit::create([
                        'entity_id' => $card->entity_id,
                        'card_id'     => $card->id,
                        'order_id'    => $order->id,
                        'amount'      => $pointsEarned,
                        'points'      => $pointsEarned,
                        'credit'      => $pointsEarned,
                        'type'        => 'earned',
                        'description' => "Points gagnés — {$order->reference}",
                    ]);
                } catch (\Illuminate\Database\QueryException $qe) {
                    $cc = new CardCredit();
                    $cc->entity_id = $card->entity_id;
                    $cc->card_id  = $card->id;
                    $cc->order_id = $order->id;
                    $cc->amount   = $pointsEarned;
                    $cc->credit   = $pointsEarned;
                    $cc->save();
                }

                Log::info('[SaleController@store] Sale created', [
                    'order_id'  => $order->id,
                    'client_id' => $client->id,
                    'amount'    => $validated['amount'],
                    'points'    => $pointsEarned,
                ]);

                return response()->json([
                    'message' => 'Vente enregistrée avec succès',
                    'data'    => $order->load(['user']),
                ], 201);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[SaleController@store] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de l\'enregistrement de la vente : ' . $e->getMessage()], 500);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $query = Order::with(['user', 'card']);
            if ($entityId = request()->attributes->get('current_entity_id')) {
                $query->where('entity_id', $entityId);
            }
            $sale = $query->findOrFail($id);
            return response()->json(['data' => $sale]);
        } catch (\Exception $e) {
            Log::error('[SaleController@show] Error', ['id' => $id, 'message' => $e->getMessage()]);
            return response()->json(['message' => 'Vente non trouvée'], 404);
        }
    }

    public function getStats(): JsonResponse
    {
        try {
            $entityId = request()->attributes->get('current_entity_id');
            $today     = now()->format('Y-m-d');
            $thisMonth = now()->startOfMonth();

            $ordersQuery = Order::query();
            if ($entityId) {
                $ordersQuery->where('entity_id', $entityId);
            }

            $stats = [
                'today_sales'              => (float) (clone $ordersQuery)->whereDate('created_at', $today)->sum('amount'),
                'today_sales_count'        => (clone $ordersQuery)->whereDate('created_at', $today)->count(),
                'this_month_sales'         => (float) (clone $ordersQuery)->where('created_at', '>=', $thisMonth)->sum('amount'),
                'this_month_sales_count'   => (clone $ordersQuery)->where('created_at', '>=', $thisMonth)->count(),
                'total_points_distributed' => (int) (clone $ordersQuery)->sum('points_earned'),
                'total_sales'              => (float) (clone $ordersQuery)->sum('amount'),
                'total_sales_count'        => (clone $ordersQuery)->count(),
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
            $query = Order::with(['user']);
            if ($entityId = request()->attributes->get('current_entity_id')) {
                $query->where('entity_id', $entityId);
            }

            $recentSales = $query
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            return response()->json(['data' => $recentSales]);
        } catch (\Exception $e) {
            // Retourner un tableau vide plutôt qu'un 500
            Log::error('[SaleController@getRecentSales] Error', ['message' => $e->getMessage()]);
            return response()->json(['data' => []]);
        }
    }
}
