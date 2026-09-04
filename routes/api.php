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
use App\Http\Controllers\Api\AdminAppOrderController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\CardController;
use App\Http\Controllers\Api\BackofficeDashboardController;
use App\Http\Controllers\Api\ClientHistoryController;
use App\Http\Controllers\Api\ClientPointsController;
use App\Http\Controllers\Api\ClientPromotionsController;
use App\Http\Controllers\Api\ClientProfileController;
use App\Http\Controllers\Api\RewardController;
use App\Http\Controllers\Api\ConversionController;
use App\Http\Controllers\Api\DemandeController;
use App\Http\Controllers\Api\ClientDemandeController;
use App\Http\Controllers\Api\BackofficeEntityController;
use App\Http\Controllers\Api\ShopItemController;
use App\Http\Controllers\Api\ShopCategoryController;
use App\Http\Controllers\Api\ShopBrandController;
use App\Http\Controllers\Api\ShopPromoCodeController;
use App\Http\Controllers\Api\ShopPaymentController;
use App\Http\Controllers\Api\ShopOrderController;
use App\Http\Controllers\Api\PaymentRestrictionController;
use App\Http\Controllers\Api\AppVersionController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\BackofficeNotificationController;
use App\Http\Controllers\Api\ClientNotificationController;
use App\Http\Controllers\Api\BackofficeRbacController;
use App\Services\RbacService;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ─── Authentification (publiques) ────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/client/register', [AuthController::class, 'registerClient']);
    Route::post('/client/register/request-otp', [AuthController::class, 'requestClientRegistrationOtp']);
    Route::post('/client/register/verify-otp', [AuthController::class, 'verifyClientRegistrationOtp']);
    Route::post('/client/register/confirm', [AuthController::class, 'confirmClientRegistration']);
    Route::post('/client/login', [AuthController::class, 'loginClient']);
    Route::post('/client/claim-card', [AuthController::class, 'claimClientCard']);
    Route::post('/client/forgot/request-otp', [AuthController::class, 'requestClientPasswordResetOtp']);
    Route::post('/client/forgot/reset', [AuthController::class, 'resetClientPassword']);
    Route::post('/phone/request-otp', [AuthController::class, 'requestPhoneOtp']);
    Route::post('/phone/verify-otp', [AuthController::class, 'verifyPhoneOtp']);
    Route::post('/phone/select-account', [AuthController::class, 'selectPhoneAccount']);
    Route::post('/backoffice/login', [AuthController::class, 'loginManager']);

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

// ─── Résolution publique de boutique ────────────────────────────────────────
Route::get('/entities/resolve', [EntityController::class, 'resolve']);
Route::get('/app/version-check', [AppVersionController::class, 'check']);

// ─── Boutique Publique (sans auth) ──────────────────────────────────────────
Route::prefix('shop')->middleware(['resolve.entity'])->group(function () {
    Route::get('/items', [ShopItemController::class, 'index']);
    Route::get('/categories', [ShopCategoryController::class, 'index']);
    Route::get('/brands', [ShopBrandController::class, 'index']);
    Route::post('/orders', [ShopOrderController::class, 'storePublic']);
    Route::post('/orders/check', [ShopOrderController::class, 'checkPublic']);
    Route::get('/payment-log/check', [ShopOrderController::class, 'checkPaymentLog']);
    Route::post('/orders/cancel', [ShopOrderController::class, 'cancelPublic']);

    Route::post('/promo-codes/check', [ShopPromoCodeController::class, 'checkPublic']);
});



Route::post('/webhook/fayko', [ShopOrderController::class, 'webhookFayko']);

// ─── Espace Admin (table admins) ─────────────────────────────────────────────
Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('/me', function (Request $request) {
        return response()->json(['data' => $request->user()]);
    });

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
    Route::get('/app-orders', [AdminAppOrderController::class, 'index']);

    // Dashboard stats
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
});

