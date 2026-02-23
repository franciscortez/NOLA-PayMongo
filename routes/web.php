<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GhlOAuthController;
use App\Http\Controllers\ProviderConfigController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/oauth/callback', [GhlOAuthController::class, 'callback']);

Route::get('/provider/config', [ProviderConfigController::class, 'show']);
Route::post('/provider/config', [ProviderConfigController::class, 'connect'])->name('provider.connect');
Route::delete('/provider/config', [ProviderConfigController::class, 'delete'])->name('provider.delete');
