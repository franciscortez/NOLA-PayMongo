<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\Log;
use App\Models\LocationToken;

class CheckoutService
{
   protected PayMongoService $payMongoService;

   public function __construct(PayMongoService $payMongoService)
   {
      $this->payMongoService = $payMongoService;
   }

   /**
    * Resolve the PayMongoService instance with dynamic keys for this location.
    */
   private function resolvePayMongoService(bool $isLiveMode, ?string $locationId): PayMongoService
   {
      $service = $this->payMongoService->setProduction($isLiveMode);

      if ($locationId) {
         $token = LocationToken::where('location_id', $locationId)->first();
         if ($token) {
            $secretKey = $isLiveMode ? $token->paymongo_live_secret_key : $token->paymongo_test_secret_key;
            if ($secretKey) {
               return $service->setDynamicKeys($secretKey);
            }
         }
      }

      return $service;
   }

   /**
    * Create a Checkout Session and save the transaction.
    */
   public function createSession(array $data, ?string $publishableKey = null, bool $isLiveModeFallback = false): array
   {
      $amount = $data['amount'];
      $currency = strtoupper($data['currency']);
      $description = $data['description'] ?? '';
      $name = $data['name'] ?? null;
      $email = $data['email'] ?? null;
      $phone = $data['phone'] ?? null;
      $transactionId = $data['transaction_id'] ?? '';
      $orderId = $data['order_id'] ?? '';
      $invoiceId = $data['invoice_id'] ?? '';
      $locationId = $data['location_id'] ?? '';

      $successUrl = url('/checkout/success') . '?' . http_build_query([
         'transaction_id' => $transactionId,
         'order_id' => $orderId,
      ]);

      $cancelUrl = url('/checkout/cancel') . '?' . http_build_query([
         'transaction_id' => $transactionId,
         'order_id' => $orderId,
      ]);

      // Determine supported payment methods based on currency
      // PayMongo e-wallets and QRPH only support PHP. USD only supports cards.
      if ($currency === 'PHP') {
         $paymentMethodTypes = ['qrph', 'card', 'gcash', 'grab_pay', 'paymaya'];
      } elseif ($currency === 'USD') {
         $paymentMethodTypes = ['card'];
      } else {
         return [
            'success' => false,
            'error' => "The currency '{$currency}' is not supported. Use PHP or USD.",
         ];
      }

      // Build line items from GHL product details or fallback to description
      $lineItems = [];
      $productDetails = $data['product_details'] ?? [];

      if (!empty($productDetails) && is_array($productDetails)) {
         foreach ($productDetails as $item) {
            $lineItems[] = [
               'name' => isset($item['name']) ? substr($item['name'], 0, 255) : 'Product',
               'quantity' => isset($item['qty']) ? (int) $item['qty'] : 1,
               'amount' => isset($item['price']) ? (int) round((float) $item['price'] * 100) : $amount,
               'currency' => $currency,
            ];
         }
      } else {
         // Fallback to single line item based on total amount and description
         $lineItems[] = [
            'name' => $description ?: 'Payment',
            'quantity' => 1,
            'amount' => $amount,
            'currency' => $currency,
         ];
      }

      $payload = [
         'send_email_receipt' => true,
         'show_description' => true,
         'show_line_items' => true,
         'description' => $description,
         'payment_method_types' => $paymentMethodTypes,
         'success_url' => $successUrl,
         'cancel_url' => $cancelUrl,
         'line_items' => $lineItems,
      ];

      $billing = array_filter([
         'name' => $name,
         'email' => $email,
         'phone' => $phone,
      ]);

      if (isset($data['address']) && is_array($data['address'])) {
         $addr = array_filter($data['address']);
         if (!empty($addr)) {
            $billing['address'] = $addr;
         }
      }

      if (!empty($billing)) {
         $payload['billing'] = $billing;
      }

      $metadata = array_filter([
         'ghl_transaction_id' => $transactionId,
         'ghl_order_id' => $orderId,
         'ghl_invoice_id' => $invoiceId,
         'ghl_location_id' => $locationId,
      ]);

      // PayMongo rigorously requires metadata to not be blank.
      // If we don't have any metadata IDs to pass, we must omit the field entirely.
      if (!empty($metadata)) {
         $payload['metadata'] = $metadata;
      }

      $isLiveMode = str_starts_with($publishableKey ?? '', 'pk_live_') || $isLiveModeFallback;
      $service = $this->resolvePayMongoService($isLiveMode, $locationId);

      $result = $service->createCheckoutSession($payload);

      if (!$result['success']) {
         Log::error('CheckoutService: Failed to create Checkout Session', $result);
         return [
            'success' => false,
            'error' => $result['error'] ?? 'Failed to create checkout session',
         ];
      }

      Transaction::create([
         'checkout_session_id' => $result['id'],
         'payment_intent_id' => $result['payment_intent_id'] ?? null,
         'ghl_transaction_id' => $transactionId ?: null,
         'ghl_order_id' => $orderId ?: null,
         'ghl_invoice_id' => $invoiceId ?: null,
         'ghl_location_id' => $locationId ?: null,
         'amount' => $amount,
         'currency' => $currency,
         'description' => $description,
         'status' => 'pending',
         'customer_name' => $name,
         'customer_email' => $email,
         'is_live_mode' => $isLiveMode,
      ]);

      Log::info('CheckoutService: Transaction saved', [
         'checkout_session_id' => $result['id'],
      ]);

      return [
         'success' => true,
         'checkout_url' => $result['checkout_url'],
         'checkout_session_id' => $result['id'],
      ];
   }

   /**
    * Check status of a checkout session.
    */
   public function checkStatus(string $sessionId): array
   {
      $transaction = Transaction::where('checkout_session_id', $sessionId)->first();

      if ($transaction && $transaction->isPaid()) {
         return [
            'status' => 'paid',
            'charge_id' => $transaction->payment_id
               ?? $transaction->payment_intent_id
               ?? $transaction->checkout_session_id,
         ];
      }

      $isLiveMode = $transaction ? (bool) $transaction->is_live_mode : false;
      $locationId = $transaction ? $transaction->ghl_location_id : null;
      $service = $this->resolvePayMongoService($isLiveMode, $locationId);

      $result = $service->retrieveCheckoutSession($sessionId);

      if (!$result['success']) {
         return ['status' => 'unknown'];
      }

      $pmStatus = $result['status'] ?? 'active';

      if ($pmStatus === 'paid') {
         if ($transaction) {
            $paymentIntent = $result['payment_intent'] ?? null;
            $payments = $result['payments'] ?? [];
            $payment = $payments[0] ?? null;

            $transaction->update([
               'status' => 'paid',
               'payment_intent_id' => $paymentIntent['id'] ?? $transaction->payment_intent_id,
               'payment_id' => $payment['id'] ?? $transaction->payment_id,
               'paid_at' => now(),
            ]);
         }

         return [
            'status' => 'paid',
            'charge_id' => $transaction->payment_id
               ?? $transaction->payment_intent_id
               ?? $sessionId,
         ];
      }

      return ['status' => $pmStatus];
   }
}
