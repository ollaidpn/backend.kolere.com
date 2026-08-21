<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Entity;
use App\Services\FaykoPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShopPayoutController extends Controller
{
    private function currentEntityId(Request $request): ?int
    {
        return $request->attributes->get('current_entity_id');
    }

    private function getFaykoService(Request $request): FaykoPaymentService
    {
        $entityId = $this->currentEntityId($request);
        $entity = $entityId ? Entity::find($entityId) : null;

        $pubKey = $entity?->fayko_public_key ?: env('FAYKO_PUBLIC_KEY');
        $secKey = $entity?->fayko_secret_key ?: env('FAYKO_SECRET_KEY');
        $webKey = $entity?->fayko_webhook_key ?: env('FAYKO_WEBHOOK_KEY');

        return new FaykoPaymentService($pubKey, $secKey, $webKey);
    }


    /**
     * Récupère le solde agrégé et les sous-soldes Fayko
     */
    public function balance(Request $request): JsonResponse
    {
        try {
            $fayko = $this->getFaykoService($request);
            $res = $fayko->getBalance();

            return response()->json($res);
        } catch (\Throwable $e) {
            Log::error('[ShopPayoutController@balance] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => $e->getMessage() ?: 'Erreur lors de la récupération du solde Fayko'], 500);
        }
    }

    /**
     * Étape 1 du Payout : Initier le virement et recevoir la référence temporaire
     */
    public function initPayout(Request $request): JsonResponse
    {
        try {
            $fayko = $this->getFaykoService($request);
            $res = $fayko->initPayout();

            return response()->json($res);
        } catch (\Throwable $e) {
            Log::error('[ShopPayoutController@initPayout] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => $e->getMessage() ?: 'Erreur lors de l\'initialisation du virement'], 500);
        }
    }

    /**
     * Étape 2 du Payout : Confirmer et exécuter le virement vers Wave / Orange Money
     */
    public function requestPayout(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'reference' => 'required|string',
                'provider' => 'required|string', // wave_sn, orange_money_sn, etc.
                'amount' => 'required|numeric|min:100',
                'phone' => 'required|string',
                'ccphone' => 'nullable|string',
                'name' => 'required|string',
                'email' => 'nullable|email',
                'note' => 'nullable|string',
            ]);

            $fayko = $this->getFaykoService($request);
            $res = $fayko->confirmPayout($validated);

            return response()->json($res);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('[ShopPayoutController@requestPayout] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => $e->getMessage() ?: 'Erreur lors de la demande de virement'], 500);
        }
    }

    /**
     * Lister l'historique des retraits effectués via Fayko
     */
    public function listPayouts(Request $request): JsonResponse
    {
        try {
            $fayko = $this->getFaykoService($request);
            $res = $fayko->listPayouts();

            return response()->json($res);
        } catch (\Throwable $e) {
            Log::error('[ShopPayoutController@listPayouts] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => $e->getMessage() ?: 'Erreur lors du chargement de l\'historique des retraits'], 500);
        }
    }

    /**
     * Obtenir la liste dynamique des opérateurs disponibles pour le Checkout Fayko
     */
    public function checkoutProviders(Request $request): JsonResponse
    {
        try {
            $fayko = $this->getFaykoService($request);
            $providers = $fayko->getCheckoutProviders();

            return response()->json(['data' => $providers]);
        } catch (\Throwable $e) {
            Log::error('[ShopPayoutController@checkoutProviders] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement des opérateurs de paiement'], 500);
        }
    }

    /**
     * Obtenir la liste dynamique des opérateurs disponibles pour le Payout Fayko
     */
    public function payoutProviders(Request $request): JsonResponse
    {
        try {
            $fayko = $this->getFaykoService($request);
            $providers = $fayko->getPayoutProviders();

            return response()->json(['data' => $providers]);
        } catch (\Throwable $e) {
            Log::error('[ShopPayoutController@payoutProviders] Error', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement des opérateurs de virement'], 500);
        }
    }
}

