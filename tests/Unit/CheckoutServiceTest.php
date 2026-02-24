<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\CheckoutService;
use App\Services\PayMongoService;
use App\Models\Transaction;
use Mockery;

class CheckoutServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_create_session_success_saves_transaction()
    {
        /** @var \App\Services\PayMongoService|\Mockery\MockInterface $mockPayMongo */
        $mockPayMongo = Mockery::mock(PayMongoService::class);
        $mockPayMongo->shouldReceive('setProduction')->once()->andReturnSelf();
        $mockPayMongo->shouldReceive('createCheckoutSession')->once()->andReturnUsing(fn() => [
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
