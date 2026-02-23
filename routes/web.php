<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GhlOAuthController;
use App\Http\Controllers\ProviderConfigController;
use App\Http\Controllers\CheckoutController;
use App\Http\Middleware\AllowIframeEmbedding;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/oauth/callback', [GhlOAuthController::class, 'callback']);

Route::get('/provider/config', [ProviderConfigController::class, 'show']);
Route::post('/provider/config', [ProviderConfigController::class, 'connect'])->name('provider.connect');
Route::delete('/provider/config', [ProviderConfigController::class, 'delete'])->name('provider.delete');

// Checkout — loaded by GHL as paymentsUrl (must allow iFrame embedding)
Route::middleware([AllowIframeEmbedding::class])->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'show']);
    Route::post('/checkout/create-session', [CheckoutController::class, 'createCheckoutSession'])->name('checkout.createSession');
    Route::get('/checkout/success', [CheckoutController::class, 'success'])->name('checkout.success');
    Route::get('/checkout/cancel', [CheckoutController::class, 'cancel'])->name('checkout.cancel');
});
