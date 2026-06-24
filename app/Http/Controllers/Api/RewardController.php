<?php

namespace App\Http\Controllers\Api;

use App\Models\Reward;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class RewardController extends Controller
{
    private function currentEntityId(Request $request): ?int
    {
        return $request->attributes->get('current_entity_id');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $query = Reward::query();
            if ($entityId = $this->currentEntityId($request)) {
                $query->where('entity_id', $entityId);
            }

            if ($request->status) {
                $query->where('status', $request->status);
            }

            if ($request->search) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            $rewards = $query->orderBy('points_required')->get();

            return response()->json(['data' => $rewards]);
        } catch (\Exception $e) {
            Log::error('[RewardController@index] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement des récompenses'], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'points_required' => 'required|integer|min:1',
                'value' => 'required|integer|min:0',
                'stock' => 'nullable|integer|min:0',
                'status' => 'in:active,inactive',
            ]);

            $entityId = $this->currentEntityId($request);
            if (!$entityId) {
                return response()->json(['message' => 'Entité courante introuvable'], 422);
            }

            $reward = Reward::create($validated + ['entity_id' => $entityId]);

            Log::info('[RewardController@store] Reward created', ['reward_id' => $reward->id]);

            return response()->json(['message' => 'Récompense créée avec succès', 'data' => $reward], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[RewardController@store] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la création de la récompense'], 500);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $query = Reward::query()->whereKey($id);
            if ($entityId = $this->currentEntityId($request)) {
                $query->where('entity_id', $entityId);
            }
            $reward = $query->firstOrFail();

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'points_required' => 'required|integer|min:1',
                'value' => 'required|integer|min:0',
                'stock' => 'nullable|integer|min:0',
                'status' => 'required|in:active,inactive',
            ]);

            $reward->update($validated);

            Log::info('[RewardController@update] Reward updated', ['reward_id' => $reward->id]);

            return response()->json(['message' => 'Récompense mise à jour', 'data' => $reward]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[RewardController@update] Error', ['id' => $id, 'message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la mise à jour'], 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $query = Reward::query()->whereKey($id);
            if ($entityId = request()->attributes->get('current_entity_id')) {
                $query->where('entity_id', $entityId);
            }
            $reward = $query->firstOrFail();
            $reward->delete();

            Log::info('[RewardController@destroy] Reward deleted', ['reward_id' => $id]);

            return response()->json(['message' => 'Récompense supprimée']);
        } catch (\Exception $e) {
            Log::error('[RewardController@destroy] Error', ['id' => $id, 'message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la suppression'], 500);
        }
    }

    public function toggleStatus($id): JsonResponse
    {
        try {
            $query = Reward::query()->whereKey($id);
            if ($entityId = request()->attributes->get('current_entity_id')) {
                $query->where('entity_id', $entityId);
            }
            $reward = $query->firstOrFail();
            $reward->status = $reward->status === 'active' ? 'inactive' : 'active';
            $reward->save();

            return response()->json(['message' => 'Statut mis à jour', 'data' => $reward]);
        } catch (\Exception $e) {
            Log::error('[RewardController@toggleStatus] Error', ['id' => $id, 'message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur'], 500);
        }
    }
}
