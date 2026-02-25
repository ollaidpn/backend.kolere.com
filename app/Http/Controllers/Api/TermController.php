<?php

namespace App\Http\Controllers\Api;

use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;

class TermController extends Controller
{
    public function index(): JsonResponse
    {
        Log::info('[TermController@index] Fetching terms');
        try {
            $terms = Term::latest()->get();
            Log::info('[TermController@index] Success', ['count' => $terms->count()]);
            return response()->json(['data' => $terms]);
        } catch (\Exception $e) {
            Log::error('[TermController@index] Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        Log::info('[TermController@store] Creating term', ['title' => $request->title]);
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'content' => 'required|string',
            ]);

            $term = Term::create($validated);
            Log::info('[TermController@store] Success', ['term_id' => $term->id]);

            return response()->json([
                'message' => 'Document créé avec succès.',
                'data' => $term,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[TermController@store] Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }

    public function update(Request $request, Term $term): JsonResponse
    {
        Log::info('[TermController@update] Updating term', ['term_id' => $term->id]);
        try {
            $validated = $request->validate([
                'title' => 'sometimes|string|max:255',
                'content' => 'sometimes|string',
            ]);

            $term->update($validated);
            Log::info('[TermController@update] Success');

            return response()->json([
                'message' => 'Document mis à jour.',
                'data' => $term,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[TermController@update] Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }

    public function destroy(Term $term): JsonResponse
    {
        Log::info('[TermController@destroy] Deleting term', ['term_id' => $term->id]);
        try {
            $term->delete();
            Log::info('[TermController@destroy] Success');
            return response()->json(['message' => 'Document supprimé.']);
        } catch (\Exception $e) {
            Log::error('[TermController@destroy] Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }
}
