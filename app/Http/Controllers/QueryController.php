<?php

namespace App\Http\Controllers;

use App\Http\Requests\QueryUrlRequest;
use App\Services\PayMongoService;
use App\Services\GhlQueryService;
use Illuminate\Support\Facades\Log;

class QueryController extends Controller
{
   protected PayMongoService $payMongoService;
   protected GhlQueryService $ghlQueryService;

   public function __construct(PayMongoService $payMongoService, GhlQueryService $ghlQueryService)
   {
      $this->payMongoService = $payMongoService;
      $this->ghlQueryService = $ghlQueryService;
   }

   /**
    * Handle all incoming queryUrl requests from GHL.
    * GHL POSTs JSON with a "type" field to dispatch the action.
    */
   public function handle(QueryUrlRequest $request)
   {
      $payload = $request->validated();

      $type = $payload['type'];
      $apiKey = $payload['apiKey'] ?? null;

      Log::channel('payments')->info('GHL query received', [
         'type' => $type,
         'charge_id' => $payload['chargeId'] ?? null,
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
   protected function handleVerify(QueryUrlRequest $request, PayMongoService $service)
   {
      $chargeId = $request->input('chargeId', '');
      $result = $this->ghlQueryService->verifyPayment($chargeId, $service);
      Log::channel('payments')->info('GHL verify result', [
         'charge_id' => $chargeId,
         'success' => $result['success'] ?? !($result['failed'] ?? false),
      ]);
      return response()->json($result);
   }

   /**
    * Process a refund.
    *
    * GHL sends: { type: "refund", amount, transactionId, chargeId, apiKey }
    * We must return: { success: true, id, amount, currency, message }
    */
   protected function handleRefund(QueryUrlRequest $request, PayMongoService $service)
   {
      $chargeId = $request->input('chargeId', '');
      $amount = (float) $request->input('amount', 0);
      $result = $this->ghlQueryService->refundPayment($chargeId, $amount, $service);
      Log::channel('payments')->info('GHL refund result', [
         'charge_id' => $chargeId,
         'amount' => $amount,
         'success' => $result['success'] ?? false,
      ]);
      return response()->json($result);
   }

   /**
    * List saved payment methods for a contact.
    * Placeholder — will be implemented with Card Vaulting in a future step.
    */
   protected function handleListPaymentMethods(QueryUrlRequest $request)
   {
      Log::info('QueryController: list_payment_methods called (not yet implemented)');

      // Return empty array — no saved methods yet
      return response()->json([]);
   }

   /**
    * Charge a saved payment method.
    * Placeholder — will be implemented with Card Vaulting in a future step.
    */
   protected function handleChargePayment(QueryUrlRequest $request, PayMongoService $service)
   {
      Log::info('QueryController: charge_payment called (not yet implemented)');

      return response()->json([
         'success' => false,
         'failed' => true,
         'message' => 'Saved payment method charging is not yet implemented',
      ]);
   }
}
