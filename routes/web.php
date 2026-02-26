<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GhlOAuthController;
use App\Http\Controllers\ProviderConfigController;
use App\Http\Controllers\CheckoutController;
use App\Http\Middleware\AllowIframeEmbedding;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

Route::get('/', function (Request $request) {
    // Generate a secure CSRF state token for the OAuth flow
    $state = Str::random(40);
    $request->session()->put('oauth_state', $state);

    $clientId = config('services.ghl.client_id');

    // The version_id is the first part of the client_id before the hyphen
    $versionIdArr = explode('-', $clientId);
    $versionId = $versionIdArr[0] ?? '';

    $redirectUri = urlencode(url('/oauth/callback'));
    $scopes = 'payments/orders.readonly payments/orders.write payments/subscriptions.readonly payments/transactions.readonly payments/custom-provider.readonly payments/custom-provider.write products.readonly products/prices.readonly';
    $scopesEncoded = urlencode($scopes);

    // Standard GHL
    $standardUrl = "https://marketplace.gohighlevel.com/oauth/chooselocation?response_type=code&redirect_uri={$redirectUri}&client_id={$clientId}&scope={$scopesEncoded}&state={$state}&version_id={$versionId}";

    // White Label (LeadConnector)
    $whiteLabelUrl = "https://marketplace.leadconnectorhq.com/oauth/chooselocation?response_type=code&redirect_uri={$redirectUri}&client_id={$clientId}&scope={$scopesEncoded}&state={$state}&version_id={$versionId}";

    return view('welcome', [
        'standardUrl' => $standardUrl,
        'whiteLabelUrl' => $whiteLabelUrl,
    ]);
});

Route::get('/oauth/callback', [GhlOAuthController::class, 'callback']);

Route::middleware(['check.ghl.token'])->group(function () {
    Route::get('/provider/config', [ProviderConfigController::class, 'show']);
    Route::post('/provider/config', [ProviderConfigController::class, 'connect'])->name('provider.connect');
    Route::delete('/provider/config', [ProviderConfigController::class, 'delete'])->name('provider.delete');
    Route::get('/provider/diagnose', [ProviderConfigController::class, 'diagnose'])->name('provider.diagnose');
});

// Checkout — loaded by GHL as paymentsUrl (must allow iFrame embedding)
Route::middleware([AllowIframeEmbedding::class])->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'show']);
    Route::post('/checkout/create-session', [CheckoutController::class, 'createCheckoutSession'])->name('checkout.createSession')->middleware('throttle:checkout');
    Route::get('/checkout/status/{sessionId}', [CheckoutController::class, 'checkStatus'])->name('checkout.status');
    Route::get('/checkout/success', [CheckoutController::class, 'success'])->name('checkout.success');
    Route::get('/checkout/cancel', [CheckoutController::class, 'cancel'])->name('checkout.cancel');
});
