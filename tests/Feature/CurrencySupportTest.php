<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Services\CheckoutService;
use App\Services\PayMongoService;
use Mockery;

class CurrencySupportTest extends TestCase
{
   use RefreshDatabase;

   protected $checkoutService;
   protected $payMongoServiceMock;

   protected function setUp(): void
   {
      parent::setUp();
      $this->payMongoServiceMock = Mockery::mock(PayMongoService::class);
      $this->checkoutService = new CheckoutService($this->payMongoServiceMock);
   }

   public function test_php_checkout_includes_all_payment_methods()
   {
      $data = [
         'amount' => 10000,
         'currency' => 'PHP',
         'description' => 'Test PHP Payment',
         'transaction_id' => 'ghl_123',
         'order_id' => 'order_123',
         'location_id' => 'loc_123',
      ];

      $this->payMongoServiceMock->shouldReceive('setProduction')->andReturnSelf();
      $this->payMongoServiceMock->shouldReceive('createCheckoutSession')
         ->with(Mockery::on(function ($payload) {
            return $payload['line_items'][0]['currency'] === 'PHP' &&
               count($payload['payment_method_types']) === 5 &&
               in_array('gcash', $payload['payment_method_types']);
         }))
         ->once()
         ->andReturn([
            'success' => true,
            'id' => 'cs_123',
            'checkout_url' => 'https://paymongo.test/cs_123',
         ]);

      $result = $this->checkoutService->createSession($data);

      $this->assertTrue($result['success']);
      $this->assertEquals('https://paymongo.test/cs_123', $result['checkout_url']);
   }

   public function test_usd_checkout_restricts_to_card_only()
   {
      $data = [
         'amount' => 1000,
         'currency' => 'USD',
         'description' => 'Test USD Payment',
         'transaction_id' => 'ghl_456',
         'order_id' => 'order_456',
         'location_id' => 'loc_456',
      ];

      $this->payMongoServiceMock->shouldReceive('setProduction')->andReturnSelf();
      $this->payMongoServiceMock->shouldReceive('createCheckoutSession')
         ->with(Mockery::on(function ($payload) {
            return $payload['line_items'][0]['currency'] === 'USD' &&
               $payload['payment_method_types'] === ['card'];
         }))
         ->once()
         ->andReturn([
            'success' => true,
            'id' => 'cs_456',
            'checkout_url' => 'https://paymongo.test/cs_456',
         ]);

      $result = $this->checkoutService->createSession($data);

      $this->assertTrue($result['success']);
      $this->assertEquals('https://paymongo.test/cs_456', $result['checkout_url']);
   }

   public function test_unsupported_currency_returns_error()
   {
      $data = [
         'amount' => 1000,
         'currency' => 'EUR',
         'description' => 'Test EUR Payment',
      ];

      $result = $this->checkoutService->createSession($data);

      $this->assertFalse($result['success']);
      $this->assertStringContainsString('not supported', $result['error']);
   }
}
