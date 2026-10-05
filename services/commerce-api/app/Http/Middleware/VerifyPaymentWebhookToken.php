<?php

namespace App\Http\Middleware;

use App\Http\Responses\ProblemDetails;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyPaymentWebhookToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $configuredToken = config('api.checkout.payment_webhook_token');
        $providedToken = $request->bearerToken();

        if (! is_string($configuredToken) || $configuredToken === '' || ! is_string($providedToken) || ! hash_equals($configuredToken, $providedToken)) {
            return ProblemDetails::response(
                $request,
                401,
                'Unauthenticated',
                'A valid payment webhook bearer token is required.',
                'unauthenticated',
            );
        }

        return $next($request);
    }
}
