<?php

namespace App\Http\Controllers\Api;

use App\Models\Demande;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ClientDemandeController extends Controller
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
        ];
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $query = Demande::where('user_id', $user->id);
            if ($entityId = $this->entityId($request)) {
                $query->where('entity_id', $entityId);
            }
            $demandes = $query
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(fn($d) => $this->formatDemande($d));

            return response()->json(['data' => $demandes]);
        } catch (\Exception $e) {
            Log::error('[ClientDemandeController@index]', ['msg' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur'], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'description' => 'nullable|string|max:1000',
                'photo'       => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            ]);

            $photoPath = null;
            if ($request->hasFile('photo')) {
                $photoPath = $request->file('photo')->store('demandes', 'public');
            }

            if (!$validated['description'] && !$photoPath) {
                return response()->json(['message' => 'Veuillez ajouter une description ou une photo'], 422);
            }

            $entityId = $this->entityId($request);
            if (!$entityId) {
                return response()->json(['message' => 'Entité courante introuvable'], 422);
            }

            $demande = Demande::create([
                'user_id'     => $request->user()->id,
                'entity_id'   => $entityId,
                'description' => $validated['description'] ?? null,
                'photo'       => $photoPath,
                'status'      => 'pending',
            ]);

            Log::info('[ClientDemandeController@store]', ['demande_id' => $demande->id]);

            return response()->json([
                'message' => 'Demande envoyée avec succès',
                'data'    => $this->formatDemande($demande),
            ], 201);
        } catch (\Exception $e) {
            Log::error('[ClientDemandeController@store]', ['msg' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de l\'envoi de la demande'], 500);
        }
    }

    public function show(Request $request, $id): JsonResponse
    {
        try {
            $query = Demande::where('user_id', $request->user()->id);
            if ($entityId = $this->entityId($request)) {
                $query->where('entity_id', $entityId);
            }
            $d = $query->findOrFail($id);
            return response()->json(['data' => $this->formatDemande($d)]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Demande introuvable'], 404);
        }
    }
}
