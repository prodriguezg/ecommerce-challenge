<?php

namespace App\Providers;

use App\Http\Responses\ProblemDetails;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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

        RateLimiter::for('login', fn (Request $request): Limit => $this->authenticationLimit($request, 'login'));
        RateLimiter::for('setup', fn (Request $request): Limit => $this->authenticationLimit($request, 'setup'));
        RateLimiter::for('registration', fn (Request $request): Limit => $this->authenticationLimit($request, 'registration'));
        RateLimiter::for('payment-webhooks', fn (Request $request): Limit => Limit::perMinute(
            (int) config('api.checkout.payment_webhook_rate_limit'),
        )->by($request->ip()));
    }

    private function authenticationLimit(Request $request, string $name): Limit
    {
        $email = mb_strtolower(trim((string) $request->input('email')));

        return Limit::perMinute((int) config("authentication.rate_limits.{$name}"))
            ->by($email.'|'.$request->ip())
            ->response(fn (Request $request, array $headers) => ProblemDetails::response(
                $request,
                429,
                'Too many requests',
                'The request rate limit has been exceeded.',
                'rate_limit_exceeded',
                headers: $headers,
            ));
    }
}
