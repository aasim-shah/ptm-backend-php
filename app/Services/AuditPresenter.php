<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Turns raw audit rows ("membership.revoked", subject "40") into sentences people can read. */
class AuditPresenter
{
    public const LABELS = [
        'auth.login' => 'Signed in', 'auth.failed' => 'Failed sign-in attempt', 'auth.logout' => 'Signed out',
        'security.review-enrollment.skipped' => 'Skipped two-step sign-in setup', 'security.mfa.enabled' => 'Turned on two-step sign-in',
        'membership.connected' => 'Connected an account', 'membership.revoked' => 'Revoked access', 'membership.restored' => 'Restored access',
        'invitation.sent' => 'Sent an invitation', 'invitation.accepted' => 'Accepted an invitation', 'invitation.revoked' => 'Withdrew an invitation',
        'guardian.requested' => 'Requested a guardian link', 'guardian.verified' => 'Verified a guardian', 'guardian.rejected' => 'Rejected a guardian request', 'guardian.revoked' => 'Revoked a guardian link',
        'school.created' => 'Created a school', 'school.profile.updated' => 'Updated a school profile', 'school.suspended' => 'Suspended a school', 'school.reactivated' => 'Reactivated a school',
        'school.admin.assigned' => 'Assigned a School Admin', 'school_admin.revoked' => 'Removed a School Admin', 'district_admin.revoked' => 'Removed a District Admin',
        'district.created' => 'Created a district', 'district.updated' => 'Updated a district', 'district.suspended' => 'Suspended a district', 'district.reactivated' => 'Reactivated a district',
        'district.admin.assigned' => 'Assigned a District Admin', 'district.school.added' => 'Added a school to a district', 'district.school.removed' => 'Removed a school from a district',
        'delegation.saved' => 'Granted Sub-Super Admin access', 'delegation.updated' => 'Changed Sub-Super Admin actions', 'delegation.revoked' => 'Revoked Sub-Super Admin access',
        'account.suspended' => 'Suspended an account', 'account.reactivated' => 'Reactivated an account', 'account.password-reset.sent' => 'Sent a password reset link',
        'post.created' => 'Published', 'post.scheduled' => 'Scheduled', 'update.published' => 'Published a student update', 'update.draft' => 'Saved a student update draft',
        'class.assignment.saved' => 'Saved a class section', 'appointment.requested' => 'Requested a meeting', 'appointment.accepted' => 'Accepted a meeting',
        'appointment.declined' => 'Declined a meeting', 'appointment.cancelled' => 'Cancelled a meeting',
        'ad_free.requested' => 'Requested ad-free status', 'ad_free.approved' => 'Approved an ad-free request', 'ad_free.rejected' => 'Rejected an ad-free request',
        'report.downloaded' => 'Downloaded a report', 'settings.updated' => 'Changed platform settings', 'market.updated' => 'Changed market settings',
        'ads.updated' => 'Changed ad settings', 'plan.updated' => 'Changed a subscription plan', 'minutes.adjusted' => 'Adjusted video minutes',
        'import.completed' => 'Completed a bulk import', 'student.imported' => 'Added a student', 'subscription.cancelled' => 'Cancelled a subscription', 'payment.refunded' => 'Refunded a payment', 'support.ticket.created' => 'Opened a support ticket',
    ];

    /** Actions whose subject starts with a user id ("39" or "39:parent"). */
    private const USER_SUBJECTS = ['membership.connected', 'school.admin.assigned', 'school_admin.revoked', 'district_admin.revoked', 'district.admin.assigned',
        'delegation.saved', 'delegation.updated', 'delegation.revoked', 'account.suspended', 'account.reactivated', 'account.password-reset.sent',
        'security.review-enrollment.skipped', 'security.mfa.enabled', 'auth.login', 'auth.logout', 'minutes.adjusted'];

    public function label(string $action): string
    {
        if (isset(self::LABELS[$action])) { return self::LABELS[$action]; }
        if (preg_match('/^report\.([a-z_]+)\.exported$/', $action, $m)) { return 'Exported '.str_replace('_', ' ', $m[1]).' report'; }
        return ucfirst(str_replace(['.', '_', '-'], ' ', $action));
    }

