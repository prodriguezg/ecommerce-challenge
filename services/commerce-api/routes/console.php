<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$expiration = Schedule::command('reservations:expire');

match ((int) config('api.checkout.reservation_worker_interval_seconds')) {
    1 => $expiration->everySecond(),
    2 => $expiration->everyTwoSeconds(),
    10 => $expiration->everyTenSeconds(),
    15 => $expiration->everyFifteenSeconds(),
    20 => $expiration->everyTwentySeconds(),
    30 => $expiration->everyThirtySeconds(),
    60 => $expiration->everyMinute(),
    default => $expiration->everyFiveSeconds(),
};
