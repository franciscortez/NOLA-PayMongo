<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\CheckoutService;
use App\Http\Requests\CheckoutSessionRequest;


class CheckoutController extends Controller
{
   protected CheckoutService $checkoutService;

   public function __construct(CheckoutService $checkoutService)
   {
      $this->checkoutService = $checkoutService;
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
   public function createCheckoutSession(CheckoutSessionRequest $request)
   {
      // The data is already validated and sanitized by CheckoutSessionRequest
      $validated = $request->validated();

      $publishableKey = $request->input('publishable_key', '');
      $isLiveModeFallback = $request->input('is_live_mode', false);

      $result = $this->checkoutService->createSession(
         $validated,
         $publishableKey,
         $isLiveModeFallback
      );

      if (!$result['success']) {
         Log::warning('Checkout session creation failed', [
            'error' => $result['error'],
            'ghl_location_id' => $validated['ghl_location_id'] ?? null,
         ]);
         return response()->json([
            'error' => $result['error'],
         ], 500);
      }

      Log::info('Checkout session created', [
         'checkout_session_id' => $result['checkout_session_id'],
         'amount' => $validated['amount'] ?? null,
         'currency' => $validated['currency'] ?? 'PHP',
         'ghl_location_id' => $validated['ghl_location_id'] ?? null,
      ]);

      return response()->json([
         'checkout_url' => $result['checkout_url'],
         'checkout_session_id' => $result['checkout_session_id'],
      ]);
   }

   /**
    * Check payment status for a checkout session (used by iFrame JS polling).
    * GET /checkout/status/{sessionId}
    */
   public function checkStatus(string $sessionId)
   {
      $result = $this->checkoutService->checkStatus($sessionId);
      return response()->json($result);
   }

   /**
    * Success callback — PayMongo redirects here after successful payment.
    * In the popup flow this page is loaded inside the popup, not the GHL iFrame.
    */
   public function success(Request $request)
   {
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
      return view('checkout.cancel');
   }
}
