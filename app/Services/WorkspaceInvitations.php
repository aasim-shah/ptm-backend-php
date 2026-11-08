<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

/** Secure, single-use school invitations. Passwords are never emailed. */
class WorkspaceInvitations
{
    public const LIFETIME_HOURS = 72;

    public function __construct(private WorkspaceAccess $access) {}

    public function ensureDeliverable(): void
    {
        abort_unless(config('delivery.email_enabled'), 422, 'Configure and enable transactional email before sending invitations.');
    }

    public function send(User $actor, int $schoolId, string $email, string $role, array $permissions = []): int
    {
        $this->ensureDeliverable();
        $email = strtolower($email);
        $recipient = app()->environment('production') ? $email : config('delivery.test_email');
        abort_unless($recipient, 422, 'Configure a nonproduction test email recipient first.');
        $token = bin2hex(random_bytes(32));
        return DB::transaction(function () use ($actor, $schoolId, $email, $role, $permissions, $token, $recipient) {
            $id = DB::table('workspace_invitations')->insertGetId(['school_id' => $schoolId, 'invited_by' => $actor->id, 'email' => $email, 'role' => $role,
                'permissions' => json_encode($role === 'staff' ? $permissions : []), 'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addHours(self::LIFETIME_HOURS), 'created_at' => now(), 'updated_at' => now()]);
            $link = URL::temporarySignedRoute('invitation.show', now()->addHours(self::LIFETIME_HOURS), ['token' => $token]);
            $school = DB::table('schools')->where('id', $schoolId)->value('school_name');
            Notification::route('mail', $recipient)->notify(new \App\Notifications\SchoolInvitation($link, $school));
            $this->access->audit($actor, $schoolId, 'invitation.sent', $id);
            return $id;
        });
    }
}
