<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;
use App\Models\LocationToken;

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

        $isValid = false;
        $locationId = $request->route('locationId');
        
        // 1. Try to validate using per-location dynamic credentials
        if ($locationId) {
            $token = LocationToken::where('location_id', $locationId)->first();
            
            if ($token) {
                // If PayMongo sent a test signature, verify it against our stored test secret
                if (isset($signatureData['te']) && $token->paymongo_test_webhook_secret) {
                    $expectedTest = hash_hmac('sha256', $signatureString, $token->paymongo_test_webhook_secret);
                    if (hash_equals($expectedTest, $signatureData['te'])) {
                        $isValid = true;
                    }
                }
                // If PayMongo sent a live signature, verify it against our stored live secret
                if (isset($signatureData['li']) && $token->paymongo_live_webhook_secret) {
                    $expectedLive = hash_hmac('sha256', $signatureString, $token->paymongo_live_webhook_secret);
                    if (hash_equals($expectedLive, $signatureData['li'])) {
                        $isValid = true;
                    }
                }
            }
        }

        // 2. Fallback to global single-account credentials
        if (!$isValid) {
            $globalSecret = config('services.paymongo.webhook_secret');
            if ($globalSecret) {
                $expectedGlobal = hash_hmac('sha256', $signatureString, $globalSecret);
                if (isset($signatureData['te']) && hash_equals($expectedGlobal, $signatureData['te'])) {
                    $isValid = true;
                }
                if (isset($signatureData['li']) && hash_equals($expectedGlobal, $signatureData['li'])) {
                    $isValid = true;
                }
            }
        }

        if (config('app.debug')) {
            Log::debug('PayMongo webhook signature validation', [
                'location_id' => $locationId,
                'has_te' => isset($signatureData['te']),
                'has_li' => isset($signatureData['li']),
                'is_valid' => $isValid,
            ]);
        }

        if (!$isValid) {
            Log::warning('PayMongo Webhook: Signature verification failed', ['location_id' => $locationId]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
