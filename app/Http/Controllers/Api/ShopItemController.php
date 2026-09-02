<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShopItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ShopItemController extends Controller
{
    private function currentEntityId(Request $request): ?int
    {
        return $request->attributes->get('current_entity_id');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $query = ShopItem::with(['category', 'brand']);

            if ($entityId = $this->currentEntityId($request)) {
                $query->where('entity_id', $entityId);
            }

            if ($request->search) {
                $search = trim((string) $request->search);
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%")
                        ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('brand', fn ($brandQuery) => $brandQuery->where('name', 'like', "%{$search}%"));
                });
            }

            $perPage = (int) $request->get('per_page', 15);
            $items = $query->orderByDesc('created_at')->paginate($perPage);

            return response()->json([
                'data' => $items->items(),
                'meta' => [
                    'current_page' => $items->currentPage(),
                    'last_page' => $items->lastPage(),
                    'per_page' => $items->perPage(),
                    'total' => $items->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('[ShopItemController@index] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement des articles'], 500);
        }
    }

    public function show(Request $request, ShopItem $item): JsonResponse
    {
        try {
            if ($entityId = $this->currentEntityId($request)) {
                abort_unless((int) $item->entity_id === (int) $entityId, 404);
            }

            return response()->json(['data' => $item->load(['category', 'brand'])]);
        } catch (\Exception $e) {
            Log::error('[ShopItemController@show] Error', ['item_id' => $item->id, 'message' => $e->getMessage()]);
            return response()->json(['message' => 'Article non trouvé'], 404);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $entityId = $this->currentEntityId($request);
            if (!$entityId) {
                return response()->json(['message' => 'Entité courante introuvable'], 422);
            }

            $validated = $request->validate([
                'category_id' => 'required|exists:shop_categories,id',
                'brand_id' => 'nullable|exists:shop_brands,id',
                'reference' => 'nullable|string|max:255|unique:shop_items,reference',
                'name' => 'required|string|max:255',
                'price' => 'required|numeric|min:0',
                'promo_price' => 'nullable|numeric|min:0|lte:price',
                'stock' => 'nullable|integer|min:0',
                'description' => 'nullable|string|max:5000',
                'image' => 'nullable|string|max:2048',
                'gallery' => 'nullable|array',
                'gallery.*' => 'nullable|string|max:2048',
                'status' => 'required|in:active,draft,archived',
            ]);

            $item = ShopItem::create([
                'entity_id' => $entityId,
                'category_id' => $validated['category_id'],
                'brand_id' => $validated['brand_id'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'name' => $validated['name'],
                'price' => $validated['price'],
                'promo_price' => $validated['promo_price'] ?? null,
                'stock' => $validated['stock'] ?? 0,
                'description' => $validated['description'] ?? null,
                'image' => $validated['image'] ?? null,
                'gallery' => $validated['gallery'] ?? [],
                'status' => $validated['status'],
            ]);

            return response()->json([
                'message' => 'Article créé avec succès',
                'data' => $item->load(['category', 'brand']),
            ], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[ShopItemController@store] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la création de l\'article'], 500);
        }
    }

    public function update(Request $request, ShopItem $item): JsonResponse
    {
        try {
            if ($entityId = $this->currentEntityId($request)) {
                abort_unless((int) $item->entity_id === (int) $entityId, 404);
            }

            $validated = $request->validate([
                'category_id' => 'required|exists:shop_categories,id',
                'brand_id' => 'nullable|exists:shop_brands,id',
                'reference' => 'nullable|string|max:255|unique:shop_items,reference,' . $item->id,
                'name' => 'required|string|max:255',
                'price' => 'required|numeric|min:0',
                'promo_price' => 'nullable|numeric|min:0|lte:price',
                'stock' => 'nullable|integer|min:0',
                'description' => 'nullable|string|max:5000',
                'image' => 'nullable|string|max:2048',
                'gallery' => 'nullable|array',
                'gallery.*' => 'nullable|string|max:2048',
                'status' => 'required|in:active,draft,archived',
            ]);

            $item->update([
                'category_id' => $validated['category_id'],
                'brand_id' => $validated['brand_id'] ?? null,
                'reference' => $validated['reference'] ?? $item->reference,
                'name' => $validated['name'],
                'price' => $validated['price'],
                'promo_price' => $validated['promo_price'] ?? null,
                'stock' => $validated['stock'] ?? 0,
                'description' => $validated['description'] ?? null,
                'image' => $validated['image'] ?? null,
                'gallery' => $validated['gallery'] ?? [],
                'status' => $validated['status'],
            ]);

            return response()->json([
                'message' => 'Article mis à jour',
                'data' => $item->load(['category', 'brand']),
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[ShopItemController@update] Error', ['item_id' => $item->id, 'message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la mise à jour de l\'article'], 500);
        }
    }

    public function destroy(Request $request, ShopItem $item): JsonResponse
    {
        try {
            if ($entityId = $this->currentEntityId($request)) {
                abort_unless((int) $item->entity_id === (int) $entityId, 404);
            }

            $item->delete();

            return response()->json(['message' => 'Article supprimé']);
        } catch (\Exception $e) {
            Log::error('[ShopItemController@destroy] Error', ['item_id' => $item->id, 'message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la suppression de l\'article'], 500);
        }
    }
}
