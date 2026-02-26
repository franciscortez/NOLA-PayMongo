<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class VerifyPayMongoSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $signatureHeader = $request->header('Paymongo-Signature');

        if (!$signatureHeader) {
            Log::warning('PayMongo Webhook: Missing signature header');
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Parse the header components: t=timestamp, te=test signature, li=live signature
        $parts = explode(',', $signatureHeader);
        $signatureData = [];

        foreach ($parts as $part) {
            $kv = explode('=', $part, 2);
            if (count($kv) === 2) {
                $signatureData[trim($kv[0])] = trim($kv[1]);
            }
        }

        if (!isset($signatureData['t']) || (!isset($signatureData['te']) && !isset($signatureData['li']))) {
            Log::warning('PayMongo Webhook: Malformed signature header');
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $timestamp = $signatureData['t'];
        $payload = $request->getContent();

        $signatureString = $timestamp . '.' . $payload;
        $webhookSecret = config('services.paymongo.webhook_secret');

        if (!$webhookSecret) {
            Log::error('PayMongo Webhook: Webhook secret is not configured.');
            return response()->json(['error' => 'Internal Server Error'], 500);
        }

        $expectedSignature = hash_hmac('sha256', $signatureString, $webhookSecret);

        // Debug logging only when LOG_LEVEL=debug to avoid leaking payload in production
        if (config('app.debug')) {
            Log::debug('PayMongo webhook signature validation', [
                'has_te' => isset($signatureData['te']),
                'has_li' => isset($signatureData['li']),
            ]);
        }

        // Check against both test (te) and live (li) signatures found in the header
        $isValid = false;

        if (isset($signatureData['te']) && hash_equals($expectedSignature, $signatureData['te'])) {
            $isValid = true;
        }

        if (isset($signatureData['li']) && hash_equals($expectedSignature, $signatureData['li'])) {
            $isValid = true;
        }

        if (!$isValid) {
            Log::warning('PayMongo Webhook: Signature verification failed');
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
