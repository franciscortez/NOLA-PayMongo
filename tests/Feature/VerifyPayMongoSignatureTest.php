<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Support\Facades\Config;

class VerifyPayMongoSignatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.paymongo.webhook_secret', 'test_secret_key');
    }

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

        $response = $this->withHeaders([
            'Paymongo-Signature' => "t={$timestamp},te=wrong_signature,li=wrong_signature"
        ])->call(
                'POST',
                '/api/webhook/paymongo',
                [],
                [],
                [],
                ['CONTENT_TYPE' => 'application/json', 'HTTP_PAYMONGO_SIGNATURE' => "t={$timestamp},te=wrong_signature,li=wrong_signature"],
                $payload
            );

        $response->assertStatus(401)
            ->assertJson(['error' => 'Unauthorized']);
    }

    public function test_it_accepts_valid_test_signature()
    {
        $timestamp = time();
        $payloadArray = [
            'data' => [
                'type' => 'event'
            ]
        ];
        $payload = json_encode($payloadArray);

        $signatureString = $timestamp . '.' . $payload;
        $validSignature = hash_hmac('sha256', $signatureString, 'test_secret_key');

        $response = $this->call(
            'POST',
            '/api/webhook/paymongo',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_PAYMONGO_SIGNATURE' => "t={$timestamp},te={$validSignature}"
            ],
            $payload // Pass the exact JSON string to ensure hash matches $request->getContent()
        );

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }
}
