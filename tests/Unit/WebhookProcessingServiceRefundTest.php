<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Transaction;
use App\Services\WebhookProcessingService;
use App\Services\GhlWebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class WebhookProcessingServiceRefundTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_process_payment_refunded_webhook()
    {
        // 1. Create a fully paid transaction
        $transaction = Transaction::create([
            'checkout_session_id' => 'cs_123',
            'payment_intent_id' => 'pi_456',
            'payment_id' => 'pay_789',
            'ghl_transaction_id' => 'cs_123',
            'ghl_order_id' => 'order_123',
            'ghl_location_id' => 'loc_123',
            'amount' => 10000, // 100 PHP total
            'amount_refunded' => 0,
            'currency' => 'PHP',
            'description' => 'Test',
            'status' => 'paid',
        ]);

        $webhookService = new WebhookProcessingService(Mockery::mock(GhlWebhookService::class));

        // 2. Simulate a partial refund webhook payload from PayMongo
        $eventData = [
            'id' => 'pay_789',
            'attributes' => [
                'refunds' => [
                    'data' => [
                        [
                            'id' => 'rfnd_1',
                            'attributes' => [
                                'amount' => 3000, // 30 PHP
                                'created_at' => now()->timestamp
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $webhookService->processPaymentRefunded($eventData);

        // 3. Assert database updated correctly
        $transaction->refresh();
        $this->assertEquals('partially_refunded', $transaction->status);
        $this->assertEquals(3000, $transaction->amount_refunded);
        $this->assertCount(1, $transaction->metadata['refunds']);
        $this->assertEquals('rfnd_1', $transaction->metadata['refunds'][0]['id']);
        $this->assertEquals('webhook', $transaction->metadata['refunds'][0]['source']);

        // 4. Simulate the second refund completing the amount
        $eventData2 = [
            'id' => 'pay_789',
            'attributes' => [
                'refunds' => [
                    'data' => [
                        [
                            'id' => 'rfnd_1',
                            'attributes' => [
                                'amount' => 3000, // old refund, should be deduplicated
                                'created_at' => now()->timestamp
                            ]
                        ],
                        [
                            'id' => 'rfnd_2',
                            'attributes' => [
                                'amount' => 7000, // remaining 70 PHP
                                'created_at' => now()->timestamp
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $webhookService->processPaymentRefunded($eventData2);

        // 5. Assert database updated correctly
        $transaction->refresh();
        $this->assertEquals('refunded', $transaction->status); // Fully refunded!
        $this->assertEquals(10000, $transaction->amount_refunded);
        $this->assertCount(2, $transaction->metadata['refunds']); // Added only the new one
        $this->assertEquals('rfnd_2', $transaction->metadata['refunds'][1]['id']);
    }
}
