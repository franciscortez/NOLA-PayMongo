<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\LocationToken;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use App\Services\PayMongoService;
use PHPUnit\Framework\Attributes\Test;

class PaymentFlowIntegrationTest extends TestCase
{
   use RefreshDatabase;

   protected function setUp(): void
   {
      parent::setUp();

      Config::set('services.paymongo.test_secret_key', 'sk_test_123');
      Config::set('services.paymongo.test_publishable_key', 'pk_test_123');
      Config::set('services.paymongo.live_secret_key', 'sk_live_123');
      Config::set('services.paymongo.live_publishable_key', 'pk_live_123');
      Config::set('services.paymongo.webhook_secret', 'whsk_test_123');
   }

   #[Test]
   public function it_can_process_a_full_payment_flow()
   {
      // 1. GHL iFrame sends a request to create a checkout session
      Http::fake([
         'api.paymongo.com/v1/checkout_sessions' => Http::response([
            'data' => [
               'id' => 'cs_integrat10n',
               'attributes' => [
                  'checkout_url' => 'https://checkout.paymongo.com/cs_integrat10n',
                  'status' => 'active',
                  'payment_intent' => [
                     'id' => 'pi_integrat10n'
                  ]
               ]
            ]
         ], 200),
         'api.paymongo.com/v1/checkout_sessions/cs_integrat10n' => Http::response([
            'data' => [
               'id' => 'cs_integrat10n',
               'attributes' => [
                  'payment_intent' => [
                     'id' => 'pi_integrat10n',
                  ],
                  'payments' => [
                     [
                        'id' => 'pay_integrat10n',
                        'attributes' => [
                           'source' => ['type' => 'card'],
                           'status' => 'paid',
                           'paid_at' => 1610000000
                        ]
                     ]
                  ]
               ]
            ]
         ], 200)
      ]);

      $createSessionResponse = $this->postJson('/checkout/create-session', [
         'amount' => 10000,
         'currency' => 'PHP',
         'product_details' => [
            ['name' => 'Consulting', 'price' => 10000, 'qty' => 1]
         ],
         'contact' => [
            'name' => 'John Doe',
            'email' => 'john@example.com'
         ],
         'order_id' => 'order_123',
         'transaction_id' => 'txn_123',
         'location_id' => 'loc_123',
         'publishable_key' => 'pk_test_123'
      ]);

      $createSessionResponse->assertStatus(200)
         ->assertJson([
            'checkout_url' => 'https://checkout.paymongo.com/cs_integrat10n',
            'checkout_session_id' => 'cs_integrat10n'
         ]);

      $this->assertDatabaseHas('transactions', [
         'checkout_session_id' => 'cs_integrat10n',
         'payment_intent_id' => 'pi_integrat10n',
         'ghl_transaction_id' => 'txn_123',
         'ghl_order_id' => 'order_123',
         'ghl_location_id' => 'loc_123',
         'status' => 'pending',
         'amount' => 10000
      ]);

      // 2. PayMongo webhook arrives indicating the session is paid
      $webhookPayload = [
         'data' => [
            'id' => 'evt_123xyz',
            'type' => 'event',
            'attributes' => [
               'type' => 'checkout_session.payment.paid',
               'data' => [
                  'id' => 'cs_integrat10n',
                  'attributes' => [
                     'payment_intent' => [
                        'id' => 'pi_integrat10n',
                        'attributes' => [
                           'payments' => [
                              [
                                 'id' => 'pay_integrat10n',
                                 'attributes' => [
                                    'source' => ['type' => 'card'],
                                    'status' => 'paid',
                                    'paid_at' => 1610000000
                                 ]
                              ]
                           ]
                        ]
                     ]
                  ]
               ]
            ]
         ]
      ];

      // Ensure token exists so webhook dispatch job can send to GHL
      LocationToken::create([
         'location_id' => 'loc_123',
         'access_token' => 'access123',
         'refresh_token' => 'refresh123',
         'expires_at' => now()->addDays(1),
         'user_type' => 'Location'
      ]);

      Http::fake([
         'services.leadconnectorhq.com/payments/custom-provider/webhook' => Http::response(['success' => true], 200)
      ]);

      $timestamp = time();
      $payloadString = json_encode($webhookPayload);
      $signatureString = $timestamp . '.' . $payloadString;
      $webhookSignature = hash_hmac('sha256', $signatureString, 'whsk_test_123');

      $webhookResponse = $this->postJson('/api/webhook/paymongo', $webhookPayload, [
         'Paymongo-Signature' => "t={$timestamp},te={$webhookSignature},li="
      ]);

      $webhookResponse->assertStatus(200)
         ->assertJson(['message' => 'OK']);

      // Check if DB updated correctly
      $this->assertDatabaseHas('transactions', [
         'checkout_session_id' => 'cs_integrat10n',
         'status' => 'paid',
         'payment_id' => 'pay_integrat10n'
      ]);

      $this->assertDatabaseHas('webhook_logs', [
         'event_id' => 'evt_123xyz',
         'status' => 'processed'
      ]);

      // 3. GHL calls the queryUrl to verify the payment
      Http::fake([
         'api.paymongo.com/v1/payment_intents/pi_integrat10n' => Http::response([
            'data' => [
               'id' => 'pi_integrat10n',
               'attributes' => [
                  'status' => 'succeeded',
                  'amount' => 10000,
                  'currency' => 'PHP',
                  'payments' => [
                     [
                        'id' => 'pay_integrat10n',
                        'attributes' => [
                           'status' => 'paid'
                        ]
                     ]
                  ]
               ]
            ]
         ], 200)
      ]);

      $verifyResponse = $this->postJson('/api/query', [
         'type' => 'verify',
         'apiKey' => 'sk_test_123',
         'transactionId' => 'txn_123', // Send transactionId for fallback since webhook might lag, but in this test it's already there
         'chargeId' => 'cs_integrat10n'
      ]);

      $verifyResponse->assertStatus(200)
         ->assertJson([
            'success' => true,
            'chargeSnapshot' => [
               'id' => 'pay_integrat10n',
               'status' => 'succeeded'
            ]
         ]);
   }
}
