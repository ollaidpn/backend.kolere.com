<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShopCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ShopCategoryController extends Controller
{
    private function currentEntityId(Request $request): ?int
    {
        return $request->attributes->get('current_entity_id');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $query = ShopCategory::query();

            if ($entityId = $this->currentEntityId($request)) {
                $query->where('entity_id', $entityId);
            }

            if ($request->search) {
                $search = trim((string) $request->search);
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%");
            }

            $categories = $query->orderBy('name')->get();

            return response()->json(['data' => $categories]);
        } catch (\Exception $e) {
            Log::error('[ShopCategoryController@index] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement des catégories'], 500);
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
                'reference' => 'nullable|string|max:255|unique:shop_categories,reference',
                'name' => 'required|string|max:255',
            ]);

            $category = ShopCategory::create([
                'entity_id' => $entityId,
                'reference' => $validated['reference'] ?? null,
                'name' => $validated['name'],
            ]);

            return response()->json(['message' => 'Catégorie créée', 'data' => $category], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[ShopCategoryController@store] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la création de la catégorie'], 500);
        }
    }

    public function update(Request $request, ShopCategory $category): JsonResponse
    {
        try {
            if ($entityId = $this->currentEntityId($request)) {
                abort_unless((int) $category->entity_id === (int) $entityId, 404);
            }

            $validated = $request->validate([
                'reference' => 'nullable|string|max:255|unique:shop_categories,reference,' . $category->id,
                'name' => 'required|string|max:255',
            ]);

            $category->update([
                'reference' => $validated['reference'] ?? $category->reference,
                'name' => $validated['name'],
            ]);

            return response()->json(['message' => 'Catégorie mise à jour', 'data' => $category]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[ShopCategoryController@update] Error', ['id' => $category->id, 'message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la mise à jour de la catégorie'], 500);
        }
    }

    public function destroy(Request $request, ShopCategory $category): JsonResponse
    {
        try {
            if ($entityId = $this->currentEntityId($request)) {
                abort_unless((int) $category->entity_id === (int) $entityId, 404);
            }

            $category->delete();

            return response()->json(['message' => 'Catégorie supprimée']);
        } catch (\Exception $e) {
            Log::error('[ShopCategoryController@destroy] Error', ['id' => $category->id, 'message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la suppression de la catégorie'], 500);
        }
    }
}
