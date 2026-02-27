<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\GhlService;
use App\Models\LocationToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GhlServiceTest extends TestCase
{
   use RefreshDatabase;

   protected GhlService $ghlService;

   protected function setUp(): void
   {
      parent::setUp();

      Config::set('services.ghl.api_base', 'https://services.leadconnectorhq.com');
      Config::set('services.ghl.client_id', 'client123');
      Config::set('services.ghl.client_secret', 'secret123');
      Config::set('services.ghl.redirect_uri', 'https://example.com/oauth/callback');

      $this->ghlService = new GhlService();
   }

   public function test_exchange_code_for_token_success()
   {
      Http::fake([
         'services.leadconnectorhq.com/oauth/token' => Http::response([
            'access_token' => 'access123',
            'refresh_token' => 'refresh123',
            'expires_in' => 86400,
            'locationId' => 'loc_123',
            'userType' => 'Location'
         ], 200)
      ]);

      $response = $this->ghlService->exchangeCodeForToken('auth_code_123');

      $this->assertTrue($response['success']);
      $this->assertEquals('access123', $response['token_data']['access_token']);

      $this->assertDatabaseHas('location_tokens', [
         'location_id' => 'loc_123',
         'user_type' => 'Location'
      ]);
   }

   public function test_exchange_code_for_token_failure()
   {
      Http::fake([
         'services.leadconnectorhq.com/oauth/token' => Http::response([
            'error' => 'invalid_grant'
         ], 400)
      ]);

      $response = $this->ghlService->exchangeCodeForToken('invalid_code');

      $this->assertFalse($response['success']);
      $this->assertEquals('Token Exchange Failed. Check Laravel logs.', $response['error']);
   }

   public function test_refresh_token_success()
   {
      $token = LocationToken::create([
         'location_id' => 'loc_123',
         'access_token' => 'old_access',
         'refresh_token' => 'old_refresh',
         'expires_at' => now()->addDays(1),
         'user_type' => 'Location'
      ]);

      Http::fake([
         'services.leadconnectorhq.com/oauth/token' => Http::response([
            'access_token' => 'new_access',
            'refresh_token' => 'new_refresh',
            'expires_in' => 86400,
            'locationId' => 'loc_123'
         ], 200)
      ]);

      $updatedToken = $this->ghlService->refreshToken($token);

      $this->assertNotNull($updatedToken);
      $this->assertEquals('loc_123', $updatedToken->location_id);

      // Ensure db contains new token records since LocationToken factory and updateOrCreate is used internally
      $this->assertDatabaseHas('location_tokens', [
         'location_id' => 'loc_123',
         // Encrypted values won't match exactly in db assertion easily due to Laravel casting but the object works
      ]);

      // Assert on the model returned
      $this->assertEquals('new_access', $updatedToken->access_token);
      $this->assertEquals('new_refresh', $updatedToken->refresh_token);
   }

   public function test_refresh_token_failure()
   {
      $token = LocationToken::create([
         'location_id' => 'loc_123',
         'access_token' => 'old_access',
         'refresh_token' => 'bad_refresh',
         'expires_at' => now()->addDays(1),
         'user_type' => 'Location'
      ]);

      Http::fake([
         'services.leadconnectorhq.com/oauth/token' => Http::response([], 400)
      ]);

      $result = $this->ghlService->refreshToken($token);

      $this->assertFalse($result);
   }
}
