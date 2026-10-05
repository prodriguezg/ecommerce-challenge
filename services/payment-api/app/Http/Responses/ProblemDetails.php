<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProblemDetails
{
    /**
     * @param  array<string, list<string>>  $errors
     * @param  array<string, mixed>  $headers
     */
    public static function response(
        Request $request,
        int $status,
        string $title,
        string $detail,
        string $code,
        array $errors = [],
        array $headers = [],
    ): JsonResponse {
        $body = [
            'type' => rtrim((string) config('app.url'), '/').'/problems/'.$code,
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            'instance' => $request->getPathInfo(),
            'code' => $code,
        ];

        if ($errors !== []) {
            $body['errors'] = $errors;
        }

        return response()->json($body, $status, ['Content-Type' => 'application/problem+json', ...$headers]);
    }
}
