<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;
use App\Models\Transaction;
use App\Models\WebhookLog;
use App\Models\LocationToken;
use Illuminate\Support\Facades\Config;
use Illuminate\Foundation\Testing\WithoutMiddleware;

class PayMongoWebhookControllerTest extends TestCase
{
    use DatabaseMigrations, WithoutMiddleware;

    // =========================================================
    // Existing: basic webhook processing tests
    // =========================================================

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
                        'attributes' => ['source' => ['type' => 'card']]
                    ]
                ]
            ]
        ];

        $response = $this->postJson('/api/webhook/paymongo', $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('webhook_logs', [
            'event_id' => 'evt_123',
            'status' => 'processed'
        ]);
    }

    public function test_skips_duplicate_webhook(): void
    {
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
                    'data' => ['id' => 'pay_456']
                ]
            ]
        ];

        $response = $this->postJson('/api/webhook/paymongo', $payload);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Already processed']);

        $this->assertDatabaseCount('webhook_logs', 1);
    }

    // =========================================================
    // NEW: Per-location webhook route tests (multi-account)
    // =========================================================

    public function test_processes_webhook_on_per_location_route(): void
    {
        LocationToken::factory()->create([
            'location_id' => 'loc_multi_abc',
        ]);

        $payload = [
            'data' => [
                'id' => 'evt_789ab',
                'type' => 'event',
                'attributes' => [
                    'type' => 'payment.paid',
                    'data' => [
                        'id' => 'pay_789',
                        'attributes' => ['source' => ['type' => 'gcash']]
                    ]
                ]
            ]
        ];

        // Hit the per-location route
        $response = $this->postJson('/api/webhook/paymongo/loc_multi_abc', $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('webhook_logs', [
            'event_id' => 'evt_789ab',
            'status' => 'processed'
        ]);
    }

    public function test_skips_duplicate_on_per_location_route(): void
    {
        WebhookLog::create([
            'event_id' => 'evt_locduplicate',
            'event_type' => 'payment.paid',
            'payload' => ['fake' => 'data'],
            'status' => 'processed'
        ]);

        $payload = [
            'data' => [
                'id' => 'evt_locduplicate',
                'type' => 'event',
                'attributes' => [
                    'type' => 'payment.paid',
                    'data' => ['id' => 'pay_dup']
                ]
            ]
        ];

        $response = $this->postJson('/api/webhook/paymongo/loc_multi_abc', $payload);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Already processed']);
    }
}
