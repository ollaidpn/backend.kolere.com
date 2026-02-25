<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        Log::info('[PaymentController@index] Fetching payments');
        try {
            $payments = AppPayment::with([
                'appSuscription.entity.domain',
                'appSuscription.pricing',
                'appOrder.entity.domain',
            ])
                ->orderBy('created_at', 'desc')
                ->get();

            Log::info('[PaymentController@index] Success', ['count' => $payments->count()]);
            return response()->json(['data' => $payments]);
        } catch (\Exception $e) {
            Log::error('[PaymentController@index] Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }
}
