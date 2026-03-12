<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayMongoWebhookRequest;
use App\Services\WebhookProcessingService;
use Illuminate\Support\Facades\Log;
use App\Models\WebhookLog;

class PayMongoWebhookController extends Controller
{
   protected WebhookProcessingService $webhookProcessingService;

   public function __construct(WebhookProcessingService $webhookProcessingService)
   {
      $this->webhookProcessingService = $webhookProcessingService;
   }

   /**
    * Handle incoming PayMongo webhook events.
    * POST /api/webhook/paymongo/{locationId?}
    */
   public function handle(PayMongoWebhookRequest $request, ?string $locationId = null)
   {
      $payload = $request->validated();

      Log::info('PayMongo webhook received', [
         'event_type' => $payload['data']['attributes']['type'] ?? 'unknown',
         'event_id' => $payload['data']['id'] ?? null,
         'location_id' => $locationId,
      ]);

      $eventType = $payload['data']['attributes']['type'] ?? null;
      $eventData = $payload['data']['attributes']['data'] ?? null;
      $eventId = $payload['data']['id'] ?? null;

      if (!$eventType || !$eventData || !$eventId) {
         Log::warning('PayMongo Webhook: Missing event type, data, or ID');
         return response()->json(['message' => 'Invalid webhook payload'], 400);
      }

      $webhookLog = WebhookLog::firstOrCreate(
         ['event_id' => $eventId],
         [
            'event_type' => $eventType,
            'payload' => $payload,
            'status' => 'pending'
         ]
      );

      if (!$webhookLog->wasRecentlyCreated && $webhookLog->status === 'processed') {
         Log::info('PayMongo webhook duplicate skipped', ['event_id' => $eventId]);
         return response()->json(['message' => 'Already processed'], 200);
      }

      try {
         $handled = match ($eventType) {
            'checkout_session.payment.paid' => $this->webhookProcessingService->processCheckoutSessionPaid($eventData, $locationId),
            'payment.paid' => $this->webhookProcessingService->processPaymentPaid($eventData),
            'payment.failed' => $this->webhookProcessingService->processPaymentFailed($eventData),
            'payment.refunded' => $this->webhookProcessingService->processPaymentRefunded($eventData),
            default => null,
         };

         if ($handled === null) {
            $webhookLog->update(['status' => 'skipped', 'error_message' => 'Unhandled event type']);
            return $this->handleUnknownEvent($eventType);
         }

         $webhookLog->update(['status' => 'processed']);
         return response()->json(['message' => 'OK'], 200);

      } catch (\Exception $e) {
         $webhookLog->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
         Log::error('PayMongo webhook processing failed', [
            'event_id' => $eventId,
            'event_type' => $eventType,
            'location_id' => $locationId,
            'error' => $e->getMessage(),
         ]);
         return response()->json(['message' => 'Error processing webhook'], 500);
      }

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
