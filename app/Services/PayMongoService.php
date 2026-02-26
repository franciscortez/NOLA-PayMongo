<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayMongoService
{
   protected string $baseUrl = 'https://api.paymongo.com/v1';
   protected bool $isProduction;

   public function __construct()
   {
      $this->isProduction = (bool) config('services.paymongo.is_production', false);
   }

   /**
    * Get the appropriate secret key based on environment.
    */
   public function getSecretKey(): string
   {
      return $this->isProduction
         ? config('services.paymongo.live_secret_key')
         : config('services.paymongo.test_secret_key');
   }

   /**
    * Get the appropriate publishable key based on environment.
    */
   public function getPublishableKey(): string
   {
      return $this->isProduction
         ? config('services.paymongo.live_publishable_key')
         : config('services.paymongo.test_publishable_key');
   }

   /**
    * Override production mode (e.g., based on GHL apiKey).
    */
   public function setProduction(bool $isProduction): self
   {
      $clone = clone $this;
      $clone->isProduction = $isProduction;
      return $clone;
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
         return ['success' => false, 'error' => $response->json()];
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
         return ['success' => false, 'error' => $response->json()];
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
         return ['success' => false, 'error' => $response->json()];
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
   public function validateKey(string $secretKey): bool
   {
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
         return ['success' => false, 'error' => $response->json()];
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
