<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use App\Services\WebhookProcessingService;
use App\Services\GhlWebhookService;
use App\Models\Transaction;
use Mockery;

class WebhookProcessingServiceTest extends TestCase
{
    use DatabaseMigrations;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_process_checkout_session_paid_updates_transaction_and_notifies_ghl()
    {
        $transaction = Transaction::create([
            'checkout_session_id' => 'cs_test_123',
            'amount' => 5000,
            'currency' => 'PHP',
            'status' => 'pending',
        ]);

        /** @var \App\Services\GhlWebhookService|\Mockery\MockInterface $mockGhlWebhook */
        $mockGhlWebhook = Mockery::mock(GhlWebhookService::class);
        $mockGhlWebhook->shouldReceive('sendPaymentCaptured')
            ->once()
            ->with(Mockery::on(function ($t) use ($transaction) {
                return $t->id === $transaction->id && $t->status === 'paid';
            }));

        $service = new WebhookProcessingService($mockGhlWebhook);

        $eventData = [
            'id' => 'cs_test_123',
            'attributes' => [
                'payment_intent' => ['id' => 'pi_123'],
                'payments' => [
                    [
                        'id' => 'pay_123',
                        'attributes' => ['source' => ['type' => 'gcash']],
                    ]
                ],
            ]
        ];

        $result = $service->processCheckoutSessionPaid($eventData, null);

        $this->assertTrue($result);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'paid',
            'payment_id' => 'pay_123',
            'payment_intent_id' => 'pi_123',
            'payment_method' => 'gcash',
        ]);
    }

    public function test_process_checkout_session_paid_passes_location_id_for_key_resolution()
    {
        // Transaction linked to a specific GHL location
        $transaction = Transaction::create([
            'checkout_session_id' => 'cs_loc_abc',
            'ghl_location_id' => 'loc_abc_123',
            'amount' => 8000,
            'currency' => 'PHP',
            'status' => 'pending',
        ]);

        /** @var \App\Services\GhlWebhookService|\Mockery\MockInterface $mockGhlWebhook */
        $mockGhlWebhook = Mockery::mock(GhlWebhookService::class);
        $mockGhlWebhook->shouldReceive('sendPaymentCaptured')->once();

        $service = new WebhookProcessingService($mockGhlWebhook);

        $eventData = [
            'id' => 'cs_loc_abc',
            'attributes' => [
                'payment_intent' => ['id' => 'pi_loc_abc'],
                'payments' => [
                    ['id' => 'pay_loc_abc', 'attributes' => ['source' => ['type' => 'card']]],
                ],
            ]
        ];

        // Pass the locationId — service should use per-location keys if available
        $result = $service->processCheckoutSessionPaid($eventData, 'loc_abc_123');

        $this->assertTrue($result);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'paid',
        ]);
    }

    public function test_process_payment_failed_updates_status()
    {
        $transaction = Transaction::create([
            'checkout_session_id' => 'cs_test_fail',
            'payment_id' => 'pay_fail_123',
            'amount' => 5000,
            'currency' => 'PHP',
            'status' => 'pending',
        ]);

        /** @var \App\Services\GhlWebhookService|\Mockery\MockInterface $mockGhlWebhook */
        $mockGhlWebhook = Mockery::mock(GhlWebhookService::class);
        $service = new WebhookProcessingService($mockGhlWebhook);

        $eventData = [
            'id' => 'pay_fail_123',
            'attributes' => [
                'last_payment_error' => 'insufficient_funds'
            ]
        ];

        $result = $service->processPaymentFailed($eventData);

        $this->assertTrue($result);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'failed',
        ]);

        $updatedTransaction = Transaction::find($transaction->id);
        $this->assertEquals('insufficient_funds', $updatedTransaction->metadata['failure_reason']);
    }

    public function test_process_payment_refunded_updates_status()
    {
        $transaction = Transaction::create([
            'payment_id' => 'pay_refund_123',
            'amount' => 5000,
            'currency' => 'PHP',
            'status' => 'paid',
        ]);

        /** @var \App\Services\GhlWebhookService|\Mockery\MockInterface $mockGhlWebhook */
        $mockGhlWebhook = Mockery::mock(GhlWebhookService::class);
        $service = new WebhookProcessingService($mockGhlWebhook);

        $eventData = [
            'id' => 'pay_refund_123',
            'attributes' => [
                'refunds' => [
                    'data' => [
                        [
                            'id' => 'rf_123',
                            'attributes' => [
                                'amount' => 5000,
                                'created_at' => now()->timestamp,
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $result = $service->processPaymentRefunded($eventData);

        $this->assertTrue($result);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'refunded',
        ]);
    }
}
