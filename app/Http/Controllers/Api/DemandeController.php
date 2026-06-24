<?php

namespace App\Http\Controllers\Api;

use App\Models\Demande;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DemandeController extends Controller
{
    private function entityId(Request $request): ?int
    {
        return $request->attributes->get('current_entity_id');
    }

    private function formatDemande(Demande $d): array
    {
        return [
            'id'             => $d->id,
            'description'    => $d->description,
            'photo_url'      => $d->photo ? url(Storage::url($d->photo)) : null,
            'status'         => $d->status,
            'manager_comment'=> $d->manager_comment,
            'manager_amount' => $d->manager_amount,
            'responded_at'   => $d->responded_at,
            'created_at'     => $d->created_at,
            'client'         => $d->user ? [
                'id'    => $d->user->id,
                'name'  => $d->user->name,
                'email' => $d->user->email,
                'phone' => $d->user->phone,
            ] : null,
        ];
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $query = Demande::with('user')->orderBy('created_at', 'desc');
            if ($entityId = $this->entityId($request)) {
                $query->where('entity_id', $entityId);
            }

            if ($request->search) {
                $search = $request->search;
                $query->whereHas('user', fn($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                );
            }
            if ($request->status) {
                $query->where('status', $request->status);
            }

            $perPage = $request->get('per_page', 20);
            $items   = $query->paginate($perPage);

            return response()->json([
                'data' => collect($items->items())->map(fn($d) => $this->formatDemande($d)),
                'meta' => [
                    'current_page' => $items->currentPage(),
                    'last_page'    => $items->lastPage(),
                    'total'        => $items->total(),
                ],
                'counts' => [
                    'pending'     => (clone $query)->where('status', 'pending')->count(),
                    'available'   => (clone $query)->where('status', 'available')->count(),
                    'unavailable' => (clone $query)->where('status', 'unavailable')->count(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('[DemandeController@index]', ['msg' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur'], 500);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $query = Demande::with('user');
            if ($entityId = request()->attributes->get('current_entity_id')) {
                $query->where('entity_id', $entityId);
            }
            $d = $query->findOrFail($id);
            return response()->json(['data' => $this->formatDemande($d)]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Demande introuvable'], 404);
        }
    }

    public function respond(Request $request, $id): JsonResponse
    {
        try {
            $query = Demande::query();
            if ($entityId = $this->entityId($request)) {
                $query->where('entity_id', $entityId);
            }
            $d = $query->findOrFail($id);

            $validated = $request->validate([
                'status'          => 'required|in:available,unavailable',
                'manager_comment' => 'nullable|string|max:1000',
                'manager_amount'  => 'nullable|numeric|min:0',
            ]);

            $d->update([
                'status'          => $validated['status'],
                'manager_comment' => $validated['manager_comment'] ?? null,
                'manager_amount'  => $validated['manager_amount'] ?? null,
                'responded_at'    => now(),
            ]);

            Log::info('[DemandeController@respond]', ['id' => $id, 'status' => $validated['status']]);

            return response()->json(['message' => 'Réponse envoyée', 'data' => $this->formatDemande($d->fresh('user'))]);
        } catch (\Exception $e) {
            Log::error('[DemandeController@respond]', ['msg' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la réponse'], 500);
        }
    }
}