    /** Adds actor_name, action_label and subject_label to each entry, with batched lookups. */
    public function present(Collection $entries): Collection
    {
        $userIds = $entries->pluck('actor_id');
        $memberships = []; $schools = []; $districts = []; $invitations = []; $posts = [];
        foreach ($entries as $e) {
            $head = explode(':', (string) $e->subject)[0];
            if (in_array($e->action, self::USER_SUBJECTS, true) && ctype_digit($head)) { $userIds->push((int) $head); }
            elseif (in_array($e->action, ['membership.revoked', 'membership.restored'], true) && ctype_digit($head)) { $memberships[] = (int) $head; }
            elseif (str_starts_with($e->action, 'school.') && $e->action !== 'school.admin.assigned' && ctype_digit($head)) { $schools[] = (int) $head; }
            elseif (str_starts_with($e->action, 'district.') && ctype_digit($head)) { $districts[] = (int) $head; }
            elseif (str_starts_with($e->action, 'invitation.') && ctype_digit($head)) { $invitations[] = (int) $head; }
            elseif (in_array($e->action, ['post.created', 'post.scheduled'], true) && ctype_digit($head)) { $posts[] = (int) $head; }
        }
        $membershipRows = DB::table('workspace_memberships')->whereIn('id', $memberships)->get(['id', 'user_id', 'role'])->keyBy('id');
        $userIds = $userIds->merge($membershipRows->pluck('user_id'))->filter()->unique();
        $users = DB::table('users')->whereIn('id', $userIds)->get(['id', 'first_name', 'last_name', 'email'])->keyBy('id');
        $schoolNames = DB::table('schools')->whereIn('id', $schools)->pluck('school_name', 'id');
        $districtNames = DB::table('workspace_districts')->whereIn('id', $districts)->pluck('name', 'id');
        $inviteEmails = DB::table('workspace_invitations')->whereIn('id', $invitations)->pluck('email', 'id');
        $postTitles = DB::table('workspace_posts')->whereIn('id', $posts)->pluck('title', 'id');
        $name = fn ($id) => ($u = $users[$id] ?? null) ? (trim($u->first_name.' '.$u->last_name) ?: $u->email) : 'Account #'.$id;

        return $entries->map(function ($e) use ($name, $membershipRows, $schoolNames, $districtNames, $inviteEmails, $postTitles) {
            $parts = explode(':', (string) $e->subject);
            $head = $parts[0];
            $subject = match (true) {
                in_array($e->action, self::USER_SUBJECTS, true) && ctype_digit($head) => $name((int) $head).(isset($parts[1]) && !ctype_digit($parts[1]) ? ' · '.(WorkspaceAccess::LABELS[$parts[1]] ?? implode(':', array_slice($parts, 1))) : ''),
                isset($membershipRows[(int) $head]) => $name($membershipRows[(int) $head]->user_id).' · '.(WorkspaceAccess::LABELS[$membershipRows[(int) $head]->role] ?? ''),
                str_starts_with($e->action, 'school.') && isset($schoolNames[(int) $head]) => $schoolNames[(int) $head].(isset($parts[1]) ? ' · '.$parts[1] : ''),
                str_starts_with($e->action, 'district.') && isset($districtNames[(int) $head]) => $districtNames[(int) $head],
                str_starts_with($e->action, 'invitation.') && isset($inviteEmails[(int) $head]) => $inviteEmails[(int) $head],
                isset($postTitles[(int) $head]) && str_starts_with($e->action, 'post.') => '“'.$postTitles[(int) $head].'”',
                str_starts_with($e->action, 'report.') => strtoupper($parts[1] ?? ''),
                $e->action === 'auth.failed' => $e->subject,
                default => ($e->subject !== '' ? 'Record #'.$e->subject : ''),
            };
            $e->actor_name = $name((int) $e->actor_id);
            $e->action_label = $this->label($e->action);
            $e->subject_label = $subject;
            return $e;
        });
    }
}
