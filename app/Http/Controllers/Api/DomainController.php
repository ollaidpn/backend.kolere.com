<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class DomainController extends Controller
{
    public function index(): JsonResponse
    {
        Log::info('[DomainController@index] Fetching domains');
        try {
            $domains = Domain::orderBy('name')->get();
            Log::info('[DomainController@index] Success', ['count' => $domains->count()]);
            return response()->json(['data' => $domains]);
        } catch (\Exception $e) {
            Log::error('[DomainController@index] Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }
}
