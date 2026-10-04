<?php

namespace App\Models\Concerns;

use LogicException;

trait PreventsModification
{
    protected static function bootPreventsModification(): void
    {
        static::updating(function (): never {
            throw new LogicException('Immutable records cannot be updated.');
        });

        static::deleting(function (): never {
            throw new LogicException('Immutable records cannot be deleted.');
        });
    }
}
