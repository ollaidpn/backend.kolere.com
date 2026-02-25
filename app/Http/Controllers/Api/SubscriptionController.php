<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSuscription;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $subscriptions = AppSuscription::with(['entity.domain', 'pricing', 'appPayments'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $subscriptions]);
    }
}
