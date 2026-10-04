<?php

namespace App\Providers;

use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
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
        Event::listen(Looping::class, function (): void {
            Cache::put(
                'payment-worker-heartbeat',
                now()->toIso8601String(),
                now()->addSeconds((int) config('payment.worker_heartbeat_ttl_seconds')),
            );
        });
    }
}
