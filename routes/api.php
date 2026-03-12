<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\QueryController;
use App\Http\Controllers\PayMongoWebhookController;
use App\Http\Controllers\HomeController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/health', [HomeController::class, 'healthCheck']);

// GHL queryUrl — receives verify, refund, list_payment_methods, charge_payment
Route::post('/webhook/ghl-query', [QueryController::class, 'handle']);
Route::post('/query', [QueryController::class, 'handle']); // Alias matching marketplace app config
// PayMongo webhooks — per-location URL carries the locationId so we can resolve the right webhook secret
Route::post('/webhook/paymongo/{locationId}', [PayMongoWebhookController::class, 'handle'])
    ->middleware(['verify.paymongo.signature']);
// Legacy single-account webhook URL (backward compatible — falls back to .env secret)
Route::post('/webhook/paymongo', [PayMongoWebhookController::class, 'handle'])
    ->middleware(['verify.paymongo.signature']);
