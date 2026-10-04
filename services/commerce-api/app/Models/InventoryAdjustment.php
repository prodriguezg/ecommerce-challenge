<?php

namespace App\Models;

use App\Enums\InventoryAdjustmentReason;
use App\Models\Concerns\PreventsModification;
use Database\Factories\InventoryAdjustmentFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded(['*'])]
class InventoryAdjustment extends DomainModel
{
    /** @use HasFactory<InventoryAdjustmentFactory> */
    use HasFactory, PreventsModification;

    public const UPDATED_AT = null;

    /** @return BelongsTo<Inventory, $this> */
    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /** @return BelongsTo<Import, $this> */
    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    protected function casts(): array
    {
        return ['reason' => InventoryAdjustmentReason::class];
    }
}
