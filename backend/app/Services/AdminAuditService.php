<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AdminAuditService
{
    public function log(User $actor, string $action, ?string $targetType = null, ?int $targetId = null, array $meta = [], ?Request $request = null): void
    {
        AdminAuditLog::query()->create([
            'actor_id' => $actor->id,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'meta' => $meta === [] ? null : $meta,
            'ip_address' => $request?->ip(),
            'created_at' => now(),
        ]);
    }
}
