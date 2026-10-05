<?php

namespace App\Providers;

use App\Http\Responses\ProblemDetails;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use LogicException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();

        $this->assertSecureRuntimeConfiguration();

        RateLimiter::for('login', fn (Request $request): Limit => $this->authenticationLimit($request, 'login'));
        RateLimiter::for('setup', fn (Request $request): Limit => $this->authenticationLimit($request, 'setup'));
        RateLimiter::for('customer-registration', fn (Request $request): Limit => $this->authenticationLimit($request, 'customer_registration'));
        RateLimiter::for('guest-registration', fn (Request $request): Limit => $this->authenticationLimit($request, 'guest_registration'));
        RateLimiter::for('guest-orders', fn (Request $request): Limit => $this->limit(
            $request,
            (int) config('api.checkout.guest_order_rate_limit'),
            'guest-order:'.hash('sha256', (string) $request->query('guest_token')).'|'.$request->ip(),
        ));
        RateLimiter::for('checkouts', fn (Request $request): Limit => $this->limit(
            $request,
            (int) config('api.rate_limits.checkout'),
            'checkout:'.($request->user()?->getAuthIdentifier() ?? $request->ip()),
        ));
        RateLimiter::for('image-uploads', fn (Request $request): Limit => $this->limit(
            $request,
            (int) config('api.rate_limits.image_upload'),
            'image-upload:'.($request->user()?->getAuthIdentifier() ?? $request->ip()),
        ));
        RateLimiter::for('csv-uploads', fn (Request $request): Limit => $this->limit(
            $request,
            (int) config('api.rate_limits.csv_upload'),
            'csv-upload:'.($request->user()?->getAuthIdentifier() ?? $request->ip()),
        ));
        RateLimiter::for('payment-webhooks', fn (Request $request): Limit => $this->limit(
            $request,
            (int) config('api.checkout.payment_webhook_rate_limit'),
            'payment-webhook:'.$request->ip(),
        ));
    }

    private function authenticationLimit(Request $request, string $name): Limit
    {
        $email = mb_strtolower(trim((string) $request->input('email')));

        return $this->limit(
            $request,
            (int) config("authentication.rate_limits.{$name}"),
            $name.':'.$email.'|'.$request->ip(),
        );
    }

    private function limit(Request $request, int $attempts, string $key): Limit
    {
        return Limit::perMinute($attempts)
            ->by($key)
            ->response(fn (Request $request, array $headers) => ProblemDetails::response(
                $request,
                429,
                'Too many requests',
                'The request rate limit has been exceeded.',
                'rate_limit_exceeded',
                headers: $headers,
            ));
    }

    private function assertSecureRuntimeConfiguration(): void
    {
        if (! config('security.require_tls')) {
            return;
        }

        if (! str_starts_with((string) config('app.url'), 'https://')
            || config('session.secure') !== true
            || config('session.http_only') !== true) {
            throw new LogicException('TLS mode requires an HTTPS APP_URL plus secure, HTTP-only session cookies.');
        }
    }
}
