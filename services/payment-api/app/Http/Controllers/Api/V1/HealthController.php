<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Throwable;

class HealthController extends Controller
{
    public function live(): JsonResponse
    {
        return response()->json([
            'service' => 'payment-api',
            'status' => 'ok',
        ]);
    }

    public function ready(): JsonResponse
    {
        try {
            Redis::connection()->command('ping');

            if (! Cache::get('payment-worker-heartbeat')) {
                throw new RuntimeException('Payment worker heartbeat is unavailable.');
            }
        } catch (Throwable) {
            return response()->json([
                'type' => 'about:blank',
                'title' => 'Service unavailable',
                'status' => 503,
                'detail' => 'Redis or the payment worker is unavailable.',
                'code' => 'PAYMENT_SERVICE_UNAVAILABLE',
            ], 503, ['Content-Type' => 'application/problem+json']);
        }

        return response()->json([
            'service' => 'payment-api',
            'status' => 'ok',
        ]);
    }
}
