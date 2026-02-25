<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Transaction;
use App\Services\GhlQueryService;
use App\Services\PayMongoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class GhlQueryServiceVerifyRefundTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_verify_payment_resolves_race_condition_by_polling_api()
    {
        // 1. Create a transaction that is still "pending" in the DB.
        $transaction = Transaction::create([
            'checkout_session_id' => 'cs_123',
            'payment_intent_id' => 'pi_456',
            'ghl_transaction_id' => 'cs_123',
            'ghl_order_id' => 'order_123',
            'ghl_location_id' => 'loc_123',
            'amount' => 10000,
            'amount_refunded' => 0,
            'currency' => 'PHP',
            'description' => 'Test',
            'status' => 'pending', // Still pending!
            'payment_method' => 'card',
        ]);

        // 2. Mock PayMongoService to simulate that the API actually says it's paid.
        $mockPayMongo = Mockery::mock(PayMongoService::class);
        $mockPayMongo->shouldReceive('retrieveCheckoutSession')
            ->once()
            ->with('cs_123')
            ->andReturn([
                'success' => true,
                'status' => 'paid',
                'payments' => [
                    [
                        'id' => 'pay_789',
                        'attributes' => [
                            'status' => 'paid',
                            'paid_at' => now()->timestamp,
                            'amount' => 10000,
                        ]
                    ]
                ],
                'amount' => 10000,
            ]);

        // 3. Execute verifyPayment
        $service = new GhlQueryService();
        $result = $service->verifyPayment('cs_123', $mockPayMongo);

        // 4. Assert the service successfully recognized the race condition
        $this->assertTrue($result['success']);
        $this->assertEquals('pay_789', $result['chargeSnapshot']['id']);
        $this->assertEquals('succeeded', $result['chargeSnapshot']['status']);

        // 5. Assert the database was actually updated behind the scenes
        $transaction->refresh();
        $this->assertEquals('paid', $transaction->status);
        $this->assertEquals('pay_789', $transaction->payment_id);
    }

    public function test_refund_payment_supports_partial_refunds()
    {
        // 1. Create a fully paid transaction
        $transaction = Transaction::create([
            'checkout_session_id' => 'cs_123',
            'payment_intent_id' => 'pi_456',
            'payment_id' => 'pay_789', // Needed for refund targeting
            'ghl_transaction_id' => 'cs_123',
            'ghl_order_id' => 'order_123',
            'ghl_location_id' => 'loc_123',
            'amount' => 10000, // 100 PHP total
            'amount_refunded' => 0,
            'currency' => 'PHP',
            'description' => 'Test',
            'status' => 'paid',
        ]);

        // 2. Mock PayMongoService to simulate a successful partial refund API call
        $mockPayMongo = Mockery::mock(PayMongoService::class);
        $mockPayMongo->shouldReceive('refundPayment')
            ->once()
            ->with('pay_789', 3000) // Refund 30 PHP
            ->andReturn([
                'success' => true,
                'id' => 'rfnd_abc',
                'amount' => 3000,
                'currency' => 'PHP',
                'status' => 'succeeded',
            ]);

        // 3. Execute partial refund (30 PHP)
        $service = new GhlQueryService();
        $result = $service->refundPayment('cs_123', 30.00, $mockPayMongo);

        // 4. Assert refund success output
        $this->assertTrue($result['success']);
        $this->assertEquals(30.00, $result['amount']);

        // 5. Assert database updated correctly
        $transaction->refresh();
        $this->assertEquals('partially_refunded', $transaction->status);
        $this->assertEquals(3000, $transaction->amount_refunded);
        $this->assertCount(1, $transaction->metadata['refunds']);
        $this->assertEquals('rfnd_abc', $transaction->metadata['refunds'][0]['id']);

        // 6. Prevent over-refunding test
        // Remaining is 70 PHP. Try refunding 80 PHP.
        $overRefundResult = $service->refundPayment('cs_123', 80.00, $mockPayMongo);
        $this->assertFalse($overRefundResult['success']);
        $this->assertStringContainsString('exceeds remaining refundable amount', $overRefundResult['message']);
    }
}
