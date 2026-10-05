<?php

namespace App\Console\Commands;

use App\Services\ReservationExpirationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reservations:expire {--limit= : Maximum reservations to claim}')]
#[Description('Atomically claim and expire overdue inventory reservations')]
class ExpireReservations extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ReservationExpirationService $expiration): int
    {
        $limit = $this->option('limit');
        $batchSize = $limit === null
            ? (int) config('api.checkout.reservation_expiration_batch_size')
            : (int) $limit;

        if ($batchSize < 1 || $batchSize > 1000) {
            $this->error('The limit must be between 1 and 1000.');

            return self::INVALID;
        }

        $expired = $expiration->expireBatch($batchSize);
        $this->info("Expired {$expired} reservation(s).");

        return self::SUCCESS;
    }
}
