<?php

use App\Http\Middleware\EnsureSetupIsAvailable;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\VerifyPaymentWebhookToken;
use App\Http\Responses\ProblemDetails;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'setup.available' => EnsureSetupIsAvailable::class,
            'payment.webhook' => VerifyPaymentWebhookToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReportDuplicates();
        $exceptions->dontFlash(['password', 'password_confirmation', 'payment_test_number', 'guest_token']);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ValidationException $exception, Request $request) {
            return ProblemDetails::validation($request, $exception->errors());
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            return ProblemDetails::response($request, 401, 'Unauthenticated', 'Authentication is required.', 'unauthenticated');
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            return ProblemDetails::response($request, 403, 'Forbidden', 'The authenticated principal is not authorized for this resource.', 'forbidden');
        });

        $exceptions->render(function (HttpException $exception, Request $request) {
            if ($exception->getStatusCode() === 419) {
                return ProblemDetails::response($request, 403, 'CSRF token mismatch', 'A valid CSRF token is required.', 'csrf_token_mismatch');
            }

            $problem = match ($exception->getStatusCode()) {
                400 => ['Bad request', 'The request could not be understood.', 'bad_request'],
                403 => ['Forbidden', 'The request is not authorized.', 'forbidden'],
                413 => ['Payload too large', 'The request body exceeds the allowed size.', 'payload_too_large'],
                415 => ['Unsupported media type', 'The request media type is not supported.', 'unsupported_media_type'],
                default => null,
            };

            return $problem === null
                ? null
                : ProblemDetails::response($request, $exception->getStatusCode(), ...$problem);
        });

        $exceptions->render(function (ThrottleRequestsException $exception, Request $request) {
            return ProblemDetails::response(
                $request,
                429,
                'Too many requests',
                'The request rate limit has been exceeded.',
                'rate_limit_exceeded',
                headers: $exception->getHeaders(),
            );
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $exception, Request $request) {
            return ProblemDetails::response($request, 404, 'Not found', 'The requested resource was not found.', 'not_found');
        });

        $exceptions->render(function (MethodNotAllowedHttpException $exception, Request $request) {
            return ProblemDetails::response($request, 405, 'Method not allowed', 'The request method is not supported for this resource.', 'method_not_allowed');
        });

        $exceptions->render(function (PostTooLargeException $exception, Request $request) {
            return ProblemDetails::response($request, 413, 'Payload too large', 'The request body exceeds the allowed size.', 'payload_too_large');
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if ($exception instanceof AuthenticationException
                || $exception instanceof AuthorizationException
                || $exception instanceof HttpException
                || $exception instanceof HttpResponseException
                || $exception instanceof ModelNotFoundException
                || $exception instanceof ThrottleRequestsException
                || $exception instanceof ValidationException) {
                return null;
            }

            if (! $request->is('api/*')) {
                return null;
            }

            return ProblemDetails::response($request, 500, 'Server error', 'The server could not complete the request.', 'server_error');
        });
    })->create();
