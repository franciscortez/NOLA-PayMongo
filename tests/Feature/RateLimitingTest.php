<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('checkout');
    }

    public function test_checkout_create_session_route_has_throttle_middleware()
    {
        $route = Route::getRoutes()->getByName('checkout.createSession');
        $this->assertNotNull($route);
        $middleware = $route->gatherMiddleware();
        $this->assertContains('throttle:checkout', $middleware);
    }

    /**
     * Flow: "checkout" limiter allows 30 requests/minute per IP (3 in testing).
     * Each POST to create-session counts; after the limit, the next request gets 429.
     */
    public function test_checkout_create_session_returns_429_after_limit_exceeded()
    {
        $payload = [
            'amount' => 100,
            'currency' => 'PHP',
            'ghl_location_id' => 'loc_123',
            'ghl_transaction_id' => 'tx_1',
            'ghl_order_id' => 'ord_1',
            'customer_name' => 'Test',
            'customer_email' => 'test@example.com',
            'productDetails' => [],
        ];

        // Exhaust the limit (3 in testing)
        for ($i = 0; $i < 3; $i++) {
            $response = $this->postJson('/checkout/create-session', $payload);
            $this->assertNotEquals(429, $response->status(), "Request " . ($i + 1) . " should not be rate limited");
        }

        // Next request must be rate limited
        $response = $this->postJson('/checkout/create-session', $payload);
        $response->assertStatus(429);
        $response->assertHeader('Retry-After');
    }

}
