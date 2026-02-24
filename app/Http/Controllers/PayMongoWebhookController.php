<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Services\PayMongoService;
use App\Services\GhlWebhookService;
use Illuminate\Support\Facades\Log;

class PayMongoWebhookController extends Controller
{
   protected PayMongoService $payMongoService;
   protected GhlWebhookService $ghlWebhookService;

   public function __construct(PayMongoService $payMongoService, GhlWebhookService $ghlWebhookService)
   {
      $this->payMongoService = $payMongoService;
      $this->ghlWebhookService = $ghlWebhookService;
   }

   /**
    * Handle incoming PayMongo webhook events.
    * POST /api/webhook/paymongo
    */
   public function handle(Request $request)
   {
      $payload = $request->all();

      Log::info('PayMongo Webhook: Received', [
         'type' => $payload['data']['attributes']['type'] ?? 'unknown',
      ]);

      $eventType = $payload['data']['attributes']['type'] ?? null;
      $eventData = $payload['data']['attributes']['data'] ?? null;

      if (!$eventType || !$eventData) {
         Log::warning('PayMongo Webhook: Missing event type or data');
         return response()->json(['message' => 'Invalid webhook payload'], 400);
      }

      return match ($eventType) {
         'checkout_session.payment.paid' => $this->handleCheckoutSessionPaid($eventData),
         'payment.paid' => $this->handlePaymentPaid($eventData),
         'payment.failed' => $this->handlePaymentFailed($eventData),
         'payment.refunded' => $this->handlePaymentRefunded($eventData),
         default => $this->handleUnknownEvent($eventType),
      };
   }

   /**
    * Handle checkout_session.payment.paid event.
    */
   protected function handleCheckoutSessionPaid(array $eventData)
   {
      $checkoutSessionId = $eventData['id'] ?? null;
      $attributes = $eventData['attributes'] ?? [];
      $payments = $attributes['payments'] ?? [];

      Log::info('PayMongo Webhook: checkout_session.payment.paid', [
         'checkout_session_id' => $checkoutSessionId,
         'payments_count' => count($payments),
      ]);

      $transaction = Transaction::where('checkout_session_id', $checkoutSessionId)->first();

      if (!$transaction) {
         Log::warning('PayMongo Webhook: Transaction not found for checkout session', [
            'checkout_session_id' => $checkoutSessionId,
         ]);
         return response()->json(['message' => 'Transaction not found'], 200);
      }

      // Extract payment details from the first payment
      $payment = $payments[0] ?? null;
      if ($payment) {
         $paymentAttrs = $payment['attributes'] ?? [];
         $paymentSource = $paymentAttrs['source'] ?? [];

         $transaction->update([
            'status' => 'paid',
            'payment_id' => $payment['id'] ?? null,
            'payment_intent_id' => $attributes['payment_intent']['id'] ?? $transaction->payment_intent_id,
            'payment_method' => $paymentSource['type'] ?? null,
            'paid_at' => now(),
            'metadata' => array_merge($transaction->metadata ?? [], [
               'webhook_payment' => $payment,
            ]),
         ]);

         Log::info('PayMongo Webhook: Transaction updated to paid', [
            'transaction_id' => $transaction->id,
            'payment_method' => $paymentSource['type'] ?? null,
            'payment_id' => $payment['id'] ?? null,
         ]);

         // Notify GHL that the payment was captured
         $this->ghlWebhookService->sendPaymentCaptured($transaction->fresh());
      }

      return response()->json(['message' => 'OK'], 200);
   }

   /**
    * Handle payment.paid event.
    */
   protected function handlePaymentPaid(array $eventData)
   {
      $paymentId = $eventData['id'] ?? null;
      $attributes = $eventData['attributes'] ?? [];
      $paymentSource = $attributes['source'] ?? [];

      Log::info('PayMongo Webhook: payment.paid', ['payment_id' => $paymentId]);

      // Try to find the transaction by payment_id or payment_intent_id
      $paymentIntentId = $attributes['payment_intent_id'] ?? null;

      $transaction = Transaction::where('payment_id', $paymentId)
         ->orWhere('payment_intent_id', $paymentIntentId)
         ->first();

      if ($transaction && $transaction->status !== 'paid') {
         $transaction->update([
            'status' => 'paid',
            'payment_id' => $paymentId,
            'payment_method' => $paymentSource['type'] ?? $transaction->payment_method,
            'paid_at' => now(),
         ]);

         Log::info('PayMongo Webhook: Transaction updated via payment.paid', [
            'transaction_id' => $transaction->id,
         ]);

         // Notify GHL that the payment was captured
         $this->ghlWebhookService->sendPaymentCaptured($transaction->fresh());
      }

      return response()->json(['message' => 'OK'], 200);
   }

   /**
    * Handle payment.failed event.
    */
   protected function handlePaymentFailed(array $eventData)
   {
      $paymentId = $eventData['id'] ?? null;
      $attributes = $eventData['attributes'] ?? [];
      $paymentIntentId = $attributes['payment_intent_id'] ?? null;

      Log::info('PayMongo Webhook: payment.failed', ['payment_id' => $paymentId]);

      $transaction = Transaction::where('payment_id', $paymentId)
         ->orWhere('payment_intent_id', $paymentIntentId)
         ->first();

      if ($transaction) {
         $transaction->update([
            'status' => 'failed',
            'metadata' => array_merge($transaction->metadata ?? [], [
               'failure_reason' => $attributes['last_payment_error'] ?? null,
            ]),
         ]);
      }

      return response()->json(['message' => 'OK'], 200);
   }

   /**
    * Handle payment.refunded event.
    */
   protected function handlePaymentRefunded(array $eventData)
   {
      $paymentId = $eventData['id'] ?? null;

      Log::info('PayMongo Webhook: payment.refunded', ['payment_id' => $paymentId]);

      $transaction = Transaction::where('payment_id', $paymentId)->first();

      if ($transaction) {
         $transaction->update(['status' => 'refunded']);
      }

      return response()->json(['message' => 'OK'], 200);
   }

   /**
    * Handle unknown webhook events (log and acknowledge).
    */
   protected function handleUnknownEvent(string $eventType)
   {
      Log::info('PayMongo Webhook: Unhandled event type', ['type' => $eventType]);
      return response()->json(['message' => 'OK'], 200);
   }
}
