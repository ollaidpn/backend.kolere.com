<?php

namespace App\Http\Controllers\Api;

use App\Models\Pricing;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;

class PricingController extends Controller
{
    public function index(): JsonResponse
    {
        Log::info('[PricingController@index] Fetching pricings');
        try {
            $pricings = Pricing::latest()->get();
            Log::info('[PricingController@index] Success', ['count' => $pricings->count()]);
            return response()->json(['data' => $pricings]);
        } catch (\Exception $e) {
            Log::error('[PricingController@index] Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        Log::info('[PricingController@store] Creating pricing', ['name' => $request->name]);
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'required|string|max:500',
                'amount' => 'required|numeric|min:0',
                'duration' => 'required|integer|min:1',
            ]);

            $pricing = Pricing::create($validated);
            Log::info('[PricingController@store] Success', ['pricing_id' => $pricing->id]);

            return response()->json([
                'message' => 'Pricing créé avec succès.',
                'data' => $pricing,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[PricingController@store] Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }

    public function update(Request $request, Pricing $pricing): JsonResponse
    {
        Log::info('[PricingController@update] Updating pricing', ['pricing_id' => $pricing->id]);
        try {
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'description' => 'sometimes|string|max:500',
                'amount' => 'sometimes|numeric|min:0',
                'duration' => 'sometimes|integer|min:1',
            ]);

            $pricing->update($validated);
            Log::info('[PricingController@update] Success');

            return response()->json([
                'message' => 'Pricing mis à jour.',
                'data' => $pricing,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[PricingController@update] Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }

    public function destroy(Pricing $pricing): JsonResponse
    {
        Log::info('[PricingController@destroy] Deleting pricing', ['pricing_id' => $pricing->id]);
        try {
            $pricing->delete();
            Log::info('[PricingController@destroy] Success');
            return response()->json(['message' => 'Pricing supprimé.']);
        } catch (\Exception $e) {
            Log::error('[PricingController@destroy] Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }
}