// ─── Espace Backoffice (pharmacie) ───────────────────────────────────────────
Route::prefix('backoffice')->middleware(['auth:sanctum', 'role:manager', 'resolve.entity'])->group(function () {
    Route::get('/me', function (Request $request) {
        return response()->json([
            'data' => app(RbacService::class)->managerPayload($request->user()),
        ]);
    });
    Route::put('/me/profile', [AuthController::class, 'updateManagerProfile']);
    Route::put('/me/password', [AuthController::class, 'updateManagerPassword']);


    // Invitations managers
    Route::post('/invitations', [InvitationController::class, 'store']);

    // Clients
    Route::get('/clients/stats', [ClientController::class, 'getStats']);
    Route::get('/clients', [ClientController::class, 'index']);
    Route::post('/clients', [ClientController::class, 'store']);
    Route::get('/clients/{id}', [ClientController::class, 'show']);
    Route::put('/clients/{id}', [ClientController::class, 'update']);
    Route::delete('/clients/{id}', [ClientController::class, 'destroy']);

    // Ventes
    Route::get('/sales/stats', [SaleController::class, 'getStats']);
    Route::get('/sales/recent', [SaleController::class, 'getRecentSales']);
    Route::get('/sales', [SaleController::class, 'index']);
    Route::post('/sales', [SaleController::class, 'store']);
    Route::get('/sales/{id}', [SaleController::class, 'show']);

    // Cartes de fidélité
    Route::get('/cards/scan/{reference}', [CardController::class, 'scanByReference']);
    Route::get('/cards/scan-by-phone', [CardController::class, 'scanByPhone']);
    Route::get('/cards/stats', [CardController::class, 'getStats']);
    Route::get('/cards', [CardController::class, 'index']);
    Route::post('/cards', [CardController::class, 'store']);
    Route::get('/cards/{id}', [CardController::class, 'show']);
    Route::get('/cards/{id}/history', [CardController::class, 'getHistory']);
    Route::put('/cards/{id}/add-points', [CardController::class, 'addPoints']);
    Route::put('/cards/{id}/redeem-points', [CardController::class, 'redeemPoints']);
    Route::post('/cards/{id}/toggle-status', [CardController::class, 'toggleStatus']);


    // Conversions (échange de points)
    Route::get('/conversions/stats', [ConversionController::class, 'stats']);
    Route::get('/conversions', [ConversionController::class, 'index']);
    Route::post('/conversions', [ConversionController::class, 'store']);

    // Récompenses
    Route::get('/rewards', [RewardController::class, 'index']);
    Route::post('/rewards', [RewardController::class, 'store']);
    Route::put('/rewards/{id}', [RewardController::class, 'update']);
    Route::post('/rewards/{id}', [RewardController::class, 'update']);
    Route::delete('/rewards/{id}', [RewardController::class, 'destroy']);
    Route::patch('/rewards/{id}/toggle-status', [RewardController::class, 'toggleStatus']);

    // Campagnes de notifications
    Route::get('/campaigns/sms-balance', [\App\Http\Controllers\Api\CampaignController::class, 'getSmsBalance']);
    Route::get('/campaigns/sms-packages', [\App\Http\Controllers\Api\CampaignController::class, 'getSmsPackages']);
    Route::post('/campaigns/sms-packages/buy', [\App\Http\Controllers\Api\CampaignController::class, 'buySmsPackage']);
    Route::get('/campaigns/sms-purchases', [\App\Http\Controllers\Api\CampaignController::class, 'getSmsPurchases']);
    Route::get('/campaigns', [\App\Http\Controllers\Api\CampaignController::class, 'index']);
    Route::post('/campaigns', [\App\Http\Controllers\Api\CampaignController::class, 'store']);
    Route::post('/campaigns/{id}/retry', [\App\Http\Controllers\Api\CampaignController::class, 'retry']);



    Route::get('/notifications', [BackofficeNotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [BackofficeNotificationController::class, 'markRead']);

    // RBAC boutique
    Route::prefix('rbac')->group(function () {
        Route::get('/catalog', [BackofficeRbacController::class, 'catalog'])->middleware('permission:backoffice.settings.roles.read');
        Route::get('/roles', [BackofficeRbacController::class, 'roles'])->middleware('permission:backoffice.settings.roles.read');
        Route::post('/roles', [BackofficeRbacController::class, 'storeRole'])->middleware('permission:backoffice.settings.roles.create');
        Route::put('/roles/{role}', [BackofficeRbacController::class, 'updateRole'])->middleware('permission:backoffice.settings.roles.update');
        Route::delete('/roles/{role}', [BackofficeRbacController::class, 'destroyRole'])->middleware('permission:backoffice.settings.roles.delete');
        Route::get('/managers', [BackofficeRbacController::class, 'managers'])->middleware('permission:backoffice.settings.users.read');
        Route::patch('/managers/{manager}/role', [BackofficeRbacController::class, 'assignManagerRole'])->middleware('permission:backoffice.settings.users.assign_role');
        Route::get('/me', [BackofficeRbacController::class, 'me']);
    });

    // Dashboard backoffice
    Route::get('/dashboard/stats', [BackofficeDashboardController::class, 'getStats']);
    Route::get('/dashboard/manager-stats', [BackofficeDashboardController::class, 'getManagerStats']);
    Route::get('/dashboard/quick-stats', [BackofficeDashboardController::class, 'getQuickStats']);

    Route::get('/payment-restriction', [PaymentRestrictionController::class, 'show']);

    // Paramètres entité (pharmacie)
    Route::get('/entity', [BackofficeEntityController::class, 'show']);
    Route::post('/entity', [BackofficeEntityController::class, 'update']);
    Route::post('/entity/domain-request', [BackofficeEntityController::class, 'requestDomainActivation']);
    Route::post('/uploads', [UploadController::class, 'store']);


    // Boutique - articles
    Route::get('/shop/items', [ShopItemController::class, 'index']);
    Route::post('/shop/items', [ShopItemController::class, 'store']);
    Route::get('/shop/items/{item}', [ShopItemController::class, 'show']);
    Route::put('/shop/items/{item}', [ShopItemController::class, 'update']);
    Route::delete('/shop/items/{item}', [ShopItemController::class, 'destroy']);

    Route::get('/shop/categories', [ShopCategoryController::class, 'index']);
    Route::post('/shop/categories', [ShopCategoryController::class, 'store']);
    Route::put('/shop/categories/{category}', [ShopCategoryController::class, 'update']);
    Route::delete('/shop/categories/{category}', [ShopCategoryController::class, 'destroy']);

    Route::get('/shop/brands', [ShopBrandController::class, 'index']);
    Route::post('/shop/brands', [ShopBrandController::class, 'store']);
    Route::put('/shop/brands/{brand}', [ShopBrandController::class, 'update']);
    Route::delete('/shop/brands/{brand}', [ShopBrandController::class, 'destroy']);

    Route::get('/shop/promo-codes', [ShopPromoCodeController::class, 'index']);
    Route::post('/shop/promo-codes', [ShopPromoCodeController::class, 'store']);
    Route::put('/shop/promo-codes/{promoCode}', [ShopPromoCodeController::class, 'update']);
    Route::delete('/shop/promo-codes/{promoCode}', [ShopPromoCodeController::class, 'destroy']);

    Route::get('/shop/orders', [ShopOrderController::class, 'index']);
    Route::get('/shop/orders/{order}', [ShopOrderController::class, 'show']);
    Route::put('/shop/orders/{order}', [ShopOrderController::class, 'update']);

    Route::get('/shop/payments', [ShopPaymentController::class, 'index']);

    // Route publique des moyens de paiement Fayko disponibles
    Route::get('/shop/payment-providers', [\App\Http\Controllers\Api\ShopPayoutController::class, 'checkoutProviders']);

    // Module Retraits (Fayko Payouts & Solde)

    Route::get('/shop/payouts/balance', [\App\Http\Controllers\Api\ShopPayoutController::class, 'balance']);
    Route::post('/shop/payouts/init', [\App\Http\Controllers\Api\ShopPayoutController::class, 'initPayout']);
    Route::post('/shop/payouts/request', [\App\Http\Controllers\Api\ShopPayoutController::class, 'requestPayout']);
    Route::get('/shop/payouts', [\App\Http\Controllers\Api\ShopPayoutController::class, 'listPayouts']);


    // Demandes clients
    Route::get('/demandes', [DemandeController::class, 'index']);
    Route::get('/demandes/{id}', [DemandeController::class, 'show']);
    Route::post('/demandes/{id}/respond', [DemandeController::class, 'respond']);

    // Domaines personnalisés
    Route::prefix('settings/shop/domain')->group(function () {
        Route::post('/custom/search', [\App\Http\Controllers\Api\ShopDomainController::class, 'customSearch']);
        Route::post('/custom/check', [\App\Http\Controllers\Api\ShopDomainController::class, 'customCheck']);
        Route::post('/custom/register', [\App\Http\Controllers\Api\ShopDomainController::class, 'customRegister']);
    });
});

// ─── Espace Manager (table managers) ─────────────────────────────────────────
Route::prefix('manager')->middleware(['auth:sanctum', 'role:manager'])->group(function () {
    Route::get('/me', function (Request $request) {
        return response()->json([
            'data' => app(RbacService::class)->managerPayload($request->user()),
        ]);
    });
});

// ─── Espace Client (table users) ────────────────────────────────────────────
Route::prefix('client')->middleware(['auth:sanctum', 'role:client', 'resolve.entity'])->group(function () {
    Route::get('/me', function (Request $request) {
        return response()->json(['data' => $request->user()]);
    });
    
    // Historique d'achats
    Route::get('/history', [ClientHistoryController::class, 'index']);
    Route::get('/history/stats', [ClientHistoryController::class, 'getStats']);
    Route::get('/history/{id}', [ClientHistoryController::class, 'show']);
    
    // Points et récompenses
    Route::get('/points', [ClientPointsController::class, 'index']);
    Route::get('/points/rewards', [ClientPointsController::class, 'getRewards']);
    Route::post('/points/redeem/{id}', [ClientPointsController::class, 'redeemReward']);
    
    // Promotions
    Route::get('/promotions', [ClientPromotionsController::class, 'index']);
    Route::get('/promotions/featured', [ClientPromotionsController::class, 'getFeatured']);
    Route::get('/promotions/{id}', [ClientPromotionsController::class, 'show']);
    
    // Profil
    Route::get('/profile', [ClientProfileController::class, 'show']);
    Route::match(['post', 'put'], '/profile', [ClientProfileController::class, 'update']);
    Route::post('/profile/avatar', [ClientProfileController::class, 'updateAvatar']);
    Route::put('/profile/password', [ClientProfileController::class, 'updatePassword']);
    Route::put('/profile/email', [ClientProfileController::class, 'updateEmail']);
    Route::post('/profile/delete/request-otp', [ClientProfileController::class, 'requestDeleteOtp']);
    Route::delete('/profile', [ClientProfileController::class, 'deleteAccount']);
    Route::get('/profile/preferences', [ClientProfileController::class, 'getPreferences']);
    Route::put('/profile/preferences', [ClientProfileController::class, 'updatePreferences']);
    Route::get('/notifications', [ClientNotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [ClientNotificationController::class, 'markRead']);

    // Demandes (ordonnances)
    Route::get('/demandes', [ClientDemandeController::class, 'index']);
    Route::post('/demandes', [ClientDemandeController::class, 'store']);
    Route::get('/demandes/{id}', [ClientDemandeController::class, 'show']);
});

// ─── FCM Push Tokens ────────────────────────────────────────────────────────
Route::post('/fcm/save-token', [\App\Http\Controllers\Api\FirebaseTokenController::class, 'store']);
Route::post('/fcm/check-token', [\App\Http\Controllers\Api\FirebaseTokenController::class, 'check']);
Route::post('/fcm/deactivate-token', [\App\Http\Controllers\Api\FirebaseTokenController::class, 'destroy']);
