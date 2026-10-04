<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Redis;
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
        } catch (Throwable) {
            return response()->json([
                'service' => 'payment-api',
                'status' => 'unavailable',
            ], 503);
        }

        return response()->json([
            'service' => 'payment-api',
            'status' => 'ready',
        ]);
    }
}
