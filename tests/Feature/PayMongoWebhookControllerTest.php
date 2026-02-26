<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Transaction;
use App\Models\WebhookLog;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Config;
use Illuminate\Foundation\Testing\WithoutMiddleware;

class PayMongoWebhookControllerTest extends TestCase
{
    use DatabaseMigrations, WithoutMiddleware;


    /**
     * Test a new webhook payload creates a log and processes.
     */
    public function test_processes_new_webhook_and_creates_log(): void
    {
        $payload = [
            'data' => [
                'id' => 'evt_123',
                'type' => 'event',
                'attributes' => [
                    'type' => 'payment.paid',
                    'data' => [
                        'id' => 'pay_123',
                        'attributes' => [
                            // mock data
                            'source' => ['type' => 'card']
                        ]
                    ]
                ]
            ]
        ];

        $response = $this->postJson('/api/webhook/paymongo', $payload);

        // We expect OK because processPaymentPaid will return true, even if 
        // no transaction is found
        $response->assertStatus(200);

        $this->assertDatabaseHas('webhook_logs', [
            'event_id' => 'evt_123',
            'status' => 'processed'
        ]);
    }

    /**
     * Test idempotency: Duplicate webhook is skipped.
     */
    public function test_skips_duplicate_webhook(): void
    {
        // Insert a processed record
        WebhookLog::create([
            'event_id' => 'evt_duplicate456',
            'event_type' => 'payment.paid',
            'payload' => ['fake' => 'data'],
            'status' => 'processed'
        ]);

        $payload = [
            'data' => [
                'id' => 'evt_duplicate456',
                'type' => 'event',
                'attributes' => [
                    'type' => 'payment.paid',
                    'data' => [
                        'id' => 'pay_456'
                    ]
                ]
            ]
        ];

        $response = $this->postJson('/api/webhook/paymongo', $payload);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Already processed']);

        // Assert it wasn't duplicated
        $this->assertDatabaseCount('webhook_logs', 1);
    }
}
