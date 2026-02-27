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

   public function test_create_checkout_session_failure()
   {
      Http::fake([
         'api.paymongo.com/v1/checkout_sessions' => Http::response([
            'errors' => [
               [
                  'detail' => 'Invalid attributes'
               ]
            ]
         ], 400)
      ]);

      $payload = ['amount' => 10000];

      $response = $this->payMongoService->createCheckoutSession($payload);

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
