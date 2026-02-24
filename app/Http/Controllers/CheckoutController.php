<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\PayMongoService;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

class CheckoutController extends Controller
{
   protected PayMongoService $payMongoService;

   public function __construct(PayMongoService $payMongoService)
   {
      $this->payMongoService = $payMongoService;
   }

   /**
    * Serve the checkout page that GHL loads inside its iFrame.
    */
   public function show()
   {
      return view('checkout.index');
   }

   /**
    * AJAX endpoint: Create a PayMongo Checkout Session and return the URL.
    */
   public function createCheckoutSession(Request $request)
   {
      $request->validate([
         'amount' => 'required|integer|min:100',
         'currency' => 'required|string|size:3',
         'description' => 'nullable|string|max:255',
         'name' => 'nullable|string|max:255',
         'email' => 'nullable|email|max:255',
         'phone' => 'nullable|string|max:20',
      ]);

      $amount = $request->input('amount');
      $currency = strtoupper($request->input('currency'));
      $description = $request->input('description');
      $name = $request->input('name');
      $email = $request->input('email');
      $phone = $request->input('phone');

      $transactionId = $request->input('transaction_id', '');
      $orderId = $request->input('order_id', '');

      // Build success/cancel URLs (no checkout_session_id here — the iFrame JS
      // handles result communication via polling after the popup closes)
      $successUrl = url('/checkout/success') . '?' . http_build_query([
         'transaction_id' => $transactionId,
         'order_id' => $orderId,
      ]);

      $cancelUrl = url('/checkout/cancel') . '?' . http_build_query([
         'transaction_id' => $transactionId,
         'order_id' => $orderId,
      ]);

      // Payment methods available in the Philippines
      $paymentMethodTypes = ['qrph', 'card', 'gcash', 'grab_pay', 'paymaya'];

      // Build the checkout session payload
      $payload = [
         'send_email_receipt' => true,
         'show_description' => true,
         'show_line_items' => true,
         'description' => $description,
         'payment_method_types' => $paymentMethodTypes,
         'success_url' => $successUrl,
         'cancel_url' => $cancelUrl,
         'line_items' => [
            [
               'name' => $description,
               'quantity' => 1,
               'amount' => $amount,
               'currency' => $currency,
            ],
         ],
         'metadata' => array_filter([
            'ghl_transaction_id' => $transactionId,
            'ghl_order_id' => $orderId,
            'ghl_location_id' => $request->input('location_id', ''),
         ]),
      ];

      // Add billing info if available
      $billing = array_filter([
         'name' => $name,
         'email' => $email,
         'phone' => $phone,
      ]);
      if (!empty($billing)) {
         $payload['billing'] = $billing;
      }

      // Determine if this is a live or test transaction based on GHL's publishable key
      $publishableKey = $request->input('publishable_key', '');
      $isLiveMode = str_starts_with($publishableKey, 'pk_live_') || $request->input('is_live_mode', false);
      $this->payMongoService = $this->payMongoService->setProduction($isLiveMode);

      $result = $this->payMongoService->createCheckoutSession($payload);

      if (!$result['success']) {
         Log::error('CheckoutController: Failed to create Checkout Session', $result);
         return response()->json([
            'error' => $result['error'] ?? 'Failed to create checkout session',
         ], 500);
      }

      // Save transaction to database
      Transaction::create([
         'checkout_session_id' => $result['id'],
         'ghl_transaction_id' => $transactionId ?: null,
         'ghl_order_id' => $orderId ?: null,
         'ghl_location_id' => $request->input('location_id') ?: null,
         'amount' => $amount,
         'currency' => $currency,
         'description' => $description,
         'status' => 'pending',
         'customer_name' => $name,
         'customer_email' => $email,
         'is_live_mode' => $isLiveMode,
      ]);

      Log::info('CheckoutController: Transaction saved', [
         'checkout_session_id' => $result['id'],
      ]);

      return response()->json([
         'checkout_url' => $result['checkout_url'],
         'checkout_session_id' => $result['id'],
      ]);
   }

   /**
    * Check payment status for a checkout session (used by iFrame JS polling).
    * GET /checkout/status/{sessionId}
    */
   public function checkStatus(string $sessionId)
   {
      // Check local DB first
      $transaction = Transaction::where('checkout_session_id', $sessionId)->first();

      if ($transaction && $transaction->isPaid()) {
         return response()->json([
            'status' => 'paid',
            'charge_id' => $transaction->payment_id
               ?? $transaction->payment_intent_id
               ?? $transaction->checkout_session_id,
         ]);
      }

      // Fallback: retrieve from PayMongo API
      $result = $this->payMongoService->retrieveCheckoutSession($sessionId);

      if (!$result['success']) {
         return response()->json(['status' => 'unknown']);
      }

      $pmStatus = $result['status'] ?? 'active';

      // PayMongo checkout session statuses: active, expired, paid
      if ($pmStatus === 'paid') {
         // Update DB if webhook hasn't arrived yet
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

         return response()->json([
            'status' => 'paid',
            'charge_id' => $transaction->payment_id
               ?? $transaction->payment_intent_id
               ?? $sessionId,
         ]);
      }

      return response()->json(['status' => $pmStatus]);
   }

   /**
    * Success callback — PayMongo redirects here after successful payment.
    * In the popup flow this page is loaded inside the popup, not the GHL iFrame.
    */
   public function success(Request $request)
   {
      Log::info('Checkout: Success callback', $request->all());

      return view('checkout.success', [
         'transactionId' => $request->query('transaction_id', ''),
         'orderId' => $request->query('order_id', ''),
      ]);
   }

   /**
    * Cancel callback — PayMongo redirects here if customer cancels.
    */
   public function cancel(Request $request)
   {
      Log::info('Checkout: Cancel callback', $request->all());

      return view('checkout.cancel');
   }
}
