<?php

namespace App\Http\Controllers\Api;

use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class TermController extends Controller
{
    public function index(): JsonResponse
    {
        $terms = Term::latest()->get();
        return response()->json(['data' => $terms]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        $term = Term::create($validated);

        return response()->json([
            'message' => 'Document créé avec succès.',
            'data' => $term,
        ], 201);
    }

    public function update(Request $request, Term $term): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
        ]);

        $term->update($validated);

        return response()->json([
            'message' => 'Document mis à jour.',
            'data' => $term,
        ]);
    }

    public function destroy(Term $term): JsonResponse
    {
        $term->delete();
        return response()->json(['message' => 'Document supprimé.']);
    }
}
