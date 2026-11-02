<?php

namespace App\Services;

use App\Models\{AuditEvent, User};
use Illuminate\Support\Facades\Request;

class AuditService
{
    public function log(
        ?User   $actor,
        string  $action,
        string  $targetType,
        string  $targetId,
        ?array  $details = null,
    ): AuditEvent {
        return AuditEvent::create([
            'actor_id'    => $actor?->id,
            'action'      => $action,
            'target_type' => $targetType,
            'target_id'   => $targetId,
            'details'     => $details,
            'ip_address'  => Request::ip(),
        ]);
    }
}