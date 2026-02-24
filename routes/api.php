<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\QueryController;
use App\Http\Controllers\PayMongoWebhookController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// GHL queryUrl — receives verify, refund, list_payment_methods, charge_payment
Route::post('/webhook/ghl-query', [QueryController::class, 'handle']);
Route::post('/query', [QueryController::class, 'handle']); // Alias matching marketplace app config

// PayMongo webhooks — receives payment.paid, payment.failed, payment.refunded, etc.
Route::post('/webhook/paymongo', [PayMongoWebhookController::class, 'handle']);
