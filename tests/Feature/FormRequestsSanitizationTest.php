<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FormRequestsSanitizationTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Route::post('/test-checkout', function (\App\Http\Requests\CheckoutSessionRequest $request) {
            return response()->json($request->all());
        });

        Route::post('/test-query', function (\App\Http\Requests\QueryUrlRequest $request) {
            return response()->json($request->all());
        });

        Route::post('/test-provider', function (\App\Http\Requests\ProviderConfigRequest $request) {
            return response()->json($request->all());
        });

        Route::post('/test-webhook', function (\App\Http\Requests\PayMongoWebhookRequest $request) {
            return response()->json($request->all());
        });
    }

    public function test_checkout_session_request_sanitizes_html_and_validates()
    {
        $response = $this->postJson('/test-checkout', [
            'amount' => 5000,
            'currency' => 'PHP',
            'name' => '<script>alert("xss")</script>John Doe',
            'product_details' => [
                [
                    'name' => '<b>Test Item</b>',
                    'price' => 5000,
                    'qty' => 1
                ]
            ]
        ]);

        $response->assertStatus(200);
        $data = $response->json();

        // strip_tags removes the <script> tags, leaving the interior text behind
        $this->assertEquals('alert("xss")John Doe', $data['name']);

        // <b> tags removed
        $this->assertEquals('Test Item', $data['product_details'][0]['name']);
    }

    public function test_query_url_request_sanitizes_html()
    {
        $response = $this->postJson('/test-query', [
            'type' => 'verify',
            'chargeId' => 'pay_12345',
            'transactionId' => '<iframe src="malicious"></iframe>12345',
            'amount' => 1000,
        ]);

        $response->assertStatus(200);
        $this->assertEquals('12345', $response->json('transactionId'));
    }

    public function test_provider_config_request_sanitizes_html()
    {
        // location_id has regex /^[a-zA-Z0-9_-]+$/
        $response = $this->postJson('/test-provider', [
            'location_id' => 'valid-location-123'
        ]);
        $response->assertStatus(200);

        // Send a payload that strips to something that STILL fails the regex (spaces and special chars)
        $responseFail = $this->postJson('/test-provider', [
            'location_id' => 'invalid location !@#'
        ]);
        $responseFail->assertStatus(422);
    }

    public function test_webhook_request_validates_strict_structure()
    {
        $responseValid = $this->postJson('/test-webhook', [
            'data' => [
                'id' => 'evt_valid123',
                'type' => 'event',
                'attributes' => [
                    'type' => 'payment.paid',
                    'data' => [
                        'id' => 'pi_123'
                    ]
                ]
            ]
        ]);

        $responseValid->assertStatus(200);

        $responseInvalid = $this->postJson('/test-webhook', [
            'data' => [
                'id' => 'evt_valid123',
                'type' => 'malicious_event', // not allowed "event"
                'attributes' => []
            ]
        ]);

        $responseInvalid->assertStatus(422);
    }
}
