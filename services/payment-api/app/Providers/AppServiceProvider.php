<?php

namespace App\Providers;

use App\Http\Responses\ProblemDetails;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
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
        RateLimiter::for('payments', fn (Request $request): Limit => Limit::perMinute(
            (int) config('payment.request_rate_limit'),
        )->by('payment:'.$request->ip())->response(
            fn (Request $request, array $headers) => ProblemDetails::response(
                $request,
                429,
                'Too many requests',
                'The request rate limit has been exceeded.',
                'rate_limit_exceeded',
                headers: $headers,
            ),
        ));

        Event::listen(Looping::class, function (): void {
            Cache::put(
                'payment-worker-heartbeat',
                now()->toIso8601String(),
                now()->addSeconds((int) config('payment.worker_heartbeat_ttl_seconds')),
            );
        });
    }
}
