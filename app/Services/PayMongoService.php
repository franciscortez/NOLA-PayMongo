<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayMongoService
{
   protected string $baseUrl = 'https://api.paymongo.com/v1';
   protected bool $isProduction;

   // Dynamic per-location keys (optional — falls back to .env if not set)
   protected ?string $dynamicSecretKey = null;
   protected ?string $dynamicPublishableKey = null;

   public function __construct()
   {
      $this->isProduction = (bool) config('services.paymongo.is_production', false);
   }

   /**
    * Get the appropriate secret key.
    * Uses the per-location dynamic key if available, otherwise falls back to .env config.
    */
   public function getSecretKey(): string
   {
      if ($this->dynamicSecretKey) {
         return $this->dynamicSecretKey;
      }

      return $this->isProduction
         ? config('services.paymongo.live_secret_key')
         : config('services.paymongo.test_secret_key');
   }

   /**
    * Get the appropriate publishable key.
    * Uses the per-location dynamic key if available, otherwise falls back to .env config.
    */
   public function getPublishableKey(): string
   {
      if ($this->dynamicPublishableKey) {
         return $this->dynamicPublishableKey;
      }

      return $this->isProduction
         ? config('services.paymongo.live_publishable_key')
         : config('services.paymongo.test_publishable_key');
   }

   /**
    * Override production mode (e.g., based on GHL apiKey prefix).
    */
   public function setProduction(bool $isProduction): self
   {
      $clone = clone $this;
      $clone->isProduction = $isProduction;
      return $clone;
   }

   /**
    * Set dynamic per-location keys (used for multi-account support).
    * These override the .env config for the lifetime of this instance.
    */
   public function setDynamicKeys(string $secretKey, ?string $publishableKey = null): self
   {
      $clone = clone $this;
      $clone->dynamicSecretKey = $secretKey;
      $clone->dynamicPublishableKey = $publishableKey;
      // Automatically determine live/test mode from the key prefix
      $clone->isProduction = str_starts_with($secretKey, 'sk_live_');
      return $clone;
   }

   /**
    * Create a PayMongo Webhook for a specific location.
    * Returns the webhook secret (whsk_xxx) on success, or null on failure.
    */
   public function createWebhook(string $secretKey, string $locationId, bool $isRetry = false): ?array
   {
      $webhookUrl = rtrim(config('app.url'), '/') . "/api/webhook/paymongo/{$locationId}";

      $events = [
         'payment.paid',
         'payment.failed',
         'payment.refunded',
         'checkout_session.payment.paid',
      ];

      Log::info('PayMongo: Creating webhook', [
         'location_id' => $locationId,
         'url' => $webhookUrl,
      ]);

      $response = Http::withBasicAuth($secretKey, '')
         ->withHeaders(['Content-Type' => 'application/json'])
         ->post("{$this->baseUrl}/webhooks", [
            'data' => [
               'attributes' => [
                  'url' => $webhookUrl,
                  'events' => $events,
               ],
            ],
         ]);

      if (!$response->successful()) {
         $errors = $response->json('errors');
         $firstError = $errors[0] ?? [];

         // If the webhook already exists, we need to delete it and recreate it 
         // because PayMongo only provides the webhook secret (whsk_...) upon creation.
          if (!$isRetry && isset($firstError['code']) && $firstError['code'] === 'resource_exists') {
             Log::info('PayMongo: Webhook already exists. Attempting to delete and recreate...', ['location_id' => $locationId]);
             
             $existingWebhooks = $this->listWebhooks($secretKey);
             foreach ($existingWebhooks as $wh) {
                if (($wh['attributes']['url'] ?? '') === $webhookUrl) {
                   $this->disableWebhook($secretKey, $wh['id']);
                   Log::info('PayMongo: Deleted existing webhook', ['location_id' => $locationId, 'webhook_id' => $wh['id']]);
                }
             }
             
             // Try creating it again (recursion once)
             return $this->createWebhook($secretKey, $locationId, true);
          }

         Log::error('PayMongo: Failed to create webhook', [
            'location_id' => $locationId,
            'status' => $response->status(),
            'body' => $response->json(),
         ]);
         return null;
      }

      $data = $response->json('data');

      Log::info('PayMongo: Webhook created', [
         'location_id' => $locationId,
         'webhook_id' => $data['id'],
      ]);

      return [
         'id' => $data['id'],
         'secret_key' => $data['attributes']['secret_key'], // This is the webhook secret (whsk_xxx)
      ];
   }

   /**
    * List all existing webhooks for a given secret key.
    */
   public function listWebhooks(string $secretKey): array
   {
      $response = Http::withBasicAuth($secretKey, '')
         ->get("{$this->baseUrl}/webhooks");

      if (!$response->successful()) {
         return [];
      }

      return $response->json('data', []);
   }

   /**
    * Disable (delete) a webhook by its ID.
    */
   public function disableWebhook(string $secretKey, string $webhookId): bool
   {
      $response = Http::withBasicAuth($secretKey, '')
         ->delete("{$this->baseUrl}/webhooks/{$webhookId}");

      return $response->successful();
   }

   /**
    * Create a PayMongo Checkout Session.
    * Returns the checkout URL and session ID — redirect the customer there.
    */
   public function createCheckoutSession(array $payload): array
   {
      $secretKey = $this->getSecretKey();

      Log::info('PayMongo: Creating Checkout Session', [
         'is_production' => $this->isProduction,
         'amount' => $payload['amount'] ?? null,
         'payment_method_types' => $payload['payment_method_types'] ?? [],
      ]);

      $response = Http::withBasicAuth($secretKey, '')
         ->withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
         ])
         ->post("{$this->baseUrl}/checkout_sessions", [
            'data' => [
               'attributes' => $payload,
            ],
         ]);

      if (!$response->successful()) {
         Log::error('PayMongo: Checkout Session failed', [
            'status' => $response->status(),
            'body' => $response->body(),
            'payload' => $payload,
         ]);
         return [
            'success' => false,
            'error' => $response->json('errors.0.detail', 'Failed to create checkout session'),
         ];
      }

      $data = $response->json('data');

      Log::info('PayMongo: Checkout Session created', [
         'id' => $data['id'],
         'checkout_url' => $data['attributes']['checkout_url'] ?? null,
      ]);

      return [
         'success' => true,
         'id' => $data['id'],
         'checkout_url' => $data['attributes']['checkout_url'],
         'status' => $data['attributes']['status'] ?? 'active',
         'payment_intent_id' => $data['attributes']['payment_intent']['id'] ?? $data['attributes']['payment_intent']['attributes']['id'] ?? null,
      ];
   }

   /**
    * Retrieve a PaymentIntent to check its status (used by queryUrl verify).
    */
   public function retrievePaymentIntent(string $paymentIntentId): array
   {
      $response = Http::withBasicAuth($this->getSecretKey(), '')
         ->get("{$this->baseUrl}/payment_intents/{$paymentIntentId}");

      if (!$response->successful()) {
         Log::error('PayMongo: RetrievePaymentIntent failed', [
            'id' => $paymentIntentId,
            'body' => $response->json(),
         ]);
         return ['success' => false, 'error' => 'Failed to retrieve payment intent details.'];
      }

      $data = $response->json('data');

      return [
         'success' => true,
         'id' => $data['id'],
         'status' => $data['attributes']['status'],
         'amount' => $data['attributes']['amount'],
         'currency' => $data['attributes']['currency'],
         'payments' => $data['attributes']['payments'] ?? [],
      ];
   }

   /**
    * Retrieve a Payment by its ID.
    */
   public function retrievePayment(string $paymentId): array
   {
      $response = Http::withBasicAuth($this->getSecretKey(), '')
         ->get("{$this->baseUrl}/payments/{$paymentId}");

      if (!$response->successful()) {
         Log::error('PayMongo: RetrievePayment failed', [
            'id' => $paymentId,
            'body' => $response->json(),
         ]);
         return ['success' => false, 'error' => 'Failed to retrieve payment details.'];
      }

      $data = $response->json('data');

      return [
         'success' => true,
         'id' => $data['id'],
         'status' => $data['attributes']['status'],
         'amount' => $data['attributes']['amount'],
         'currency' => $data['attributes']['currency'],
         'paid_at' => $data['attributes']['paid_at'] ?? null,
      ];
   }

   /**
    * Retrieve a Checkout Session to check its status.
    */
   public function retrieveCheckoutSession(string $checkoutSessionId): array
   {
      $response = Http::withBasicAuth($this->getSecretKey(), '')
         ->get("{$this->baseUrl}/checkout_sessions/{$checkoutSessionId}");

      if (!$response->successful()) {
         Log::error('PayMongo: RetrieveCheckoutSession failed', [
            'id' => $checkoutSessionId,
            'body' => $response->json(),
         ]);
         return ['success' => false, 'error' => 'Failed to retrieve checkout session details.'];
      }

      $data = $response->json('data');

      return [
         'success' => true,
         'id' => $data['id'],
         'status' => $data['attributes']['status'] ?? null,
         'payment_intent' => $data['attributes']['payment_intent'] ?? null,
         'payments' => $data['attributes']['payments'] ?? [],
      ];
   }

   /**
    * Validate a PayMongo Secret Key by making a lightweight API call.
    */
   public function validateKey(?string $secretKey): bool
   {
      if (empty($secretKey)) {
         return false;
      }

      try {
         $response = Http::withBasicAuth($secretKey, '')
            ->get("{$this->baseUrl}/webhooks");

         return $response->successful();
      } catch (\Exception $e) {
         Log::error('PayMongo: Key validation exception', ['error' => $e->getMessage()]);
         return false;
      }
   }

   /**
    * Create a refund for a PayMongo payment.
    */
   public function refundPayment(string $paymentId, int $amount, string $reason = 'requested_by_customer'): array
   {
      $payload = [
         'data' => [
            'attributes' => [
               'amount' => $amount,
               'payment_id' => $paymentId,
               'reason' => $reason,
            ],
         ],
      ];

      Log::info('PayMongo: Creating refund', ['payment_id' => $paymentId, 'amount' => $amount]);

      $response = Http::withBasicAuth($this->getSecretKey(), '')
         ->post("{$this->baseUrl}/refunds", $payload);

      if (!$response->successful()) {
         Log::error('PayMongo: Refund failed', [
            'payment_id' => $paymentId,
            'body' => $response->json(),
         ]);
         return ['success' => false, 'error' => 'Refund process failed. Please contact support.'];
      }

      $data = $response->json('data');

      return [
         'success' => true,
         'id' => $data['id'],
         'amount' => $data['attributes']['amount'],
         'currency' => $data['attributes']['currency'],
         'status' => $data['attributes']['status'],
      ];
   }
}
