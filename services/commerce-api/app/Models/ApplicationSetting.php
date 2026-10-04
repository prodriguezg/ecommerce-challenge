<?php

namespace App\Models;

use App\Enums\SettingType;
use Database\Factories\ApplicationSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Guarded([])]
class ApplicationSetting extends DomainModel
{
    /** @use HasFactory<ApplicationSettingFactory> */
    use HasFactory;

    /** @return MorphMany<AuditLog, $this> */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'target');
    }

    protected function casts(): array
    {
        return ['type' => SettingType::class];
    }
}
