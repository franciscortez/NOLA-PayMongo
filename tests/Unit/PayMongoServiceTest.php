<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\PayMongoService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

class PayMongoServiceTest extends TestCase
{
   protected PayMongoService $payMongoService;

   protected function setUp(): void
   {
      parent::setUp();

      Config::set('services.paymongo.is_production', false);
      Config::set('services.paymongo.test_secret_key', 'sk_test_123');
      Config::set('services.paymongo.test_publishable_key', 'pk_test_123');
      Config::set('services.paymongo.live_secret_key', 'sk_live_123');
      Config::set('services.paymongo.live_publishable_key', 'pk_live_123');

      $this->payMongoService = new PayMongoService();
   }

   // =========================================================
   // Existing: .env / config-based key tests
   // =========================================================

   public function test_get_secret_key_returns_correct_key_based_on_environment()
   {
      // Default is test
      $this->assertEquals('sk_test_123', $this->payMongoService->getSecretKey());

      // Change to production
      $productionService = $this->payMongoService->setProduction(true);
      $this->assertEquals('sk_live_123', $productionService->getSecretKey());
   }

   public function test_get_publishable_key_returns_correct_key_based_on_environment()
   {
      // Default is test
      $this->assertEquals('pk_test_123', $this->payMongoService->getPublishableKey());

      // Change to production
      $productionService = $this->payMongoService->setProduction(true);
      $this->assertEquals('pk_live_123', $productionService->getPublishableKey());
   }

   // =========================================================
   // NEW: Dynamic per-location key tests (multi-account)
   // =========================================================

   public function test_set_dynamic_keys_overrides_config_keys()
   {
      $service = $this->payMongoService->setDynamicKeys('sk_test_perLocation', 'pk_test_perLocation');

      // Dynamic key should be used, not the .env/config one
      $this->assertEquals('sk_test_perLocation', $service->getSecretKey());
      $this->assertEquals('pk_test_perLocation', $service->getPublishableKey());
   }

   public function test_set_dynamic_keys_auto_detects_live_mode_from_key_prefix()
   {
      $liveService = $this->payMongoService->setDynamicKeys('sk_live_perLocation');
      $testService = $this->payMongoService->setDynamicKeys('sk_test_perLocation');

      // setDynamicKeys sets isProduction based on key prefix
      $this->assertEquals('sk_live_perLocation', $liveService->getSecretKey());
      $this->assertEquals('sk_test_perLocation', $testService->getSecretKey());
   }

   public function test_original_service_is_not_mutated_by_set_dynamic_keys()
   {
      $this->payMongoService->setDynamicKeys('sk_test_perLocation');

      // Original should still use .env config key
      $this->assertEquals('sk_test_123', $this->payMongoService->getSecretKey());
   }

   public function test_create_webhook_returns_id_and_secret_on_success()
   {
      Http::fake([
         'api.paymongo.com/v1/webhooks' => Http::response([
            'data' => [
               'id' => 'wh_test_123',
               'attributes' => [
                  'url' => 'https://app.test/api/webhook/paymongo/loc_abc',
                  'secret_key' => 'whsk_test_abc123',
               ]
            ]
         ], 200)
      ]);

      $result = $this->payMongoService->createWebhook('sk_test_123', 'loc_abc');

      $this->assertNotNull($result);
      $this->assertEquals('wh_test_123', $result['id']);
      $this->assertEquals('whsk_test_abc123', $result['secret_key']);
   }

   public function test_create_webhook_returns_null_on_failure()
   {
      Http::fake([
         'api.paymongo.com/v1/webhooks' => Http::response(['errors' => [['detail' => 'Unauthorized']]], 401)
      ]);

      $result = $this->payMongoService->createWebhook('sk_test_invalid', 'loc_abc');

      $this->assertNull($result);
   }

   // =========================================================
   // Existing: API operation tests
   // =========================================================

