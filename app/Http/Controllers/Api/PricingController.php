<?php

namespace App\Http\Controllers\Api;

use App\Models\Pricing;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class PricingController extends Controller
{
    public function index(): JsonResponse
    {
        $pricings = Pricing::latest()->get();
        return response()->json(['data' => $pricings]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string|max:500',
            'amount' => 'required|numeric|min:0',
            'duration' => 'required|integer|min:1',
        ]);

        $pricing = Pricing::create($validated);

        return response()->json([
            'message' => 'Pricing créé avec succès.',
            'data' => $pricing,
        ], 201);
    }

    public function update(Request $request, Pricing $pricing): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|max:500',
            'amount' => 'sometimes|numeric|min:0',
            'duration' => 'sometimes|integer|min:1',
        ]);

        $pricing->update($validated);

        return response()->json([
            'message' => 'Pricing mis à jour.',
            'data' => $pricing,
        ]);
    }

    public function destroy(Pricing $pricing): JsonResponse
    {
        $pricing->delete();
        return response()->json(['message' => 'Pricing supprimé.']);
    }
}
