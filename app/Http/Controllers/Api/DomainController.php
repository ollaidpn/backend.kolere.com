<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use Illuminate\Http\JsonResponse;

class DomainController extends Controller
{
    public function index(): JsonResponse
    {
        $domains = Domain::orderBy('name')->get();

        return response()->json(['data' => $domains]);
    }
}
