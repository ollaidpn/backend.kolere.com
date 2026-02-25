<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DomainController;
use App\Http\Controllers\Api\EntityController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\PricingController;
use App\Http\Controllers\Api\TermController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AdminInvitationController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\DashboardController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ─── Authentification (publiques) ────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/client/login', [AuthController::class, 'loginClient']);
    Route::post('/manager/login', [AuthController::class, 'loginManager']);
    Route::post('/admin/login', [AuthController::class, 'loginAdmin']);
});

// ─── Invitations (publiques) ─────────────────────────────────────────────────
Route::prefix('invitations')->group(function () {
    Route::get('/{token}', [InvitationController::class, 'show']);
    Route::post('/{token}/accept', [InvitationController::class, 'accept']);
    Route::post('/{token}/refuse', [InvitationController::class, 'refuse']);
});

// ─── Invitations Admin (publiques) ──────────────────────────────────────────
Route::prefix('admin-invitations')->group(function () {
    Route::get('/{token}', [AdminInvitationController::class, 'show']);
    Route::post('/{token}/accept', [AdminInvitationController::class, 'accept']);
    Route::post('/{token}/refuse', [AdminInvitationController::class, 'refuse']);
});

// ─── Espace Admin (table admins) ─────────────────────────────────────────────
Route::prefix('admin')->group(function () {
    Route::get('/me', function (Request $request) {
        return response()->json(['data' => $request->user()]);
    })->middleware('auth:sanctum');

    // Domaines
    Route::get('/domains', [DomainController::class, 'index']);

    // Boutiques (Entities)
    Route::get('/entities', [EntityController::class, 'index']);
    Route::post('/entities', [EntityController::class, 'store']);
    Route::get('/entities/{entity}', [EntityController::class, 'show']);
    Route::put('/entities/{entity}', [EntityController::class, 'update']);
    Route::delete('/entities/{entity}', [EntityController::class, 'destroy']);

    // Invitations
    Route::post('/invitations', [InvitationController::class, 'store']);

    // Pricings
    Route::get('/pricings', [PricingController::class, 'index']);
    Route::post('/pricings', [PricingController::class, 'store']);
    Route::put('/pricings/{pricing}', [PricingController::class, 'update']);
    Route::delete('/pricings/{pricing}', [PricingController::class, 'destroy']);

    // Terms & Conditions
    Route::get('/terms', [TermController::class, 'index']);
    Route::post('/terms', [TermController::class, 'store']);
    Route::put('/terms/{term}', [TermController::class, 'update']);
    Route::delete('/terms/{term}', [TermController::class, 'destroy']);

    // Admin Users
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::post('/users', [AdminUserController::class, 'store']);
    Route::put('/users/{admin}', [AdminUserController::class, 'update']);
    Route::patch('/users/{admin}/toggle-status', [AdminUserController::class, 'toggleStatus']);
    Route::post('/users/{admin}/reset-password', [AdminUserController::class, 'resetPassword']);
    Route::delete('/users/{admin}', [AdminUserController::class, 'destroy']);

    // Subscriptions
    Route::get('/subscriptions', [SubscriptionController::class, 'index']);

    // Payments
    Route::get('/payments', [PaymentController::class, 'index']);

    // Dashboard stats
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
});

// ─── Espace Manager (table managers) ─────────────────────────────────────────
Route::prefix('manager')->group(function () {
    Route::get('/me', function (Request $request) {
        return response()->json(['data' => $request->user()]);
    })->middleware('auth:sanctum');
});

// ─── Espace Client (table users) ────────────────────────────────────────────
Route::prefix('client')->group(function () {
    Route::get('/me', function (Request $request) {
        return response()->json(['data' => $request->user()]);
    })->middleware('auth:sanctum');
});
