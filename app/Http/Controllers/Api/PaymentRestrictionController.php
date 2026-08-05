<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppInfo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentRestrictionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        try {
            $entityId = $request->attributes->get('current_entity_id');

            if (!$entityId) {
                return response()->json(['enabled' => false]);
            }

            $appInfo = AppInfo::firstOrCreate(
                ['entity_id' => $entityId],
                ['payment_restriction_enabled' => false]
            );

            return response()->json(['enabled' => (bool) $appInfo->payment_restriction_enabled]);
        } catch (\Throwable $e) {
            Log::error('[PaymentRestrictionController@show] Error', [
                'message' => $e->getMessage(),
            ]);

            return response()->json(['enabled' => false]);
        }
    }
}
