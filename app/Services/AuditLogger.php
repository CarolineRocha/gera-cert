<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    public function record(
        ?User $user,
        string $action,
        ?Model $subject = null,
        array $properties = [],
        ?Request $request = null
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $user?->id,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'properties' => $properties ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
