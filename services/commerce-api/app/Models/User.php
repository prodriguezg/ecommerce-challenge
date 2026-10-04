<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use LogicException;

#[Fillable(['role', 'name', 'email', 'password_hash'])]
#[Hidden(['password_hash', 'remember_token', 'normalized_email', 'admin_singleton'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUlids, Notifiable;

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            $user->email = Str::lower(trim($user->email));
            $user->normalized_email = $user->email;
        });
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function isAdministrator(): bool
    {
        return $this->getRawOriginal('role') === UserRole::Admin->value;
    }

    public function isCustomer(): bool
    {
        return $this->getRawOriginal('role') === UserRole::Customer->value;
    }

    public function roleValue(): string
    {
        return match (true) {
            $this->isAdministrator() => UserRole::Admin->value,
            $this->isCustomer() => UserRole::Customer->value,
            default => throw new LogicException('User role is invalid.'),
        };
    }

    /** @return HasMany<Address, $this> */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /** @return HasOne<Cart, $this> */
    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_user_id');
    }

    /** @return HasMany<Import, $this> */
    public function imports(): HasMany
    {
        return $this->hasMany(Import::class, 'actor_user_id');
    }

    /** @return HasMany<InventoryAdjustment, $this> */
    public function inventoryAdjustments(): HasMany
    {
        return $this->hasMany(InventoryAdjustment::class, 'actor_user_id');
    }

    /** @return HasMany<AuditLog, $this> */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_user_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'role' => UserRole::class,
            'password_hash' => 'hashed',
        ];
    }
}
