<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\CheckGhlToken;
use App\Http\Middleware\EnsureHttps;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->prepend(EnsureHttps::class);
        $middleware->validateCsrfTokens(except: [
            'checkout/create-session', // POST from GHL iFrame (cross-origin)
        ]);
        $middleware->alias([
            'check.ghl.token' => CheckGhlToken::class,
            'verify.paymongo.signature' => \App\Http\Middleware\VerifyPayMongoSignature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
