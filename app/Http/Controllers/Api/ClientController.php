<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Card;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ClientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = User::query();
            
            // Recherche par nom ou email
            if ($request->search) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }
            
            // Pagination
            $perPage = $request->get('per_page', 15);
            $clients = $query->with('card')
                           ->orderBy('created_at', 'desc')
                           ->paginate($perPage);

            return response()->json([
                'data' => $clients->items(),
                'meta' => [
                    'current_page' => $clients->currentPage(),
                    'last_page' => $clients->lastPage(),
                    'per_page' => $clients->perPage(),
                    'total' => $clients->total(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('[ClientController@index] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement des clients'], 500);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $client = User::with(['card.cardCredits', 'orders'])
                        ->findOrFail($id);
            
            return response()->json(['data' => $client]);
        } catch (\Exception $e) {
            Log::error('[ClientController@show] Error', ['id' => $id, 'message' => $e->getMessage()]);
            return response()->json(['message' => 'Client non trouvé'], 404);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'phone' => 'required|string|max:20',
                'address' => 'required|string|max:500',
                'password' => 'nullable|string|min:6',
            ]);

            // Créer le client
            $client = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'address' => $validated['address'],
                'password' => $validated['password'] ? Hash::make($validated['password']) : Hash::make('password123'),
            ]);

            // Créer la carte de fidélité
            $card = Card::create([
                'user_id' => $client->id,
                'entity_id' => 1, // ID de l'entité pharmacie
                'card_type_id' => 1, // Type de carte par défaut
                'number' => 'CARD-' . str_pad($client->id, 8, '0', STR_PAD_LEFT),
                'points' => 0,
                'status' => 'active',
            ]);

            Log::info('[ClientController@store] Client created', ['client_id' => $client->id]);

            return response()->json([
                'message' => 'Client créé avec succès',
                'data' => $client->load('card')
            ], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[ClientController@store] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la création du client'], 500);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $client = User::findOrFail($id);
            
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email,' . $id,
                'phone' => 'required|string|max:20',
                'address' => 'required|string|max:500',
            ]);

            $client->update($validated);

            Log::info('[ClientController@update] Client updated', ['client_id' => $client->id]);

            return response()->json([
                'message' => 'Client modifié avec succès',
                'data' => $client
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[ClientController@update] Error', ['id' => $id, 'message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la modification du client'], 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $client = User::findOrFail($id);
            
            // Vérifier si le client a des commandes
            if ($client->orders()->count() > 0) {
                return response()->json([
                    'message' => 'Impossible de supprimer ce client car il a des commandes associées'
                ], 422);
            }

            // Supprimer la carte associée
            if ($client->card) {
                $client->card->delete();
            }

            $client->delete();

            Log::info('[ClientController@destroy] Client deleted', ['client_id' => $id]);

            return response()->json(['message' => 'Client supprimé avec succès']);
        } catch (\Exception $e) {
            Log::error('[ClientController@destroy] Error', ['id' => $id, 'message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la suppression du client'], 500);
        }
    }

    public function getStats(): JsonResponse
    {
        try {
            $stats = [
                'total_clients' => User::count(),
                'active_clients' => User::whereHas('card', function($query) {
                    $query->where('status', 'active');
                })->count(),
                'total_points' => Card::sum('points'),
                'new_this_month' => User::whereMonth('created_at', now()->month)
                                       ->whereYear('created_at', now()->year)
                                       ->count(),
            ];

            return response()->json(['data' => $stats]);
        } catch (\Exception $e) {
            Log::error('[ClientController@getStats] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement des statistiques'], 500);
        }
    }
}
