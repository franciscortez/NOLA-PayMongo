<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\GhlQueryService;
use App\Services\PayMongoService;
use App\Models\Transaction;
use Mockery;

class GhlQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_verify_returns_paid_from_db()
    {
        Transaction::create([
            'ghl_transaction_id' => 'ghl_charge_123',
            'payment_id' => 'pay_123',
            'amount' => 10000, // 100 PHP
            'currency' => 'PHP',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $service = new GhlQueryService();
        /** @var \App\Services\PayMongoService|\Mockery\MockInterface $mockPayMongo */
        $mockPayMongo = Mockery::mock(PayMongoService::class);
        $mockPayMongo->shouldNotReceive('retrievePaymentIntent');

        $result = $service->verifyPayment('ghl_charge_123', $mockPayMongo);

        $this->assertTrue($result['success']);
        $this->assertEquals('succeeded', $result['chargeSnapshot']['status']);
        $this->assertEquals(100, $result['chargeSnapshot']['amount']);
        $this->assertEquals('pay_123', $result['chargeSnapshot']['id']);
    }

    public function test_verify_falls_back_to_api_and_returns_succeeded()
    {
        $service = new GhlQueryService();
        /** @var \App\Services\PayMongoService|\Mockery\MockInterface $mockPayMongo */
        $mockPayMongo = Mockery::mock(PayMongoService::class);
        $mockPayMongo->shouldReceive('retrievePaymentIntent')
            ->once()
            ->with('pi_123')
            ->andReturnUsing(fn() => [
                'success' => true,
                'status' => 'succeeded',
                'amount' => 20000, // 200 PHP
                'payments' => [['id' => 'pay_123']]
            ]);

        $result = $service->verifyPayment('pi_123', $mockPayMongo);

        $this->assertTrue($result['success']);
        $this->assertEquals('succeeded', $result['chargeSnapshot']['status']);
        $this->assertEquals(200, $result['chargeSnapshot']['amount']);
        $this->assertEquals('pay_123', $result['chargeSnapshot']['id']);
    }

    public function test_refund_successful_processes_via_api_and_updates_db()
    {
        $transaction = Transaction::create([
            'payment_intent_id' => 'pi_refund_123',
            'payment_id' => 'pay_refund_123',
            'amount' => 10000,
            'currency' => 'PHP',
            'status' => 'paid',
        ]);

        $service = new GhlQueryService();
        /** @var \App\Services\PayMongoService|\Mockery\MockInterface $mockPayMongo */
        $mockPayMongo = Mockery::mock(PayMongoService::class);

        $mockPayMongo->shouldReceive('retrievePaymentIntent')
            ->once()
            ->with('pi_refund_123')
            ->andReturnUsing(fn() => [
                'success' => true,
                'payments' => [['id' => 'pay_refund_123']]
            ]);

        $mockPayMongo->shouldReceive('refundPayment')
            ->once()
            ->with('pay_refund_123', 5000) // 50 PHP * 100
            ->andReturnUsing(fn() => [
                'success' => true,
                'id' => 'rf_123',
                'currency' => 'PHP'
            ]);

        $result = $service->refundPayment('pi_refund_123', 50.00, $mockPayMongo);

        $this->assertTrue($result['success']);
        $this->assertEquals('Refund successful', $result['message']);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'refunded'
        ]);
    }
}
