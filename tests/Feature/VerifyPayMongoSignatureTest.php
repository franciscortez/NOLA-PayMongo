<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;
use Illuminate\Support\Facades\Config;
use App\Models\LocationToken;

class VerifyPayMongoSignatureTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        // Global fallback secret (legacy single-account .env mode)
        Config::set('services.paymongo.webhook_secret', 'global_secret_key');
    }

    // =========================================================
    // Existing: basic validation tests (legacy route)
    // =========================================================

    public function test_it_rejects_request_without_signature_header()
    {
        $response = $this->postJson('/api/webhook/paymongo', ['event' => 'test']);

        $response->assertStatus(401)
            ->assertJson(['error' => 'Unauthorized']);
    }

    public function test_it_rejects_malformed_signature_header()
    {
        $response = $this->withHeaders([
            'Paymongo-Signature' => 'invalid-format'
        ])->postJson('/api/webhook/paymongo', ['event' => 'test']);

        $response->assertStatus(401)
            ->assertJson(['error' => 'Unauthorized']);
    }

    public function test_it_rejects_invalid_signature()
    {
        $timestamp = time();
        $payload = json_encode(['event' => 'test']);

        $response = $this->call(
            'POST',
            '/api/webhook/paymongo',
            [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_PAYMONGO_SIGNATURE' => "t={$timestamp},te=wrong_signature,li=wrong_signature"],
            $payload
        );

        $response->assertStatus(401)
            ->assertJson(['error' => 'Unauthorized']);
    }

    public function test_it_accepts_valid_test_signature_using_global_fallback_secret()
    {
        $timestamp = time();
        $payloadArray = ['data' => ['type' => 'event']];
        $payload = json_encode($payloadArray);

        $signatureString = $timestamp . '.' . $payload;
        $validSignature = hash_hmac('sha256', $signatureString, 'global_secret_key');

        $response = $this->call(
            'POST',
            '/api/webhook/paymongo',
            [], [], [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_PAYMONGO_SIGNATURE' => "t={$timestamp},te={$validSignature}"
            ],
            $payload
        );

        // Signature passes — gets 422 from request validation (not 401)
        $response->assertStatus(422);
    }

    // =========================================================
    // NEW: Per-location webhook secret tests (multi-account)
    // =========================================================

    public function test_it_accepts_valid_test_signature_using_per_location_test_secret()
    {
        // Store a per-location test webhook secret in the DB
        $token = LocationToken::factory()->create([
            'location_id' => 'loc_test_abc',
            'paymongo_test_webhook_secret' => 'location_test_secret',
        ]);

        $timestamp = time();
        $payloadArray = ['data' => ['type' => 'event']];
        $payload = json_encode($payloadArray);

        $signatureString = $timestamp . '.' . $payload;
        $validSignature = hash_hmac('sha256', $signatureString, 'location_test_secret');

        $response = $this->call(
            'POST',
            '/api/webhook/paymongo/loc_test_abc',
            [], [], [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_PAYMONGO_SIGNATURE' => "t={$timestamp},te={$validSignature}"
            ],
            $payload
        );

        // Signature passes — 422 from request validation, not 401
        $response->assertStatus(422);
    }

    public function test_it_accepts_valid_live_signature_using_per_location_live_secret()
    {
        LocationToken::factory()->create([
            'location_id' => 'loc_live_abc',
            'paymongo_live_webhook_secret' => 'location_live_secret',
        ]);

        $timestamp = time();
        $payloadArray = ['data' => ['type' => 'event']];
        $payload = json_encode($payloadArray);

        $signatureString = $timestamp . '.' . $payload;
        $validSignature = hash_hmac('sha256', $signatureString, 'location_live_secret');

        $response = $this->call(
            'POST',
            '/api/webhook/paymongo/loc_live_abc',
            [], [], [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_PAYMONGO_SIGNATURE' => "t={$timestamp},li={$validSignature}" // 'li' = live
            ],
            $payload
        );

        $response->assertStatus(422);
    }

    public function test_it_rejects_signature_when_wrong_location_secret_used()
    {
        // Location has its own secret
        LocationToken::factory()->create([
            'location_id' => 'loc_wrong',
            'paymongo_test_webhook_secret' => 'location_secret_correct',
        ]);

        $timestamp = time();
        $payload = json_encode(['data' => ['type' => 'event']]);

        // Build signature using the WRONG secret
        $signatureString = $timestamp . '.' . $payload;
        $wrongSignature = hash_hmac('sha256', $signatureString, 'totally_wrong_secret');

        $response = $this->call(
            'POST',
            '/api/webhook/paymongo/loc_wrong',
            [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_PAYMONGO_SIGNATURE' => "t={$timestamp},te={$wrongSignature}"],
            $payload
        );

        $response->assertStatus(401);
    }

    public function test_it_falls_back_to_global_secret_when_no_location_token_exists()
    {
        // No LocationToken in DB for this locationId — should fall back to .env global secret
        $timestamp = time();
        $payloadArray = ['data' => ['type' => 'event']];
        $payload = json_encode($payloadArray);

        $signatureString = $timestamp . '.' . $payload;
        $validSignature = hash_hmac('sha256', $signatureString, 'global_secret_key');

        $response = $this->call(
            'POST',
            '/api/webhook/paymongo/loc_nonexistent',
            [], [], [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_PAYMONGO_SIGNATURE' => "t={$timestamp},te={$validSignature}"
            ],
            $payload
        );

        // Falls back to global secret — signature passes, 422 from request validation
        $response->assertStatus(422);
    }
}