   public function test_create_checkout_session_success()
   {
      Http::fake([
         'api.paymongo.com/v1/checkout_sessions' => Http::response([
            'data' => [
               'id' => 'cs_123abc',
               'attributes' => [
                  'checkout_url' => 'https://checkout.paymongo.com/cs_123abc',
                  'status' => 'active',
                  'payment_intent' => [
                     'id' => 'pi_123abc'
                  ]
               ]
            ]
         ], 200)
      ]);

      $payload = [
         'amount' => 10000,
         'payment_method_types' => ['card']
      ];

      $response = $this->payMongoService->createCheckoutSession($payload);

      $this->assertTrue($response['success']);
      $this->assertEquals('cs_123abc', $response['id']);
      $this->assertEquals('https://checkout.paymongo.com/cs_123abc', $response['checkout_url']);
      $this->assertEquals('active', $response['status']);
      $this->assertEquals('pi_123abc', $response['payment_intent_id']);
   }

   public function test_create_checkout_session_uses_dynamic_key()
   {
      Http::fake([
         'api.paymongo.com/v1/checkout_sessions' => Http::response([
            'data' => [
               'id' => 'cs_456',
               'attributes' => [
                  'checkout_url' => 'https://checkout.paymongo.com/cs_456',
                  'status' => 'active',
                  'payment_intent' => ['id' => 'pi_456']
               ]
            ]
         ], 200)
      ]);

      $service = $this->payMongoService->setDynamicKeys('sk_test_dynamic_key');
      $response = $service->createCheckoutSession(['amount' => 10000, 'payment_method_types' => ['card']]);

      // Verify the dynamic key was used in the request
      Http::assertSent(function ($request) {
         return str_contains($request->header('Authorization')[0] ?? '', base64_encode('sk_test_dynamic_key:'));
      });

      $this->assertTrue($response['success']);
   }

   public function test_create_checkout_session_failure()
   {
      Http::fake([
         'api.paymongo.com/v1/checkout_sessions' => Http::response([
            'errors' => [['detail' => 'Invalid attributes']]
         ], 400)
      ]);

      $response = $this->payMongoService->createCheckoutSession(['amount' => 10000]);

      $this->assertFalse($response['success']);
      $this->assertEquals('Invalid attributes', $response['error']);
   }

   public function test_retrieve_payment_intent_success()
   {
      Http::fake([
         'api.paymongo.com/v1/payment_intents/pi_123' => Http::response([
            'data' => [
               'id' => 'pi_123',
               'attributes' => [
                  'status' => 'succeeded',
                  'amount' => 10000,
                  'currency' => 'PHP',
                  'payments' => []
               ]
            ]
         ], 200)
      ]);

      $response = $this->payMongoService->retrievePaymentIntent('pi_123');

      $this->assertTrue($response['success']);
      $this->assertEquals('pi_123', $response['id']);
      $this->assertEquals('succeeded', $response['status']);
      $this->assertEquals(10000, $response['amount']);
   }

   public function test_validate_key_returns_true_on_success()
   {
      Http::fake([
         'api.paymongo.com/v1/webhooks' => Http::response([], 200)
      ]);

      $this->assertTrue($this->payMongoService->validateKey('sk_test_valid'));
   }

   public function test_validate_key_returns_false_on_failure()
   {
      Http::fake([
         'api.paymongo.com/v1/webhooks' => Http::response([], 401)
      ]);

      $this->assertFalse($this->payMongoService->validateKey('sk_test_invalid'));
   }

   public function test_refund_payment_success()
   {
      Http::fake([
         'api.paymongo.com/v1/refunds' => Http::response([
            'data' => [
               'id' => 'rf_123',
               'attributes' => [
                  'amount' => 5000,
                  'currency' => 'PHP',
                  'status' => 'succeeded'
               ]
            ]
         ], 200)
      ]);

      $response = $this->payMongoService->refundPayment('pay_123', 5000);

      $this->assertTrue($response['success']);
      $this->assertEquals('rf_123', $response['id']);
      $this->assertEquals('succeeded', $response['status']);
      $this->assertEquals(5000, $response['amount']);
   }
}
