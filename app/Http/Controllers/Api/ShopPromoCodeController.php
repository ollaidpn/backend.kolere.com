<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShopPromoCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ShopPromoCodeController extends Controller
{
    private function currentEntityId(Request $request): ?int
    {
        return $request->attributes->get('current_entity_id');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $query = ShopPromoCode::query();

            if ($entityId = $this->currentEntityId($request)) {
                $query->where('entity_id', $entityId);
            }

            if ($request->search) {
                $search = trim((string) $request->search);
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $codes = $query->orderByDesc('created_at')->get();

            return response()->json(['data' => $codes]);
        } catch (\Exception $e) {
            Log::error('[ShopPromoCodeController@index] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement des codes promo'], 500);
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
                'reference' => 'nullable|string|max:255|unique:shop_promo_codes,reference',
                'code' => 'required|string|max:255|unique:shop_promo_codes,code',
                'description' => 'nullable|string|max:1000',
                'type' => 'required|in:percentage,fixed',
                'value' => 'required|numeric|min:0',
                'min_amount' => 'nullable|numeric|min:0',
                'uses' => 'nullable|integer|min:0',
                'max_uses' => 'nullable|integer|min:0',
                'status' => 'required|in:active,scheduled,disabled',
                'valid_until' => 'nullable|date',
            ]);

            $code = ShopPromoCode::create([
                'entity_id' => $entityId,
                'reference' => $validated['reference'] ?? null,
                'code' => strtoupper($validated['code']),
                'description' => $validated['description'] ?? null,
                'type' => $validated['type'],
                'value' => $validated['value'],
                'min_amount' => $validated['min_amount'] ?? 0,
                'uses' => $validated['uses'] ?? 0,
                'max_uses' => $validated['max_uses'] ?? 0,
                'status' => $validated['status'],
                'valid_until' => $validated['valid_until'] ?? null,
            ]);

            return response()->json(['message' => 'Code promo créé', 'data' => $code], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[ShopPromoCodeController@store] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la création du code promo'], 500);
        }
    }

    public function update(Request $request, ShopPromoCode $promoCode): JsonResponse
    {
        try {
            if ($entityId = $this->currentEntityId($request)) {
                abort_unless((int) $promoCode->entity_id === (int) $entityId, 404);
            }

            $validated = $request->validate([
                'reference' => 'nullable|string|max:255|unique:shop_promo_codes,reference,' . $promoCode->id,
                'code' => 'required|string|max:255|unique:shop_promo_codes,code,' . $promoCode->id,
                'description' => 'nullable|string|max:1000',
                'type' => 'required|in:percentage,fixed',
                'value' => 'required|numeric|min:0',
                'min_amount' => 'nullable|numeric|min:0',
                'uses' => 'nullable|integer|min:0',
                'max_uses' => 'nullable|integer|min:0',
                'status' => 'required|in:active,scheduled,disabled',
                'valid_until' => 'nullable|date',
            ]);

            $promoCode->update([
                'reference' => $validated['reference'] ?? $promoCode->reference,
                'code' => strtoupper($validated['code']),
                'description' => $validated['description'] ?? null,
                'type' => $validated['type'],
                'value' => $validated['value'],
                'min_amount' => $validated['min_amount'] ?? 0,
                'uses' => $validated['uses'] ?? 0,
                'max_uses' => $validated['max_uses'] ?? 0,
                'status' => $validated['status'],
                'valid_until' => $validated['valid_until'] ?? null,
            ]);

            return response()->json(['message' => 'Code promo mis à jour', 'data' => $promoCode]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[ShopPromoCodeController@update] Error', ['id' => $promoCode->id, 'message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la mise à jour du code promo'], 500);
        }
    }

    public function destroy(Request $request, ShopPromoCode $promoCode): JsonResponse
    {
        try {
            if ($entityId = $this->currentEntityId($request)) {
                abort_unless((int) $promoCode->entity_id === (int) $entityId, 404);
            }

            $promoCode->delete();

            return response()->json(['message' => 'Code promo supprimé']);
        } catch (\Exception $e) {
            Log::error('[ShopPromoCodeController@destroy] Error', ['id' => $promoCode->id, 'message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la suppression du code promo'], 500);
        }
    }
}
