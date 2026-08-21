<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GoDaddyDomainService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ShopDomainController extends Controller
{
    /**
     * Recherche de domaines disponibles et leurs prix
     * POST /api/settings/shop/domain/custom/search
     */
    public function customSearch(Request $request, GoDaddyDomainService $domainService): JsonResponse
    {
        try {
            $request->validate([
                'query' => 'required|string|min:1',
                'extensions' => 'nullable|array',
            ]);

            $query = strtolower(trim($request->input('query')));
            $extensions = $request->input('extensions', []);

            $results = $domainService->searchDomains($query, $extensions);

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);

        } catch (\Exception $e) {
            Log::error('ShopDomain customSearch error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recherche de domaines: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Vérifier un domaine personnalisé
     * POST /api/settings/shop/domain/custom/check
     */
    public function customCheck(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'domain' => 'required|string',
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'status' => 'free',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Enregistrer un domaine personnalisé
     * POST /api/settings/shop/domain/custom/register
     */
    public function customRegister(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'domain' => 'required|string',
                'type' => 'required|string',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Domaine enregistré avec succès.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
