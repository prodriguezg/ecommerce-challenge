<?php

use App\Http\Middleware\EnsureSetupIsAvailable;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\VerifyPaymentWebhookToken;
use App\Http\Responses\ProblemDetails;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
            if ($exception->getStatusCode() !== 419) {
                return null;
            }

            return ProblemDetails::response($request, 403, 'CSRF token mismatch', 'A valid CSRF token is required.', 'csrf_token_mismatch');
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
    })->create();
