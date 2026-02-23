<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\PayMongoService;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

class QueryController extends Controller
{
   protected PayMongoService $payMongoService;

   public function __construct(PayMongoService $payMongoService)
   {
      $this->payMongoService = $payMongoService;
   }

   /**
    * Handle all incoming queryUrl requests from GHL.
    * GHL POSTs JSON with a "type" field to dispatch the action.
    */
   public function handle(Request $request)
   {
      $type = $request->input('type');
      $apiKey = $request->input('apiKey');

      Log::info('QueryController: Incoming GHL query', [
         'type' => $type,
         'payload' => $request->all(),
      ]);

      // Determine which PayMongo secret key to use based on the apiKey GHL sends.
      // If the apiKey matches a test key, use test; otherwise use live.
      $service = $this->resolveService($apiKey);

      return match ($type) {
         'verify' => $this->handleVerify($request, $service),
         'refund' => $this->handleRefund($request, $service),
         'list_payment_methods' => $this->handleListPaymentMethods($request),
         'charge_payment' => $this->handleChargePayment($request, $service),
         default => response()->json(['error' => 'Unknown query type: ' . $type], 400),
      };
   }

   /**
    * Resolve which PayMongo service instance (test vs live) to use.
    */
   protected function resolveService(?string $apiKey): PayMongoService
   {
      // If the apiKey GHL sends matches our test secret key, use test mode
      $isProduction = ($apiKey !== config('services.paymongo.test_secret_key'));

      return $this->payMongoService->setProduction($isProduction);
   }

   /**
    * Verify a payment after the iFrame reports success.
    *
    * GHL sends: { type: "verify", transactionId, apiKey, chargeId, subscriptionId? }
    * We must return: { success: true } or { failed: true }
    */
   protected function handleVerify(Request $request, PayMongoService $service)
   {
      $chargeId = $request->input('chargeId');

      if (!$chargeId) {
         Log::warning('QueryController: Verify called without chargeId');
         return response()->json(['failed' => true]);
      }

      // Try finding the transaction in our DB first (faster, no API call)
      $transaction = Transaction::where('ghl_transaction_id', $chargeId)
         ->orWhere('checkout_session_id', $chargeId)
         ->orWhere('payment_intent_id', $chargeId)
         ->first();

      if ($transaction && $transaction->isPaid()) {
         Log::info('QueryController: Verify from DB — paid', [
            'chargeId' => $chargeId,
            'transaction_id' => $transaction->id,
         ]);

         return response()->json([
            'success' => true,
            'chargeSnapshot' => [
               'id' => $transaction->payment_id ?? $chargeId,
               'status' => 'succeeded',
               'amount' => $transaction->amount / 100,
               'chargeId' => $chargeId,
               'chargedAt' => ($transaction->paid_at ?? $transaction->updated_at)->timestamp,
            ],
         ]);
      }

      if ($transaction && $transaction->isPending()) {
         return response()->json(['success' => false]);
      }

      if ($transaction && $transaction->status === 'failed') {
         return response()->json(['failed' => true]);
      }

      // Fallback: hit PayMongo API if no DB record
      $result = $service->retrievePaymentIntent($chargeId);

      if (!$result['success']) {
         Log::error('QueryController: Failed to verify', ['chargeId' => $chargeId]);
         return response()->json(['failed' => true]);
      }

      $status = $result['status'];

      if ($status === 'succeeded') {
         $payment = $result['payments'][0] ?? null;
         $paymentId = $payment['id'] ?? $chargeId;

         return response()->json([
            'success' => true,
            'chargeSnapshot' => [
               'id' => $paymentId,
               'status' => 'succeeded',
               'amount' => $result['amount'] / 100,
               'chargeId' => $chargeId,
               'chargedAt' => now()->timestamp,
            ],
         ]);
      }

      if (in_array($status, ['processing', 'awaiting_next_action'])) {
         return response()->json(['success' => false]);
      }

      return response()->json(['failed' => true]);
   }

   /**
    * Process a refund.
    *
    * GHL sends: { type: "refund", amount, transactionId, chargeId, apiKey }
    * We must return: { success: true, id, amount, currency, message }
    */
   protected function handleRefund(Request $request, PayMongoService $service)
   {
      $chargeId = $request->input('chargeId');
      $amount = $request->input('amount');

      if (!$chargeId || !$amount) {
         return response()->json([
            'success' => false,
            'message' => 'Missing chargeId or amount',
         ]);
      }

      // First retrieve the PaymentIntent to get the actual payment ID
      $piResult = $service->retrievePaymentIntent($chargeId);

      if (!$piResult['success'] || empty($piResult['payments'])) {
         return response()->json([
            'success' => false,
            'message' => 'Could not find the payment to refund',
         ]);
      }

      // Get the payment ID from the PaymentIntent's payments array
      $paymentId = $piResult['payments'][0]['id'];

      // Convert decimal amount to cents for PayMongo
      $amountInCents = (int) round($amount * 100);

      $result = $service->refundPayment($paymentId, $amountInCents);

      if (!$result['success']) {
         return response()->json([
            'success' => false,
            'message' => 'Refund failed: ' . json_encode($result['error'] ?? ''),
         ]);
      }

      // Update transaction status in DB
      $transaction = Transaction::where('payment_id', $paymentId)
         ->orWhere('payment_intent_id', $chargeId)
         ->first();

      if ($transaction) {
         $transaction->update(['status' => 'refunded']);
      }

      return response()->json([
         'success' => true,
         'message' => 'Refund successful',
         'id' => $result['id'],
         'amount' => $amount,
         'currency' => strtoupper($result['currency'] ?? 'PHP'),
      ]);
   }

   /**
    * List saved payment methods for a contact.
    * Placeholder — will be implemented with Card Vaulting in a future step.
    */
   protected function handleListPaymentMethods(Request $request)
   {
      Log::info('QueryController: list_payment_methods called (not yet implemented)');

      // Return empty array — no saved methods yet
      return response()->json([]);
   }

   /**
    * Charge a saved payment method.
    * Placeholder — will be implemented with Card Vaulting in a future step.
    */
   protected function handleChargePayment(Request $request, PayMongoService $service)
   {
      Log::info('QueryController: charge_payment called (not yet implemented)');

      return response()->json([
         'success' => false,
         'failed' => true,
         'message' => 'Saved payment method charging is not yet implemented',
      ]);
   }
}
