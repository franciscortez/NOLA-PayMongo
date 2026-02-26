<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use App\Services\CheckoutService;
use App\Services\PayMongoService;
use App\Models\Transaction;
use Mockery;

class CheckoutServiceTest extends TestCase
{
    use DatabaseMigrations;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_create_session_fallback_line_item()
    {
        /** @var \App\Services\PayMongoService|\Mockery\MockInterface $mockPayMongo */
        $mockPayMongo = Mockery::mock(PayMongoService::class);
        $mockPayMongo->shouldReceive('setProduction')->once()->andReturnSelf();

        $mockPayMongo->shouldReceive('createCheckoutSession')
            ->once()
            ->with(Mockery::on(function ($payload) {
                return count($payload['line_items']) === 1
                    && $payload['line_items'][0]['name'] === 'Test Item'
                    && $payload['line_items'][0]['amount'] === 10000;
            }))
            ->andReturn([
                'success' => true,
                'id' => 'cs_test_123',
                'checkout_url' => 'https://paymongo.com/checkout/123',
                'status' => 'active',
            ]);

        $service = new CheckoutService($mockPayMongo);

        $result = $service->createSession([
            'amount' => 10000,
            'currency' => 'PHP',
            'description' => 'Test Item',
            'transaction_id' => 'ghl_tx_123',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('cs_test_123', $result['checkout_session_id']);

        $this->assertDatabaseHas('transactions', [
            'checkout_session_id' => 'cs_test_123',
            'amount' => 10000,
            'status' => 'pending',
            'ghl_transaction_id' => 'ghl_tx_123',
        ]);
    }

    public function test_create_session_with_product_details()
    {
        /** @var \App\Services\PayMongoService|\Mockery\MockInterface $mockPayMongo */
        $mockPayMongo = Mockery::mock(PayMongoService::class);
        $mockPayMongo->shouldReceive('setProduction')->once()->andReturnSelf();

        $mockPayMongo->shouldReceive('createCheckoutSession')
            ->once()
            ->with(Mockery::on(function ($payload) {
                return count($payload['line_items']) === 2
                    && $payload['line_items'][0]['name'] === 'First Product'
                    && $payload['line_items'][0]['amount'] === 5000 // 50 * 100
                    && $payload['line_items'][0]['quantity'] === 2
                    && $payload['line_items'][1]['name'] === 'Second Product'
                    && $payload['line_items'][1]['amount'] === 2500 // 25 * 100
                    && $payload['line_items'][1]['quantity'] === 1;
            }))
            ->andReturn([
                'success' => true,
                'id' => 'cs_test_multi',
                'checkout_url' => 'https://paymongo.com/checkout/multi',
                'status' => 'active',
            ]);

        $service = new CheckoutService($mockPayMongo);

        $result = $service->createSession([
            'amount' => 12500, // (50 * 2) + (25 * 1) = 125 -> 12500 cents
            'currency' => 'PHP',
            'description' => 'First Product, Second Product',
            'transaction_id' => 'ghl_tx_456',
            'product_details' => [
                [
                    'name' => 'First Product',
                    'price' => 50.00,
                    'qty' => 2
                ],
                [
                    'name' => 'Second Product',
                    'price' => 25.00,
                    'qty' => 1
                ]
            ]
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('cs_test_multi', $result['checkout_session_id']);

        $this->assertDatabaseHas('transactions', [
            'checkout_session_id' => 'cs_test_multi',
            'amount' => 12500,
            'status' => 'pending',
            'ghl_transaction_id' => 'ghl_tx_456',
        ]);
    }

    public function test_create_session_failure_returns_error()
    {
        /** @var \App\Services\PayMongoService|\Mockery\MockInterface $mockPayMongo */
        $mockPayMongo = Mockery::mock(PayMongoService::class);
        $mockPayMongo->shouldReceive('setProduction')->once()->andReturnSelf();
        $mockPayMongo->shouldReceive('createCheckoutSession')->once()->andReturnUsing(fn() => [
            'success' => false,
            'error' => 'Invalid API key',
        ]);

        $service = new CheckoutService($mockPayMongo);

        $result = $service->createSession([
            'amount' => 10000,
            'currency' => 'PHP',
            'description' => 'Test Item',
        ]);

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid API key', $result['error']);
        $this->assertDatabaseEmpty('transactions');
    }

    public function test_check_status_paid_in_db()
    {
        Transaction::create([
            'checkout_session_id' => 'cs_test_paid',
            'amount' => 10000,
            'currency' => 'PHP',
            'status' => 'paid',
            'payment_id' => 'pay_123',
        ]);

        /** @var \App\Services\PayMongoService|\Mockery\MockInterface $mockPayMongo */
        $mockPayMongo = Mockery::mock(PayMongoService::class);
        // Should not call retrieveCheckoutSession
        $mockPayMongo->shouldNotReceive('retrieveCheckoutSession');

        $service = new CheckoutService($mockPayMongo);
        $result = $service->checkStatus('cs_test_paid');

        $this->assertEquals('paid', $result['status']);
        $this->assertEquals('pay_123', $result['charge_id']);
    }

    public function test_check_status_calls_api_and_updates_db_if_paid()
    {
        $transaction = Transaction::create([
            'checkout_session_id' => 'cs_test_pending',
            'amount' => 10000,
            'currency' => 'PHP',
            'status' => 'pending',
        ]);

        /** @var \App\Services\PayMongoService|\Mockery\MockInterface $mockPayMongo */
        $mockPayMongo = Mockery::mock(PayMongoService::class);
        $mockPayMongo->shouldReceive('retrieveCheckoutSession')
            ->once()
            ->with('cs_test_pending')
            ->andReturnUsing(fn() => [
                'success' => true,
                'status' => 'paid',
                'payment_intent' => ['id' => 'pi_123'],
                'payments' => [['id' => 'pay_123']],
            ]);

        $service = new CheckoutService($mockPayMongo);
        $result = $service->checkStatus('cs_test_pending');

        $this->assertEquals('paid', $result['status']);
        $this->assertEquals('pay_123', $result['charge_id']);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'paid',
            'payment_intent_id' => 'pi_123',
            'payment_id' => 'pay_123',
        ]);
    }
}
