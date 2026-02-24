<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CheckoutService;
use Illuminate\Support\Facades\Log;

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

      $publishableKey = $request->input('publishable_key', '');
      $isLiveModeFallback = $request->input('is_live_mode', false);

      $result = $this->checkoutService->createSession(
         $request->all(),
         $publishableKey,
         $isLiveModeFallback
      );

      if (!$result['success']) {
         return response()->json([
            'error' => $result['error'],
         ], 500);
      }

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
