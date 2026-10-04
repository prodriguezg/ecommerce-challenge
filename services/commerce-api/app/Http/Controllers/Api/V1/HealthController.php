<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function live(): JsonResponse
    {
        return response()->json([
            'service' => 'commerce-api',
            'status' => 'ok',
        ]);
    }

    public function ready(): JsonResponse
    {
        try {
            DB::connection()->getPdo();
        } catch (Throwable) {
            return response()->json([
                'service' => 'commerce-api',
                'status' => 'unavailable',
            ], 503);
        }

        return response()->json([
            'service' => 'commerce-api',
            'status' => 'ready',
        ]);
    }
}
