<?php

namespace App\Models;

use App\Enums\ImportMode;
use App\Enums\UnknownCategoryPolicy;
use Database\Factories\ImportFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded([])]
class Import extends DomainModel
{
    /** @use HasFactory<ImportFactory> */
    use HasFactory;

    protected $hidden = ['rejection_report'];

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /** @return HasMany<InventoryAdjustment, $this> */
    public function inventoryAdjustments(): HasMany
    {
        return $this->hasMany(InventoryAdjustment::class);
    }

    protected function casts(): array
    {
        return ['mode' => ImportMode::class, 'unknown_category_policy' => UnknownCategoryPolicy::class, 'stock_override' => 'boolean', 'stock_override_confirmed_at' => 'datetime'];
    }
}
