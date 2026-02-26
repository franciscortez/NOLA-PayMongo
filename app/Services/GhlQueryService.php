<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

class GhlQueryService
{
   /**
    * Verify a payment.
    */
   public function verifyPayment(string $chargeId, PayMongoService $service): array
   {
      if (!$chargeId) {
         Log::warning('GhlQueryService: Verify called without chargeId');
         return ['success' => false, 'failed' => true];
      }

      $transaction = Transaction::where('ghl_transaction_id', $chargeId)
         ->orWhere('checkout_session_id', $chargeId)
         ->orWhere('payment_intent_id', $chargeId)
         ->orWhere('payment_id', $chargeId)
         ->first();

      if ($transaction && $transaction->isPaid()) {
         Log::info('GhlQueryService: Verify from DB — paid', [
            'chargeId' => $chargeId,
            'transaction_id' => $transaction->id,
         ]);

         return [
            'success' => true,
            'chargeSnapshot' => [
               'id' => $transaction->payment_id ?? $chargeId,
               'status' => 'succeeded',
               'amount' => $transaction->amount / 100,
               'chargeId' => $chargeId,
               'chargedAt' => ($transaction->paid_at ?? $transaction->updated_at)->timestamp,
            ],
         ];
      }

      // If the transaction is failed, we can return early
      if ($transaction && $transaction->status === 'failed') {
         return ['success' => false, 'failed' => true];
      }

      // RACE CONDITION FIX: 
      // If transaction is pending, or if we couldn't find it in the DB (maybe it was just created),
      // we need to actively poll PayMongo right now to see if it actually succeeded before the webhook arrived.

      $isCheckoutSession = str_starts_with($chargeId, 'cs_');

      if (str_starts_with($chargeId, 'cs_')) {
         $result = $service->retrieveCheckoutSession($chargeId);
      } elseif (str_starts_with($chargeId, 'pay_')) {
         $result = $service->retrievePayment($chargeId);
      } else {
         $result = $service->retrievePaymentIntent($chargeId);
      }

      if ($transaction && $transaction->status === 'failed') {
         return ['success' => false, 'failed' => true];
      }

      if (!$result['success']) {
         Log::error('GhlQueryService: Failed to retrieve from API', ['chargeId' => $chargeId, 'result' => $result]);
         return ['success' => false, 'failed' => true];
      }

      $status = $result['status'] ?? null;
      $payments = $result['payments'] ?? [];
      $payment = !empty($payments) ? end($payments) : null;

      // If it's a checkout session, payment success is indicated by the linked payment intent or payments array.
      // For simplicity, we check if there's a successful payment inside.
      $isActuallyPaid = false;
      $paymentId = $chargeId;
      $paidAt = now();

      if ($status === 'succeeded' || $status === 'paid') {
         $isActuallyPaid = true;
         // Use paid_at from top level if available (for Payment objects)
         if (isset($result['paid_at'])) {
            $paidAt = \Carbon\Carbon::createFromTimestamp($result['paid_at']);
         }
         // Try finding it within nested payments if available (for Checkout Sessions or Intents)
         if ($payment && isset($payment['id'])) {
            $paymentId = $payment['id'];
            $paidAt = \Carbon\Carbon::createFromTimestamp($payment['attributes']['paid_at'] ?? $paidAt->timestamp);
         }
      } elseif ($payment && ($payment['attributes']['status'] ?? '') === 'paid') {
         $isActuallyPaid = true;
         $paymentId = $payment['id'];
         $paidAt = \Carbon\Carbon::createFromTimestamp($payment['attributes']['paid_at'] ?? now()->timestamp);
      }

      if ($isActuallyPaid) {
         Log::info('GhlQueryService: Race condition met. Actively verified payment as paid.', [
            'chargeId' => $chargeId,
            'paymentId' => $paymentId
         ]);

         if ($transaction && $transaction->isPending()) {
            $paymentIntentId = isset($result['payment_intent']) && is_array($result['payment_intent'])
               ? ($result['payment_intent']['id'] ?? $transaction->payment_intent_id)
               : $transaction->payment_intent_id;

            $transaction->update([
               'status' => 'paid',
               'payment_id' => $paymentId !== $chargeId ? $paymentId : $transaction->payment_id,
               'payment_intent_id' => $paymentIntentId,
               'paid_at' => $paidAt
            ]);
         }

         return [
            'success' => true,
            'chargeSnapshot' => [
               'id' => $paymentId,
               'status' => 'succeeded',
               'amount' => ($result['amount'] ?? ($transaction->amount ?? 0)) / 100,
               'chargeId' => $chargeId,
               'chargedAt' => $paidAt->timestamp,
            ],
         ];
      }


      return [
         'success' => true,
         'chargeSnapshot' => [
            'id' => $paymentId,
            'status' => 'succeeded',
            'amount' => ($result['amount'] ?? ($transaction->amount ?? 0)) / 100,
            'chargeId' => $chargeId,
            'chargedAt' => now()->timestamp,
         ],
      ];

      // If all checks fail or we could not confirm payment
      return ['success' => false, 'failed' => true];
   }

   /**
    * Process a refund (Full or Partial).
    */
   public function refundPayment(string $chargeId, float $amount, PayMongoService $service): array
   {
      if (!$chargeId || !$amount) {
         return [
            'success' => false,
            'message' => 'Missing chargeId or amount',
         ];
      }

      // Robustly find the transaction
      $transaction = Transaction::where('ghl_transaction_id', $chargeId)
         ->orWhere('checkout_session_id', $chargeId)
         ->orWhere('payment_intent_id', $chargeId)
         ->orWhere('payment_id', $chargeId)
         ->first();

      if (!$transaction || !$transaction->payment_id) {
         Log::warning('GhlQueryService: Refund — Transaction missing or no payment_id attached.', ['chargeId' => $chargeId]);
         return [
            'success' => false,
            'message' => 'Could not find a completed payment record to refund.',
         ];
      }

      $paymentId = $transaction->payment_id;
      $amountInCents = (int) round($amount * 100);

      // Verify we aren't over-refunding
      $remainingRefundable = $transaction->amount - $transaction->amount_refunded;
      if ($amountInCents > $remainingRefundable) {
         return [
            'success' => false,
            'message' => "Requested refund ($amountInCents) exceeds remaining refundable amount ($remainingRefundable)."
         ];
      }

      $result = $service->refundPayment($paymentId, $amountInCents);

      if (!$result['success']) {
         return [
            'success' => false,
            'message' => 'Refund failed: ' . json_encode($result['error'] ?? ''),
         ];
      }

      // Track the successful partial or full refund
      $newRefundedTotal = $transaction->amount_refunded + $amountInCents;
      $newStatus = ($newRefundedTotal >= $transaction->amount) ? 'refunded' : 'partially_refunded';

      $metadata = $transaction->metadata ?? [];
      $metadata['refunds'][] = [
         'id' => $result['id'],
         'amount' => $amountInCents,
         'created_at' => now()->toIso8601String()
      ];

      $transaction->update([
         'amount_refunded' => $newRefundedTotal,
         'status' => $newStatus,
         'metadata' => $metadata
      ]);

      return [
         'success' => true,
         'message' => 'Refund successful',
         'id' => $result['id'],
         'amount' => $amount,
         'currency' => strtoupper($result['currency'] ?? 'PHP'),
      ];
   }

}
