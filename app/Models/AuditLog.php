<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only audit trail: role requests, approvals, context switches, permission changes.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['actor_user_id', 'action', 'subject_type', 'subject_id', 'context_id', 'payload'];

    protected $casts = ['payload' => 'array'];

    public static function record(?int $actorId, string $action, $subject = null, ?int $contextId = null, array $payload = []): self
    {
        return self::create([
            'actor_user_id' => $actorId,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject ? (string) $subject->getKey() : null,
            'context_id' => $contextId,
            'payload' => $payload ?: null,
        ]);
    }
}
