<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProblemDetails
{
    /**
     * @param  array<string, list<string>>  $errors
     * @param  array<string, mixed>  $headers
     * @param  array<string, mixed>  $extensions
     */
    public static function response(
        Request $request,
        int $status,
        string $title,
        string $detail,
        string $code,
        array $errors = [],
        array $headers = [],
        array $extensions = [],
    ): JsonResponse {
        $body = [
            'type' => rtrim((string) config('app.url'), '/').'/problems/'.$code,
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            'instance' => $request->getRequestUri(),
            'code' => $code,
        ];

        if ($errors !== []) {
            $body['errors'] = $errors;
        }

        $body = [...$body, ...$extensions];

        return response()->json(
            $body,
            $status,
            ['Content-Type' => 'application/problem+json', ...$headers],
        );
    }

    /** @param array<string, list<string>> $errors */
    public static function validation(Request $request, array $errors): JsonResponse
    {
        return self::response(
            $request,
            422,
            'Validation failed',
            'One or more fields are invalid.',
            'validation_error',
            $errors,
        );
    }
}
