<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AdminAuditLogger
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        Request $request,
        string $action,
        Model $target,
        ?array $before,
        ?array $after,
        ?User $actor = null,
    ): void {
        $requestActor = $request->user();
        $actor ??= $requestActor instanceof User ? $requestActor : null;

        AuditLog::unguarded(fn (): AuditLog => AuditLog::create([
            'actor_user_id' => $actor?->getKey(),
            'action_code' => $action,
            'target_type' => $target->getMorphClass(),
            'target_id' => $target->getKey(),
            'before_data' => $this->redact($before),
            'after_data' => $this->redact($after),
            'request_context' => [
                'ip' => $request->ip(),
                'route' => $request->route()?->getName(),
            ],
        ]));
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @return array<string, mixed>|null
     */
    private function redact(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        $redacted = [];

        foreach ($data as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                continue;
            }

            $redacted[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $redacted;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);

        return str_contains($normalized, 'password')
            || str_contains($normalized, 'token')
            || str_contains($normalized, 'card')
            || str_contains($normalized, 'security_code')
            || str_contains($normalized, 'cvv')
            || $normalized === 'payment_test_number'
            || $normalized === 'image_path';
    }
}
