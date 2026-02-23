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
      $description = $request->input('description', 'Payment');
      $name = $request->input('name', 'Customer');
      $email = $request->input('email', 'customer@example.com');
      $phone = $request->input('phone');

      // Build success/cancel URLs with params GHL needs
      $transactionId = $request->input('transaction_id', '');
      $orderId = $request->input('order_id', '');

      $successUrl = url('/checkout/success') . '?' . http_build_query([
         'transaction_id' => $transactionId,
         'order_id' => $orderId,
      ]);

      $cancelUrl = url('/checkout/cancel') . '?' . http_build_query([
         'transaction_id' => $transactionId,
         'order_id' => $orderId,
      ]);

      // Determine available payment methods (QR PH always enabled, card if available)
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
    * Success callback — PayMongo redirects here after successful payment.
    * This page notifies the GHL parent window.
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
