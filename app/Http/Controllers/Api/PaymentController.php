<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppPayment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $payments = AppPayment::with([
            'appSuscription.entity.domain',
            'appSuscription.pricing',
            'appOrder.entity.domain',
        ])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $payments]);
    }
}
