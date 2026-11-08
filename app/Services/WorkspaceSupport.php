<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Support tickets. Platform staff (Super Admin, or a Sub-Super Admin holding support.manage) handle tickets
 * in their scope; School and District Admins see their institutions' tickets; everyone else sees only their own.
 */
class WorkspaceSupport
{
    public const CATEGORIES = ['account' => 'Account & sign in', 'billing' => 'Subscription & billing', 'video' => 'Video calls',
        'technical' => 'Technical problem', 'data_privacy' => 'Data & privacy request', 'other' => 'Something else'];
    public const PRIORITIES = ['normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'];
    public const STATUSES = ['open' => 'Open', 'in_progress' => 'In progress', 'resolved' => 'Resolved', 'closed' => 'Closed'];

    public function __construct(private WorkspaceAccess $access) {}

    public function handles(?array $context): bool
    {
        return $context && ($context['role'] === 'super_admin' || ($context['role'] === 'sub_super_admin' && $this->access->can($context, 'support.manage')));
    }

    /** Tickets visible to the user. A null context means the mobile API: own tickets only. */
    public function visible(User $user, ?array $context)
    {
        $q = DB::table('workspace_support_tickets as t')->join('users as r', 'r.id', '=', 't.requester_id')->leftJoin('schools as s', 's.id', '=', 't.school_id')
            ->select('t.*', 'r.first_name', 'r.last_name', 'r.email', 's.school_name');
        if ($context && WorkspaceAccess::platformWide($context)) { return $q; }
        $schools = $context ? array_keys($context['schools']) : [];
        $institution = $context && ($this->handles($context) || in_array($context['role'], ['school_admin', 'district_admin'], true));
        return $q->where(function ($q) use ($user, $schools, $institution) {
            $q->where('t.requester_id', $user->id);
            if ($institution && $schools) { $q->orWhereIn('t.school_id', $schools); }
        });
    }

    /** Schools a mobile user may attach to a ticket: those with an active membership. */
    public function memberSchools(User $user): array
    {
        return $this->access->memberships($user)->whereNotNull('school_id')->pluck('school_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    public function create(User $user, array $data, ?int $schoolId): int
    {
        return DB::transaction(function () use ($user, $data, $schoolId) {
            $id = DB::table('workspace_support_tickets')->insertGetId(['requester_id' => $user->id, 'school_id' => $schoolId, 'subject' => $data['subject'],
                'category' => $data['category'], 'priority' => $data['priority'] ?? 'normal', 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('workspace_support_replies')->insert(['ticket_id' => $id, 'author_id' => $user->id, 'body' => $data['body'], 'internal' => false, 'created_at' => now()]);
            $this->access->audit($user, $schoolId, 'support.ticket.created', $id);
            return $id;
        });
    }

    public function reply(User $user, object $ticket, string $body, bool $internal, bool $handler): void
    {
        abort_if($ticket->status === 'closed', 422, 'This ticket is closed. Open a new ticket if you still need help.');
        abort_if($internal && !$handler, 403);
        DB::transaction(function () use ($user, $ticket, $body, $internal, $handler) {
            DB::table('workspace_support_replies')->insert(['ticket_id' => $ticket->id, 'author_id' => $user->id, 'body' => $body, 'internal' => $internal, 'created_at' => now()]);
            // A requester replying to a resolved ticket reopens it; a handler's public reply moves it into progress.
            $status = !$handler && $ticket->status === 'resolved' ? 'open' : ($handler && !$internal && $ticket->status === 'open' ? 'in_progress' : $ticket->status);
            DB::table('workspace_support_tickets')->where('id', $ticket->id)->update(['status' => $status, 'resolved_at' => $status === 'resolved' ? $ticket->resolved_at : null, 'updated_at' => now()]);
            $this->access->audit($user, $ticket->school_id, $internal ? 'support.ticket.noted' : 'support.ticket.replied', $ticket->id);
        });
    }

    public function replies(object $ticket, bool $handler)
    {
        return DB::table('workspace_support_replies as r')->join('users as u', 'u.id', '=', 'r.author_id')->where('r.ticket_id', $ticket->id)
            ->when(!$handler, fn ($q) => $q->where('r.internal', false))->orderBy('r.id')
            ->select('r.id', 'r.body', 'r.internal', 'r.created_at', 'r.author_id', 'u.first_name', 'u.last_name')->get();
    }
}
