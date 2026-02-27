<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\LocationToken;
use Illuminate\Support\Facades\Http;

class ProviderConfigControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_connect_handles_ghl_provider_registration_error_with_details()
    {
        // 1. Set up a dummy location and token
        $locationId = 'test_location_123';
        LocationToken::create([
            'location_id' => $locationId,
            'access_token' => 'dummy_access_token',
            'refresh_token' => 'dummy_refresh_token',
            'expires_at' => now()->addDays(1),
            'user_type' => 'Location',
        ]);

        // 2. Mock environment variables for PayMongo keys so validation passes
        config(['services.paymongo.live_secret_key' => 'sk_live_dummy']);
        config(['services.paymongo.live_publishable_key' => 'pk_live_dummy']);
        config(['services.paymongo.test_secret_key' => 'sk_test_dummy']);
        config(['services.paymongo.test_publishable_key' => 'pk_test_dummy']);
        config(['services.paymongo.is_production' => true]);

        // 3. Mock PayMongoService to return true for key validation
        $payMongoMock = \Mockery::mock(\App\Services\PayMongoService::class)->makePartial();
        $payMongoMock->shouldReceive('validateKey')->andReturn(true);
        $this->app->instance(\App\Services\PayMongoService::class, $payMongoMock);

        // 4. Mock GHL API to return a 400 Bad Request with a specific error message during provider registration
        Http::fake([
            'services.leadconnectorhq.com/payments/custom-provider/provider*' => Http::response([
                'statusCode' => 400,
                'message' => 'paymentsUrl is invalid.',
                'error' => 'Bad Request'
            ], 400),
        ]);

        // 5. Submit the connection request
        $response = $this->post(route('provider.connect'), [
            'location_id' => $locationId,
        ]);

        // 6. Assert that we are redirected back with the extracted error message in the session
        $response->assertSessionHas('error', 'Failed to register the Custom Provider in GHL. Reason: paymentsUrl is invalid.');
        $response->assertSessionHas('error_details');
    }

    public function test_connect_handles_ghl_connect_config_error_with_details()
    {
        // 1. Set up a dummy location and token
        $locationId = 'test_location_123';
        LocationToken::create([
            'location_id' => $locationId,
            'access_token' => 'dummy_access_token',
            'refresh_token' => 'dummy_refresh_token',
            'expires_at' => now()->addDays(1),
            'user_type' => 'Location',
        ]);

        // 2. Mock environment variables for PayMongo keys so validation passes
        config(['services.paymongo.live_secret_key' => 'sk_live_dummy']);
        config(['services.paymongo.live_publishable_key' => 'pk_live_dummy']);
        config(['services.paymongo.test_secret_key' => 'sk_test_dummy']);
        config(['services.paymongo.test_publishable_key' => 'pk_test_dummy']);
        config(['services.paymongo.is_production' => true]);

        // 3. Mock PayMongoService to return true for key validation
        $payMongoMock = \Mockery::mock(\App\Services\PayMongoService::class)->makePartial();
        $payMongoMock->shouldReceive('validateKey')->andReturn(true);
        $this->app->instance(\App\Services\PayMongoService::class, $payMongoMock);

        // 4. Mock GHL API to SUCCEED on provider registration, but FAIL on connect config
        Http::fake([
            'services.leadconnectorhq.com/payments/custom-provider/provider*' => Http::response([
                '_id' => 'dummy_provider_id',
                'name' => 'NOLA PayMongo'
            ], 200),
            'services.leadconnectorhq.com/payments/custom-provider/connect*' => Http::response([
                'statusCode' => 401,
                'message' => 'Unauthorized token or expired access.',
                'error' => 'Unauthorized'
            ], 401),
        ]);

        // 5. Submit the connection request
        $response = $this->post(route('provider.connect'), [
            'location_id' => $locationId,
        ]);

        // 6. Assert that we are redirected back with the extracted error message in the session
        $response->assertSessionHas('error', 'Failed to push the PayMongo keys to the Connect Config API in GHL. Reason: Unauthorized token or expired access.');
        $response->assertSessionHas('error_details');
    }
}
