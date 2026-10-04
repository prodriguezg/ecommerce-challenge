<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\DomainModel;
use App\Models\User;
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
        DomainModel $target,
        ?array $before,
        ?array $after,
    ): void {
        $actor = $request->user();

        AuditLog::unguarded(fn (): AuditLog => AuditLog::create([
            'actor_user_id' => $actor instanceof User ? $actor->getKey() : null,
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

        return collect($data)
            ->except(['password', 'password_hash', 'remember_token', 'image_path'])
            ->all();
    }
}
