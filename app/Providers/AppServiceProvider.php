<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        $this->configureRateLimiting();
    }

    /**
     * Configure rate limiting for checkout and webhook endpoints.
     */
    protected function configureRateLimiting(): void
    {
        $checkoutLimit = app()->environment('testing') ? 3 : 30;

        // Checkout session creation: 30/min per IP (3 in testing for fast tests)
        RateLimiter::for('checkout', function (Request $request) use ($checkoutLimit) {
            return Limit::perMinute($checkoutLimit)->by($request->ip());
        });
    }
}
