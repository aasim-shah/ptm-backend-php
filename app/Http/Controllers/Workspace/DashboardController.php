<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\WorkspaceAccess;
use App\Services\WorkspaceDashboard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    public function __construct(private WorkspaceAccess $access, private WorkspaceDashboard $dashboard) {}

    private function context(Request $request): array
    {
        abort_unless((int) $request->user()->status && $request->user()->email_verified_at, 403, 'An active, verified account is required.');
        $id = $request->input('context');
        if ($id === null && $request->hasSession()) { $id = $request->session()->get('workspace_context'); }
        abort_if($id !== null && !is_string($id), 422, 'Invalid workspace context.');
        return $this->access->resolve($request->user(), $id);
    }

    private function timezone(User $user): string
    {
        return DB::table('workspace_profiles')->where('user_id', $user->id)->value('timezone') ?? 'UTC';
    }

    public function workspaces(Request $request)
    {
        return response()->json(['contexts' => $this->access->contexts($request->user())]);
    }

    public function select(Request $request)
    {
        $request->validate(['context' => 'required|string|max:80']);
        $context = $this->context($request);
        $request->session()->put('workspace_context', $context['id']);
        return redirect()->route('workspace.home');
    }

    public function index(Request $request, string $page = 'home')
    {
        if ($page === 'files') { return app(FilesController::class)->index($request); }
        if (!$request->input('context') && $request->user()->hasRole('Super Admin') && !$this->access->contexts($request->user())) {
            return $request->expectsJson() ? response()->json(['contexts' => [], 'platform_dashboard' => route('home')]) : redirect()->route('home');
        }
        $context = $this->context($request);
        $timezone = $this->timezone($request->user());
        abort_unless(in_array($page, ['home', 'classes', 'students', 'updates', 'messages', 'schedule', 'events', 'notices', 'people', 'profile']), 404);
        if ($page === 'people') { abort_unless($this->access->can($context, 'memberships.manage'), 403); }
        if ($page === 'updates') { abort_if($context['role'] === 'staff', 403); }
        if (in_array($page, ['students', 'updates'])) {
            abort_unless($context['role'] === 'parent' || $this->access->can($context, 'students.view'), 403);
        }
        if ($page === 'classes') { abort_unless(in_array($context['role'], ['teacher', 'school_admin', 'district_admin', 'super_admin', 'sub_super_admin']), 403); }
        if ($page === 'classes' && $context['role'] === 'sub_super_admin') { abort_unless($this->access->can($context, 'academics.manage'), 403); }
        $data = $this->dashboard->data($request->user(), $context);
        $data['timezone'] = $timezone;
        if ($request->expectsJson()) { return response()->json($data); }
        $items = null;
        if ($page === 'classes') { $items = $this->access->sections($request->user(), $context)->with('class', 'section')->withCount('students')->paginate(20)->withQueryString(); }
        if ($page === 'students') { $items = $this->access->students($request->user(), $context)->with('user', 'class_section.class', 'class_section.section')->paginate(20)->withQueryString(); }
        if ($page === 'updates') { $items = $this->dashboard->updates($request->user(), $context)->paginate(20)->withQueryString(); }
        if ($page === 'messages') { $items = $this->dashboard->messages($request->user(), $context)->paginate(20)->withQueryString(); }
        if ($page === 'schedule') { $items = $this->dashboard->appointments($request->user(), $context)->paginate(20)->withQueryString(); }
        if (in_array($page, ['events', 'notices'])) { $items = $this->dashboard->posts($context, $page === 'events' ? 'event' : 'notice')->paginate(20)->withQueryString(); }
        $members = $guardians = collect();
        if ($page === 'people') {
            $members = DB::table('workspace_memberships as m')->join('users as u', 'u.id', '=', 'm.user_id')
                ->whereIn('m.school_id', array_keys($context['schools']))->whereNull('u.deleted_at')
                ->select('m.*', 'u.first_name', 'u.last_name', 'u.email')->orderByDesc('m.id')->paginate(20)->withQueryString();
            $guardians = DB::table('workspace_guardians as g')->join('users as u', 'u.id', '=', 'g.guardian_id')
                ->whereIn('g.school_id', array_keys($context['schools']))->select('g.*', 'u.first_name', 'u.last_name')->orderByDesc('g.id')->limit(50)->get();
        }
        return view('workspace.index', $data + ['timezone' => $timezone, 'formatTime' => fn($date, $format = 'j M Y · H:i') => \Carbon\Carbon::parse($date, 'UTC')->timezone($timezone)->format($format), 'page' => $page, 'items' => $items, 'members' => $members, 'guardians' => $guardians,
            'contexts' => $this->access->webContexts($request->user()), 'access' => $this->access,
            'classTeachers' => $page === 'classes' ? DB::table('workspace_memberships as m')->join('users as u','u.id','=','m.user_id')->whereIn('m.school_id',array_keys($context['schools']))->where('m.role','teacher')->where('m.status','active')->where('u.status',1)->whereNull('u.deleted_at')->whereNotNull('u.email_verified_at')->where(function ($q) { $q->whereNull('m.starts_at')->orWhere('m.starts_at','<=',now()); })->where(function ($q) { $q->whereNull('m.ends_at')->orWhere('m.ends_at','>',now()); })->select('u.id','u.first_name','u.last_name','m.school_id')->get() : collect(),
            'updateStudents' => $page === 'updates' ? $this->access->students($request->user(), $context)->when($request->student_id, function ($q) use ($request) { $q->where('students.id', $request->student_id); })->with('user:id,first_name,last_name')->limit(200)->get() : collect(),
            'contacts' => in_array($page, ['messages', 'schedule']) ? $this->access->contacts($request->user(), $context)->limit(200)->get() : collect()]);
    }

    private function schoolContext(Request $request): array
    {
        $context = $this->context($request);
        $request->validate(['school_id' => ['required', 'integer', Rule::in(array_keys($context['schools']))]]);
        $id = (int) $request->school_id;
        $context['schools'] = [$id => $context['schools'][$id]];
        return $context;
    }

    private function done(Request $request, string $message)
    {
        return $request->expectsJson() ? response()->json(['message' => $message]) : back()->with('workspace_success', $message);
    }

    public function classSection(Request $request)
    {
        $context = $this->schoolContext($request);
        abort_unless($this->access->can($context, 'academics.manage'), 403);
        $data = $request->validate(['name' => 'required|string|max:120', 'section_name' => 'required|string|max:120', 'teacher_id' => 'required|integer']);
        $teacher = User::where('id', $data['teacher_id'])->where('status', 1)->whereNotNull('email_verified_at')->first();
        abort_unless($teacher && $this->access->memberships($teacher)->where('school_id', $request->school_id)->where('role', 'teacher')->exists(), 422, 'Choose an active teacher membership in this school.');
        DB::transaction(function () use ($request, $data) {
            DB::table('schools')->where('id', $request->school_id)->lockForUpdate()->first();
            $class = \App\Models\ClassSchool::firstOrCreate(['school_id' => $request->school_id, 'name' => $data['name']]);
            $section = \App\Models\Section::firstOrCreate(['school_id' => $request->school_id, 'name' => $data['section_name']]);
            $cs = \App\Models\ClassSection::updateOrCreate(['class_id' => $class->id, 'section_id' => $section->id], ['class_teacher_id' => $data['teacher_id']]);
            $this->access->audit($request->user(), $request->school_id, 'class.assignment.saved', $cs->id);
        });
        return $this->done($request, 'Class section and teacher assignment saved.');
    }

    public function post(Request $request)
    {
        $context = $this->schoolContext($request);
        $data = $request->validate(['kind' => 'required|in:notice,event', 'title' => 'required|string|max:160',
            'body' => 'required|string|max:5000', 'audience' => ['required', Rule::in(['all', 'parent', 'teacher', 'staff', 'school_admin', 'district_admin'])],
            'starts_at' => 'nullable|required_if:kind,event|date']);
        abort_unless($this->access->can($context, $data['kind'] === 'event' ? 'events.manage' : 'notices.publish'), 403);
        DB::transaction(function () use ($request, $data) {
            if (!empty($data['starts_at'])) { $data['starts_at'] = \Carbon\Carbon::parse($data['starts_at'], $this->timezone($request->user()))->utc(); abort_if($data['starts_at']->isPast(), 422, 'The event must start in the future.'); }
            $id = DB::table('workspace_posts')->insertGetId($data + ['school_id' => $request->school_id, 'author_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            $this->access->audit($request->user(), $request->school_id, 'post.created', $id);
            $notifications = app(\App\Services\WorkspaceNotifications::class);
            $notifications->record((int)$request->school_id, $data['kind'], $id, 'post:'.$id, $notifications->audience((int)$request->school_id, $data['audience']));
        });
        return $this->done($request, 'Published to the selected school and audience.');
    }

    public function update(Request $request)
    {
        $context = $this->schoolContext($request);
        abort_unless($this->access->can($context, 'updates.write'), 403);
        $data = $request->validate(['student_id' => 'required|integer', 'title' => 'required|string|max:160',
            'body' => 'required|string|max:5000', 'status' => 'required|in:draft,published', 'update_id' => 'nullable|integer', 'version' => 'required_with:update_id|integer|min:1']);
        abort_unless($this->access->students($request->user(), $context)->where('students.id', $data['student_id'])->exists(), 403);
        DB::transaction(function () use ($request, $data) {
            $values = ['title' => $data['title'], 'body' => $data['body'], 'status' => $data['status'],
                'updated_at' => now(), 'published_at' => $data['status'] === 'published' ? now() : null];
            if (!empty($data['update_id'])) {
                $old = DB::table('workspace_updates')->where('school_id', $request->school_id)->where('student_id', $data['student_id'])->lockForUpdate()->find($data['update_id']);
                abort_unless($old, 404);
                abort_unless((int) $old->author_id === (int) $request->user()->id, 403, 'Only the author may correct this update.');
                abort_unless((int) $old->version === (int) $data['version'], 409, 'This update changed. Reload before saving.');
                abort_if($old->status === 'published' && $data['status'] !== 'published', 422, 'Published updates must remain published; corrections preserve history.');
                $version = $old->version + 1; $id = $old->id;
                DB::table('workspace_updates')->where('id', $id)->update($values + ['version' => $version]);
            } else {
                $version = 1;
                $id = DB::table('workspace_updates')->insertGetId($values + ['school_id' => $request->school_id, 'student_id' => $data['student_id'],
                    'author_id' => $request->user()->id, 'version' => 1, 'created_at' => now()]);
            }
            DB::table('workspace_update_versions')->insert(['update_id' => $id, 'actor_id' => $request->user()->id,
                'version' => $version, 'title' => $data['title'], 'body' => $data['body'], 'status' => $data['status'], 'created_at' => now()]);
            $this->access->audit($request->user(), $request->school_id, 'update.'.$data['status'], $id);
            if ($data['status'] === 'published') {
                $recipients = DB::table('workspace_guardians')->where('student_id',$data['student_id'])->where('school_id',$request->school_id)->where('status','verified')
                    ->where(function ($q) { $q->whereNull('ends_at')->orWhere('ends_at','>',now()); })->pluck('guardian_id')->all();
                app(\App\Services\WorkspaceNotifications::class)->record((int)$request->school_id, 'update', $id, 'update:'.$id.':'.$version, $recipients);
            }
        });
        return $this->done($request, $data['status'] === 'published' ? 'Student update published.' : 'Draft saved. Parents cannot see drafts.');
    }

    public function message(Request $request)
    {
        $context = $this->schoolContext($request);
        $data = $request->validate(['recipient_id' => 'required|integer', 'body' => 'required|string|max:5000']);
        abort_unless($this->access->contacts($request->user(), $context)->where('id', $data['recipient_id'])->exists(), 403);
        DB::transaction(function () use ($request, $data) {
            $id = DB::table('workspace_messages')->insertGetId($data + ['school_id' => $request->school_id, 'sender_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            app(\App\Services\WorkspaceNotifications::class)->record((int)$request->school_id, 'message', $id, 'message:'.$id, [(int)$data['recipient_id']]);
        });
        return $this->done($request, 'Message sent.');
    }

    public function read(Request $request, int $id)
    {
        $context = $this->context($request);
        $message = $this->dashboard->messages($request->user(), $context)->where('m.id', $id)->where('m.recipient_id', $request->user()->id)->first();
        abort_unless($message, 404);
        DB::table('workspace_messages')->where('id', $id)->whereNull('read_at')->update(['read_at' => now(), 'updated_at' => now()]);
        return $this->done($request, 'Message marked read.');
    }

    public function appointment(Request $request)
    {
        $context = $this->schoolContext($request);
        $data = $request->validate(['participant_id' => 'required|integer', 'title' => 'required|string|max:160',
            'starts_at' => 'required|date', 'ends_at' => 'required|date|after:starts_at']);
        abort_unless($this->access->contacts($request->user(), $context)->where('id', $data['participant_id'])->exists(), 403);
        $data['starts_at'] = \Carbon\Carbon::parse($data['starts_at'], $this->timezone($request->user()))->utc();
        $data['ends_at'] = \Carbon\Carbon::parse($data['ends_at'], $this->timezone($request->user()))->utc();
        abort_if($data['starts_at']->isPast() || $data['ends_at']->lte($data['starts_at']), 422, 'Choose a future start and a later end time.');
        abort_if($data['ends_at']->diffInMinutes($data['starts_at'], true) > 240, 422, 'Meetings may be up to four hours.');
        DB::transaction(function () use ($request, $data) {
            // Lock both accounts in a stable order so simultaneous bookings serialize.
            User::whereIn('id', [$request->user()->id, $data['participant_id']])->orderBy('id')->lockForUpdate()->get();
            $this->assertNoConflict([$request->user()->id, $data['participant_id']], $data['starts_at'], $data['ends_at']);
            $id = DB::table('workspace_appointments')->insertGetId($data + ['school_id' => $request->school_id,
                'organizer_id' => $request->user()->id, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
            $this->access->audit($request->user(), $request->school_id, 'appointment.requested', $id);
            app(\App\Services\WorkspaceNotifications::class)->record((int)$request->school_id, 'appointment', $id, 'appointment:'.$id.':pending', [(int)$data['participant_id']]);
        });
        return $this->done($request, 'Meeting requested. The recipient can accept or decline.');
    }

    private function assertNoConflict(array $ids, $start, $end, ?int $except = null): void
    {
        $q = DB::table('workspace_appointments')->whereIn('status', ['pending', 'accepted'])
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start)
            ->where(function ($q) use ($ids) { $q->whereIn('organizer_id', $ids)->orWhereIn('participant_id', $ids); });
        if ($except) { $q->where('id', '!=', $except); }
        abort_if($q->exists(), 409, 'A participant already has a meeting during this time.');
    }

    public function appointmentStatus(Request $request, int $id)
    {
        $context = $this->context($request);
        $request->validate(['status' => 'required|in:accepted,declined,cancelled']);
        DB::transaction(function () use ($request, $context, $id) {
            $visible = $this->dashboard->appointments($request->user(), $context)->where('id', $id)->first();
            abort_unless($visible, 404);
            User::whereIn('id', [$visible->organizer_id, $visible->participant_id])->orderBy('id')->lockForUpdate()->get();
            $a = DB::table('workspace_appointments')->lockForUpdate()->find($id);
            abort_unless($a->starts_at > now()->toDateTimeString(), 422, 'This appointment has already started.');
            if ($a->status === $request->status) { return; }
            abort_unless(in_array($a->status, ['pending', 'accepted']), 409, 'This appointment is already closed.');
            if ($request->status !== 'cancelled') {
                abort_unless((int) $a->participant_id === (int) $request->user()->id && $a->status === 'pending', 403);
            }
            if ($request->status === 'accepted') { $this->assertNoConflict([$a->organizer_id, $a->participant_id], $a->starts_at, $a->ends_at, $id); }
            DB::table('workspace_appointments')->where('id', $id)->update(['status' => $request->status, 'updated_at' => now()]);
            $this->access->audit($request->user(), $a->school_id, 'appointment.'.$request->status, $id);
            app(\App\Services\WorkspaceNotifications::class)->record((int)$a->school_id, 'appointment', $id, 'appointment:'.$id.':'.$request->status, [(int)$a->organizer_id, (int)$a->participant_id]);
        });
        return $this->done($request, 'Meeting '.$request->status.'.');
    }

    public function membership(Request $request)
    {
        $context = $this->schoolContext($request);
        abort_unless($this->access->can($context, 'memberships.manage'), 403);
        $data = $request->validate(['email' => 'required|email', 'role' => 'required|in:parent,teacher,staff',
            'permissions' => 'nullable|array', 'permissions.*' => [Rule::in(WorkspaceAccess::STAFF_PERMISSIONS)]]);
        abort_unless($this->access->mayDelegate($context, $data['permissions'] ?? []), 403, 'Cannot delegate permissions beyond your own grant.');
        $user = User::where('email', $data['email'])->where('status', 1)->whereNotNull('email_verified_at')->first();
        abort_unless($user, 422, 'An active, verified PTM account is required.');
        DB::transaction(function () use ($request, $data, $user) {
            DB::table('workspace_memberships')->updateOrInsert(['user_id' => $user->id, 'school_id' => $request->school_id, 'role' => $data['role']],
                ['status' => 'active', 'approved_by' => $request->user()->id, 'starts_at' => now(), 'ends_at' => null,
                    'permissions' => json_encode($data['role'] === 'staff' ? ($data['permissions'] ?? []) : []), 'created_at' => now(), 'updated_at' => now()]);
            $this->access->audit($request->user(), $request->school_id, 'membership.connected', $user->id.':'.$data['role']);
        });
        return $this->done($request, 'School membership connected. Teaching assignments are managed separately.');
    }

    public function revoke(Request $request, int $id)
    {
        $context = $this->context($request);
        abort_unless($this->access->can($context, 'memberships.manage'), 403);
        DB::transaction(function () use ($request, $context, $id) {
            $m = DB::table('workspace_memberships')->whereIn('school_id', array_keys($context['schools']))->lockForUpdate()->find($id);
            abort_unless($m, 404);
            abort_unless(in_array($m->role, ['parent', 'teacher', 'staff']), 403, 'Administrator access is managed by the platform owner.');
            DB::table('workspace_memberships')->where('id', $id)->update(['status' => 'revoked', 'ends_at' => now(), 'updated_at' => now()]);
            $this->access->audit($request->user(), $m->school_id, 'membership.revoked', $id);
        });
        return $this->done($request, 'Membership revoked. Future workspace requests recheck access.');
    }

    public function guardian(Request $request)
    {
        $context = $this->schoolContext($request);
        abort_unless($context['role'] === 'parent', 403);
        $request->validate(['student_id' => 'required|integer']);
        // No name, roster or academic information is returned before verification.
        $exists = \App\Models\Students::where('id', $request->student_id)->whereHas('class_section.class', function ($q) use ($request) { $q->where('school_id', $request->school_id); })->exists();
        if ($exists) {
            DB::transaction(function () use ($request) {
                DB::table('workspace_guardians')->insertOrIgnore(['guardian_id' => $request->user()->id, 'student_id' => $request->student_id,
                    'school_id' => $request->school_id, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
                $this->access->audit($request->user(), $request->school_id, 'guardian.requested', $request->student_id);
            });
        }
        return $this->done($request, 'If the student reference matches this school, your request will be reviewed. No access is granted until verification.');
    }

    public function guardianStatus(Request $request, int $id)
    {
        $context = $this->context($request);
        abort_unless($this->access->can($context, 'memberships.manage'), 403);
        $request->validate(['status' => 'required|in:verified,rejected,revoked']);
        DB::transaction(function () use ($request, $context, $id) {
            $g = DB::table('workspace_guardians')->whereIn('school_id', array_keys($context['schools']))->lockForUpdate()->find($id);
            abort_unless($g, 404);
            abort_unless($this->access->students($request->user(), $context)->where('students.id', $g->student_id)->whereHas('class_section.class', function ($q) use ($g) { $q->where('school_id', $g->school_id); })->exists(), 403);
            DB::table('workspace_guardians')->where('id', $id)->update(['status' => $request->status, 'verified_by' => $request->user()->id,
                'verified_at' => $request->status === 'verified' ? now() : null, 'ends_at' => $request->status === 'verified' ? null : now(), 'updated_at' => now()]);
            $this->access->audit($request->user(), $g->school_id, 'guardian.'.$request->status, $id);
        });
        return $this->done($request, 'Guardian relationship '.$request->status.'.');
    }

    public function profile(Request $request)
    {
        $this->context($request);
        $data = $request->validate(['first_name' => 'required|string|max:100', 'last_name' => 'required|string|max:100',
            'timezone' => ['sometimes', 'required', Rule::in(\DateTimeZone::listIdentifiers())],
            'current_password' => 'required_with:password', 'password' => 'nullable|confirmed|min:12|max:128']);
        if (!empty($data['password'])) {
            abort_unless(Hash::check($data['current_password'], $request->user()->password), 422, 'Current password is incorrect.');
            $data['password'] = Hash::make($data['password']);
        } else { unset($data['password']); }
        unset($data['current_password']);
        $timezone = $data['timezone'] ?? null; unset($data['timezone']);
        DB::transaction(function () use ($request, $data, $timezone) {
            $request->user()->update($data);
            if ($timezone) { DB::table('workspace_profiles')->updateOrInsert(['user_id' => $request->user()->id], ['timezone' => $timezone, 'created_at' => now(), 'updated_at' => now()]); }
        });
        if (isset($data['password'])) {
            $request->user()->tokens()->delete();
            $request->user()->forceFill(['remember_token' => \Illuminate\Support\Str::random(60)])->save();
            $request->session()->regenerate();
        }
        return $this->done($request, 'Profile updated.');
    }

    public function logout(Request $request)
    {
        Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
