<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class HealthCheckController extends Controller
{
    /**
     * Check the overall health of the application.
     */
    public function index()
    {
        // 1. Check DB
        try {
            DB::connection()->getPdo();
            $dbStatus = 'connected';
        } catch (\Exception $e) {
            $dbStatus = 'disconnected';
        }

        // 2. Check Cache
        try {
            Cache::put('health_check', true, 10);
            $cacheStatus = Cache::get('health_check') ? 'ok' : 'failed';
        } catch (\Exception $e) {
            $cacheStatus = 'failed';
        }

        // 3. Check important Env variables
        $envStatus = config('services.paymongo.live_secret_key') ? 'configured' : 'missing';

        $isHealthy = $dbStatus === 'connected' && $cacheStatus === 'ok' && $envStatus === 'configured';

        return response()->json([
            'status' => $isHealthy ? 'OK' : 'ERROR',
            'database' => $dbStatus,
            'cache' => $cacheStatus,
            'paymongo_keys_present' => $envStatus,
            'timestamp' => now()->toIso8601String(),
        ], $isHealthy ? 200 : 503);
    }
}
