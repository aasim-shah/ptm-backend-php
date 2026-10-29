<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class WorkspaceDashboard
{
    public function __construct(private WorkspaceAccess $access) {}

    public function updates(User $user, array $context)
    {
        $q = DB::table('workspace_updates as u')->join('students as s', 's.id', '=', 'u.student_id')
            ->join('users as student', 'student.id', '=', 's.user_id')->whereNull('student.deleted_at')->whereNull('s.deleted_at')
            ->whereIn('u.school_id', array_keys($context['schools']))
            ->whereIn('s.id', $this->access->students($user, $context)->select('students.id'));
        if ($context['role'] === 'parent') { $q->where('u.status', 'published'); }
        if ($context['role'] === 'staff') { $q->whereRaw('1 = 0'); }
        return $q->select('u.*', 'student.first_name', 'student.last_name')->orderByDesc('u.updated_at');
    }

    public function posts(array $context, string $kind)
    {
        return DB::table('workspace_posts')->whereIn('school_id', array_keys($context['schools']))->where('kind', $kind)
            ->whereIn('audience', ['all', $context['role']])->orderByDesc('created_at');
    }

    public function messages(User $user, array $context)
    {
        $contacts = $this->access->contacts($user, $context)->pluck('id');
        return DB::table('workspace_messages as m')->join('users as sender', 'sender.id', '=', 'm.sender_id')
            ->join('users as recipient', 'recipient.id', '=', 'm.recipient_id')->whereIn('m.school_id', array_keys($context['schools']))
            ->where(function ($q) use ($user, $contacts) {
                $q->where(function ($q) use ($user, $contacts) { $q->where('m.sender_id', $user->id)->whereIn('m.recipient_id', $contacts); })
                    ->orWhere(function ($q) use ($user, $contacts) { $q->where('m.recipient_id', $user->id)->whereIn('m.sender_id', $contacts); });
            })->select('m.*', 'sender.first_name as sender_name', 'recipient.first_name as recipient_name')->orderByDesc('m.id');
    }

    public function appointments(User $user, array $context)
    {
        if (in_array($context['role'], ['super_admin', 'school_admin', 'district_admin']) ||
            ($context['role'] === 'sub_super_admin' && $this->access->can($context, 'events.manage'))) {
            return DB::table('workspace_appointments')->whereIn('school_id', array_keys($context['schools']))->orderBy('starts_at');
        }
        $contacts = $this->access->contacts($user, $context)->pluck('id');
        return DB::table('workspace_appointments')->whereIn('school_id', array_keys($context['schools']))
            ->where(function ($q) use ($user, $contacts) {
                $q->where(function ($q) use ($user, $contacts) { $q->where('organizer_id', $user->id)->whereIn('participant_id', $contacts); })
                    ->orWhere(function ($q) use ($user, $contacts) { $q->where('participant_id', $user->id)->whereIn('organizer_id', $contacts); });
            })->orderBy('starts_at');
    }

    public function data(User $user, array $context): array
    {
        $students = $this->access->students($user, $context);
        $updates = $this->updates($user, $context);
        $messages = $this->messages($user, $context);
        $appointments = $this->appointments($user, $context);
        $stats = [
            ['label' => $context['role'] === 'parent' ? 'Connected students' : 'Students in scope', 'value' => (clone $students)->count()],
            ['label' => 'Unread messages', 'value' => (clone $messages)->where('m.recipient_id', $user->id)->whereNull('m.read_at')->count()],
            ['label' => 'Upcoming meetings', 'value' => (clone $appointments)->where('starts_at', '>=', now())->whereIn('status', ['pending', 'accepted'])->count()],
        ];
        if ($context['role'] === 'teacher') {
            $stats[] = ['label' => 'Draft student updates', 'value' => (clone $updates)->where('u.status', 'draft')->count()];
        } elseif (in_array($context['role'], ['school_admin', 'district_admin', 'super_admin', 'sub_super_admin'])) {
            $stats[] = ['label' => 'Schools', 'value' => count($context['schools'])];
        } else {
            $stats[] = ['label' => 'School notices', 'value' => $this->posts($context, 'notice')->count()];
        }
        if (in_array($context['role'], ['super_admin', 'sub_super_admin', 'school_admin', 'district_admin'])) {
            foreach (['parent' => 'Parents', 'teacher' => 'Teachers', 'staff' => 'Staff'] as $role => $label) {
                $stats[] = ['label' => 'Connected '.$label, 'value' => DB::table('workspace_memberships as m')->join('users as member', 'member.id', '=', 'm.user_id')
                    ->whereIn('m.school_id', array_keys($context['schools']))->where('m.role', $role)->where('m.status', 'active')
                    ->where('member.status', 1)->whereNull('member.deleted_at')->whereNotNull('member.email_verified_at')
                    ->where(fn ($q) => $q->whereNull('m.starts_at')->orWhere('m.starts_at', '<=', now()))
                    ->where(fn ($q) => $q->whereNull('m.ends_at')->orWhere('m.ends_at', '>', now()))->distinct()->count('m.user_id')];
            }
        }
        $capabilities = [];
        foreach (['students.view', 'updates.write', 'notices.publish', 'events.manage', 'memberships.manage', 'academics.manage'] as $permission) {
            $capabilities[$permission] = $this->access->can($context, $permission);
        }
        return ['context' => $context, 'capabilities' => $capabilities, 'summary' => $stats, 'students' => (clone $students)->select('students.id', 'students.user_id', 'students.class_section_id', 'students.class_status')->with('user:id,first_name,last_name', 'class_section.class', 'class_section.section')->limit(8)->get(),
            'updates' => $updates->limit(5)->get(), 'notices' => $this->posts($context, 'notice')->limit(4)->get(),
            'events' => $this->posts($context, 'event')->where('starts_at', '>=', now())->reorder('starts_at')->limit(4)->get(),
            'appointments' => $appointments->where('starts_at', '>=', now())->whereIn('status', ['pending', 'accepted'])->limit(5)->get(),
            'as_of' => now()->toIso8601String()];
    }
}
