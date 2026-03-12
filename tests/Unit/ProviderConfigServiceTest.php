<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ProviderConfigService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

class ProviderConfigServiceTest extends TestCase
{
   protected ProviderConfigService $providerConfigService;

   protected function setUp(): void
   {
      parent::setUp();

      Config::set('services.ghl.api_base', 'https://services.leadconnectorhq.com');
      Config::set('services.ghl.api_version', '2021-07-28');
      Config::set('app.url', 'https://myapp.com');

      $payMongoMock = \Mockery::mock(\App\Services\PayMongoService::class);
      $this->providerConfigService = new ProviderConfigService($payMongoMock);
   }

   public function test_register_custom_provider_success()
   {
      Http::fake([
         'services.leadconnectorhq.com/payments/custom-provider/provider?locationId=loc_123' => Http::response([
            '_id' => 'provider_123',
            'name' => 'NOLA PayMongo'
         ], 200)
      ]);

      $response = $this->providerConfigService->registerCustomProvider('loc_123', 'access_token_123');

      $this->assertTrue($response['success']);
      $this->assertEquals('provider_123', $response['data']['_id']);
   }

   public function test_register_custom_provider_failure()
   {
      Http::fake([
         'services.leadconnectorhq.com/payments/custom-provider/provider?locationId=loc_123' => Http::response([
            'message' => 'Provider already exists'
         ], 400)
      ]);

      $response = $this->providerConfigService->registerCustomProvider('loc_123', 'access_token_123');

      $this->assertFalse($response['success']);
      $this->assertEquals('HighLevel Provider Registration Failed.', $response['error']);
   }

   public function test_update_connect_config_success()
   {
      Http::fake([
         'services.leadconnectorhq.com/payments/custom-provider/connect?locationId=loc_123' => Http::response([
            'success' => true
         ], 200)
      ]);

      $keys = [
         'live_secret_key' => 'sk_live_1',
         'live_publishable_key' => 'pk_live_1',
         'test_secret_key' => 'sk_test_1',
         'test_publishable_key' => 'pk_test_1',
      ];

      $response = $this->providerConfigService->updateConnectConfig('loc_123', 'access_token_123', $keys);

      $this->assertTrue($response['success']);
   }

   public function test_is_provider_registered_returns_true()
   {
      Http::fake([
         'services.leadconnectorhq.com/payments/custom-provider/connect?locationId=loc_123' => Http::response([
            '_id' => 'provider_123'
         ], 200)
      ]);

      $this->assertTrue($this->providerConfigService->isProviderRegistered('loc_123', 'access_token_123'));
   }

   public function test_is_provider_registered_returns_false()
   {
      Http::fake([
         'services.leadconnectorhq.com/payments/custom-provider/connect?locationId=loc_123' => Http::response([], 404)
      ]);

      $this->assertFalse($this->providerConfigService->isProviderRegistered('loc_123', 'access_token_123'));
   }

   public function test_delete_provider_success()
   {
      Http::fake([
         'services.leadconnectorhq.com/payments/custom-provider/provider?locationId=loc_123' => Http::response([], 200)
      ]);

      $response = $this->providerConfigService->deleteProvider('loc_123', 'access_token_123');

      $this->assertTrue($response['success']);
   }
}
