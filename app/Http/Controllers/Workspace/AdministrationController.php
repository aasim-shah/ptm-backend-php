<?php

namespace App\Http\Controllers\Workspace;

use App\Models\User;
use App\Services\WorkspaceAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Schools, Districts, Administrators, Sub-Super Admins and ad-free requests.
 * Each has its own page. The Super Admin creates, edits and suspends
 * organizations; District and School Admins manage what they are assigned.
 */
class AdministrationController extends AdminPage
{
    public const MARKETS = ['US' => 'United States', 'UK' => 'United Kingdom'];

    private function mayEditSchools(array $context): bool
    {
        return in_array($context['role'], ['super_admin', 'district_admin', 'school_admin'], true)
            || ($context['role'] === 'sub_super_admin' && $this->access->can($context, 'organizations.manage'));
    }

    private function districtsInScope(array $context)
    {
        $q = DB::table('workspace_districts');
        if (!WorkspaceAccess::platformWide($context)) {
            $q->whereIn('id', DB::table('workspace_district_schools')->whereIn('school_id', array_keys($context['schools']))->select('district_id'));
        }
        return $q;
    }

    private function ownDistrictId(Request $request, array $context): ?int
    {
        if ($context['role'] !== 'district_admin') { return null; }
        return (int) DB::table('workspace_memberships')->where('id', $context['membership_id'])->value('district_id') ?: null;
    }

    private function sync(): \App\Services\DistrictSync
    {
        return app(\App\Services\DistrictSync::class);
    }

    /** The old combined page now opens Schools; its anchors map to the new pages. */
    public function index(Request $request)
    {
        $context = $this->context($request);
        return redirect()->route('workspace.schools', ['context' => $context['id']]);
    }

    // ---------------------------------------------------------------- Schools

    public function schools(Request $request)
    {
        $context = $this->context($request);
        $filters = $request->validate(['q' => 'nullable|string|max:120', 'district_id' => 'nullable|integer', 'market' => 'nullable|in:US,UK,none', 'status' => 'nullable|in:active,suspended']);
        $query = DB::table('schools as s')->leftJoin('workspace_district_schools as ds', 'ds.school_id', '=', 's.id')->leftJoin('workspace_districts as d', 'd.id', '=', 'ds.district_id')
            ->whereIn('s.id', array_keys($context['schools']));
        if ($term = $this->like($filters['q'] ?? null)) {
            $query->where(fn ($q) => $q->where('s.school_name', 'like', $term)->orWhere('s.post_code', 'like', $term)->orWhere('s.email', 'like', $term)->orWhere('s.post_town', 'like', $term));
        }
        if (!empty($filters['district_id'])) { $query->where('ds.district_id', $filters['district_id']); }
        if (($filters['market'] ?? null) === 'none') { $query->whereNull('s.market'); } elseif (!empty($filters['market'])) { $query->where('s.market', $filters['market']); }
        if (!empty($filters['status'])) { $query->where('s.status', $filters['status']); }
        $schools = $query->select('s.id', 's.school_name', 's.address', 's.post_town', 's.post_code', 's.market', 's.status', 's.email', 's.created_at', 'd.id as district_id', 'd.name as district_name')
            ->orderBy('s.school_name')->paginate(25)->withQueryString();
        $ids = $schools->pluck('id');
        $members = DB::table('workspace_memberships')->whereIn('school_id', $ids)->where('status', 'active')->selectRaw('school_id, role, COUNT(DISTINCT user_id) as total')
            ->groupBy('school_id', 'role')->get()->groupBy('school_id');
        $students = DB::table('students')->join('class_sections as cs', 'cs.id', '=', 'students.class_section_id')->join('classes as c', 'c.id', '=', 'cs.class_id')
            ->whereIn('c.school_id', $ids)->whereNull('students.deleted_at')->selectRaw('c.school_id, COUNT(*) as total')->groupBy('c.school_id')->pluck('total', 'c.school_id');
        $all = DB::table('schools')->whereIn('id', array_keys($context['schools']));
        $stats = [
            ['label' => 'Schools', 'value' => (clone $all)->count(), 'icon' => 'university'],
            ['label' => 'Active', 'value' => (clone $all)->where('status', 'active')->count(), 'icon' => 'check-circle'],
            ['label' => 'Suspended', 'value' => (clone $all)->where('status', 'suspended')->count(), 'icon' => 'pause-circle'],
            ['label' => 'Without a market', 'value' => (clone $all)->whereNull('market')->count(), 'icon' => 'globe'],
        ];
        return $this->render($request, $context, 'schools', 'workspace.admin.schools', ['schools' => $schools, 'members' => $members, 'students' => $students,
            'filters' => $filters, 'stats' => $stats, 'districts' => $this->districtsInScope($context)->orderBy('name')->get(['id', 'name', 'market']),
            'canCreate' => WorkspaceAccess::platformWide($context)]);
    }

    public function school(Request $request, int $id)
    {
        $context = $this->context($request);
        abort_unless(isset($context['schools'][$id]), 404);
        $school = DB::table('schools as s')->leftJoin('workspace_district_schools as ds', 'ds.school_id', '=', 's.id')->leftJoin('workspace_districts as d', 'd.id', '=', 'ds.district_id')
            ->where('s.id', $id)->select('s.*', 'd.id as district_id', 'd.name as district_name')->first();
        $analytics = app(\App\Services\WorkspaceAnalytics::class);
        $scoped = array_merge($context, ['role' => 'school_admin', 'platform' => false, 'schools' => [$id => $school->school_name]]);
        $stats = [
            ['label' => 'Parents / Guardians', 'value' => $analytics->members($scoped, 'parent'), 'icon' => 'users'],
            ['label' => 'Teachers', 'value' => $analytics->members($scoped, 'teacher'), 'icon' => 'user'],
            ['label' => 'Staff', 'value' => $analytics->members($scoped, 'staff'), 'icon' => 'id-badge'],
            ['label' => 'Students', 'value' => \App\Models\Students::whereHas('class_section.class', fn ($q) => $q->where('school_id', $id))->count(), 'icon' => 'graduation-cap'],
            ['label' => 'Classes', 'value' => $analytics->classCount($scoped), 'icon' => 'book'],
        ];
        $admins = DB::table('workspace_memberships as m')->join('users as u', 'u.id', '=', 'm.user_id')->where('m.school_id', $id)->where('m.role', 'school_admin')
            ->whereNull('u.deleted_at')->select('m.id', 'm.status', 'm.created_at', 'u.id as user_id', 'u.first_name', 'u.last_name', 'u.email')->orderByDesc('m.status')->get();
        $activity = DB::table('workspace_audit as a')->leftJoin('users as u', 'u.id', '=', 'a.actor_id')->where('a.school_id', $id)
            ->select('a.*', 'u.first_name', 'u.last_name')->orderByDesc('a.id')->limit(10)->get();
        return $this->render($request, $context, 'schools', 'workspace.admin.school', ['school' => $school, 'stats' => $stats, 'admins' => $admins, 'activity' => $activity,
            'districts' => $this->districtsInScope($context)->orderBy('name')->get(['id', 'name', 'market']), 'canEdit' => $this->mayEditSchools($context),
            'platform' => WorkspaceAccess::platformWide($context), 'canAssign' => in_array($context['role'], ['super_admin', 'district_admin'], true),
            'timezones' => \DateTimeZone::listIdentifiers(\DateTimeZone::AMERICA | \DateTimeZone::EUROPE)]);
    }

    private function schoolRules(): array
    {
        return ['school_name' => 'required|string|max:191', 'market' => 'required|in:US,UK', 'address' => 'required|string|max:191', 'post_town' => 'nullable|string|max:191',
            'state' => 'nullable|string|max:191', 'post_code' => 'required|string|max:32', 'email' => 'nullable|email|max:191', 'phone' => 'nullable|string|max:40',
            'website' => 'nullable|url|max:191', 'timezone' => ['nullable', Rule::in(\DateTimeZone::listIdentifiers())]];
    }

    private function countryFor(string $market): int
    {
        return (int) DB::table('countries')->where('name', self::MARKETS[$market])->value('id');
    }

    public function createSchool(Request $request)
    {
        $context = $this->context($request);
        $this->platformOnly($context);
        $data = $request->validate($this->schoolRules() + ['district_id' => 'nullable|integer|exists:workspace_districts,id']);
        $id = DB::transaction(function () use ($request, $data) {
            $id = DB::table('schools')->insertGetId(collect($data)->except('district_id')->all() + ['user_id' => $request->user()->id, 'country_id' => $this->countryFor($data['market']),
                'image' => '', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            if (!empty($data['district_id'])) { DB::table('workspace_district_schools')->insert(['district_id' => $data['district_id'], 'school_id' => $id]); $this->sync()->toApp((int) $data['district_id']); }
            $this->access->audit($request->user(), $id, 'school.created', $id);
            return $id;
        });
        return $this->done($request, 'School created. Next, assign its School Admin.', route('workspace.school', ['id' => $id, 'context' => $context['id']]));
    }

    /** Updates a school's profile. Kept at /workspace/school-profile for existing clients. */
    public function schoolProfile(Request $request)
    {
        $context = $this->context($request);
        abort_unless($this->mayEditSchools($context), 403);
        $rules = ['school_id' => ['required', 'integer', Rule::in(array_keys($context['schools']))]] + $this->schoolRules();
        $rules['market'] = 'nullable|in:US,UK';
        if (WorkspaceAccess::platformWide($context)) { $rules['district_id'] = 'nullable|integer|exists:workspace_districts,id'; }
        $data = $request->validate($rules);
        DB::transaction(function () use ($request, $data, $context) {
            // Only fields the form sent are changed; validated data omits the others.
            $values = collect($data)->only(['school_name', 'address', 'post_town', 'state', 'post_code', 'email', 'phone', 'website', 'timezone'])->all();
            if (!empty($data['market']) && WorkspaceAccess::platformWide($context)) { $values += ['market' => $data['market'], 'country_id' => $this->countryFor($data['market'])]; }
            DB::table('schools')->where('id', $data['school_id'])->update($values + ['updated_at' => now()]);
            if (WorkspaceAccess::platformWide($context) && $request->has('district_id')) {
                $previous = DB::table('workspace_district_schools')->where('school_id', $data['school_id'])->value('district_id');
                DB::table('workspace_district_schools')->where('school_id', $data['school_id'])->delete();
                if (!empty($data['district_id'])) { DB::table('workspace_district_schools')->insert(['district_id' => $data['district_id'], 'school_id' => $data['school_id']]); }
                foreach (array_unique(array_filter([$previous, $data['district_id'] ?? null])) as $districtId) { $this->sync()->toApp((int) $districtId); }
                if (!$previous && empty($data['district_id'])) { DB::table('schools')->where('id', $data['school_id'])->update(['district_id' => null]); }
            }
            $this->access->audit($request->user(), $data['school_id'], 'school.profile.updated', $data['school_id']);
        });
        return $this->done($request, 'School profile saved.');
    }

    public function schoolStatus(Request $request, int $id)
    {
        $context = $this->context($request);
        $this->platformOnly($context);
        $data = $request->validate(['status' => 'required|in:active,suspended', 'reason' => 'required_if:status,suspended|nullable|string|max:300']);
        abort_unless(DB::table('schools')->where('id', $id)->exists(), 404);
        DB::table('schools')->where('id', $id)->update(['status' => $data['status'], 'updated_at' => now()]);
        $this->access->audit($request->user(), $id, $data['status'] === 'suspended' ? 'school.suspended' : 'school.reactivated', $id.($data['reason'] ?? null ? ':'.$data['reason'] : ''));
        return $this->done($request, $data['status'] === 'suspended' ? 'School suspended. Its members lose access until it is reactivated.' : 'School reactivated.');
    }

    public function export(Request $request)
    {
        $context = $this->context($request);
        abort_unless($this->access->can($context, 'reports.export'), 403);
        $schools = DB::table('schools')->whereIn('id', array_keys($context['schools']))->orderBy('id')->get(['id', 'school_name', 'market', 'status']);
        $this->access->audit($request->user(), count($context['schools']) === 1 ? array_key_first($context['schools']) : null, 'report.schools.exported', $context['id']);
        return response()->streamDownload(function () use ($schools) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['School ID', 'School', 'Market', 'Status', 'Active memberships', 'Students']);
            foreach ($schools as $school) {
                // Prevent spreadsheet formulas from being evaluated on opening the export.
                $name = preg_match('/^[\s]*[=+@\-]/u', $school->school_name) ? "'".$school->school_name : $school->school_name;
                $members = DB::table('workspace_memberships')->where('school_id', $school->id)->where('status', 'active')
                    ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                    ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))->count();
                $students = \App\Models\Students::whereHas('class_section.class', fn ($q) => $q->where('school_id', $school->id))->count();
                fputcsv($out, [$school->id, $name, $school->market, $school->status, $members, $students]);
            }
            fclose($out);
        }, 'ptm-schools.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    // -------------------------------------------------------------- Districts

    public function districts(Request $request)
    {
        $context = $this->context($request, ['super_admin', 'sub_super_admin', 'district_admin']);
        $filters = $request->validate(['q' => 'nullable|string|max:120', 'market' => 'nullable|in:US,UK', 'status' => 'nullable|in:active,suspended']);
        $query = $this->districtsInScope($context);
        if ($term = $this->like($filters['q'] ?? null)) { $query->where('name', 'like', $term); }
        if (!empty($filters['market'])) { $query->where('market', $filters['market']); }
        if (!empty($filters['status'])) { $query->where('status', $filters['status']); }
        $districts = $query->orderBy('name')->paginate(25)->withQueryString();
        $schoolCounts = DB::table('workspace_district_schools')->whereIn('district_id', $districts->pluck('id'))->selectRaw('district_id, COUNT(*) as total')->groupBy('district_id')->pluck('total', 'district_id');
        $admins = DB::table('workspace_memberships as m')->join('users as u', 'u.id', '=', 'm.user_id')->whereIn('m.district_id', $districts->pluck('id'))
            ->where('m.role', 'district_admin')->where('m.status', 'active')->select('m.district_id', 'u.first_name', 'u.last_name')->get()->groupBy('district_id');
        return $this->render($request, $context, 'districts', 'workspace.admin.districts', compact('districts', 'schoolCounts', 'admins', 'filters')
            + ['canCreate' => WorkspaceAccess::platformWide($context)]);
    }

    public function district(Request $request, int $id)
    {
        $context = $this->context($request, ['super_admin', 'sub_super_admin', 'district_admin']);
        $district = $this->districtsInScope($context)->where('id', $id)->first();
        abort_unless($district, 404);
        $schools = DB::table('schools as s')->join('workspace_district_schools as ds', 'ds.school_id', '=', 's.id')->where('ds.district_id', $id)
            ->select('s.id', 's.school_name', 's.post_code', 's.status', 's.market')->orderBy('s.school_name')->get();
        $available = WorkspaceAccess::platformWide($context) ? DB::table('schools')->whereNotIn('id', DB::table('workspace_district_schools')->select('school_id'))
            ->orderBy('school_name')->limit(500)->get(['id', 'school_name']) : collect();
        $admins = DB::table('workspace_memberships as m')->join('users as u', 'u.id', '=', 'm.user_id')->where('m.district_id', $id)->where('m.role', 'district_admin')
            ->whereNull('u.deleted_at')->select('m.id', 'm.status', 'm.created_at', 'u.id as user_id', 'u.first_name', 'u.last_name', 'u.email')->orderByDesc('m.status')->get();
        $activity = DB::table('workspace_audit as a')->leftJoin('users as u', 'u.id', '=', 'a.actor_id')->whereIn('a.school_id', $schools->pluck('id'))
            ->select('a.*', 'u.first_name', 'u.last_name')->orderByDesc('a.id')->limit(10)->get();
        return $this->render($request, $context, 'districts', 'workspace.admin.district', compact('district', 'schools', 'available', 'admins', 'activity')
            + ['platform' => WorkspaceAccess::platformWide($context), 'timezones' => \DateTimeZone::listIdentifiers(\DateTimeZone::AMERICA | \DateTimeZone::EUROPE)]);
    }

    public function createDistrict(Request $request)
    {
        $context = $this->context($request);
        $this->platformOnly($context);
        $data = $request->validate(['name' => 'required|string|max:191|unique:workspace_districts,name', 'market' => 'required|in:US,UK', 'timezone' => ['nullable', Rule::in(\DateTimeZone::listIdentifiers())]]);
        $id = DB::transaction(function () use ($request, $data) {
            $id = DB::table('workspace_districts')->insertGetId($data + ['status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            $this->sync()->toApp($id);
            $this->access->audit($request->user(), null, 'district.created', $id);
            return $id;
        });
        return $this->done($request, 'District created. Add its schools and assign a District Admin.', route('workspace.district', ['id' => $id, 'context' => $context['id']]));
    }

    public function updateDistrict(Request $request, int $id)
    {
        $context = $this->context($request);
        $this->platformOnly($context);
        $data = $request->validate(['name' => ['required', 'string', 'max:191', Rule::unique('workspace_districts', 'name')->ignore($id)], 'market' => 'required|in:US,UK',
            'timezone' => ['nullable', Rule::in(\DateTimeZone::listIdentifiers())]]);
        abort_unless(DB::table('workspace_districts')->where('id', $id)->exists(), 404);
        DB::table('workspace_districts')->where('id', $id)->update($data + ['updated_at' => now()]);
        $this->sync()->toApp($id);
        $this->access->audit($request->user(), null, 'district.updated', $id);
        return $this->done($request, 'District saved.');
    }

    public function districtStatus(Request $request, int $id)
    {
        $context = $this->context($request);
        $this->platformOnly($context);
        $data = $request->validate(['status' => 'required|in:active,suspended', 'reason' => 'required_if:status,suspended|nullable|string|max:300']);
        abort_unless(DB::table('workspace_districts')->where('id', $id)->exists(), 404);
        DB::table('workspace_districts')->where('id', $id)->update(['status' => $data['status'], 'updated_at' => now()]);
        $this->sync()->toApp($id);
        $this->access->audit($request->user(), null, $data['status'] === 'suspended' ? 'district.suspended' : 'district.reactivated', $id.($data['reason'] ?? null ? ':'.$data['reason'] : ''));
        return $this->done($request, $data['status'] === 'suspended' ? 'District suspended. District-level access stops; each school keeps its own status.' : 'District reactivated.');
    }

    public function districtSchool(Request $request, int $id)
    {
        $context = $this->context($request);
        $this->platformOnly($context);
        $data = $request->validate(['school_id' => 'required|integer|exists:schools,id', 'action' => 'required|in:add,remove']);
        abort_unless(DB::table('workspace_districts')->where('id', $id)->exists(), 404);
        DB::transaction(function () use ($request, $data, $id) {
            if ($data['action'] === 'add') {
                abort_if(DB::table('workspace_district_schools')->where('school_id', $data['school_id'])->exists(), 422, 'This school already belongs to a district. Remove it there first.');
                DB::table('workspace_district_schools')->insert(['district_id' => $id, 'school_id' => $data['school_id']]);
                $market = DB::table('workspace_districts')->where('id', $id)->value('market');
                DB::table('schools')->where('id', $data['school_id'])->whereNull('market')->update(['market' => $market]);
            } else {
                DB::table('workspace_district_schools')->where('district_id', $id)->where('school_id', $data['school_id'])->delete();
            }
            $this->sync()->toApp($id);
            if ($data['action'] === 'remove') { DB::table('schools')->where('id', $data['school_id'])->whereNotIn('id', DB::table('workspace_district_schools')->select('school_id'))->update(['district_id' => null]); }
            $this->access->audit($request->user(), $data['school_id'], 'district.school.'.($data['action'] === 'add' ? 'added' : 'removed'), $id.':'.$data['school_id']);
        });
        return $this->done($request, $data['action'] === 'add' ? 'School added to the district.' : 'School removed from the district.');
    }

    // --------------------------------------------------------- Administrators

    public function administrators(Request $request)
    {
        $context = $this->context($request, ['super_admin', 'district_admin']);
        $filters = $request->validate(['q' => 'nullable|string|max:120', 'role' => 'nullable|in:school_admin,district_admin', 'status' => 'nullable|in:active,revoked']);
        $districts = $this->districtsInScope($context)->pluck('id');
        $query = DB::table('workspace_memberships as m')->join('users as u', 'u.id', '=', 'm.user_id')->leftJoin('schools as s', 's.id', '=', 'm.school_id')
            ->leftJoin('workspace_districts as d', 'd.id', '=', 'm.district_id')->whereIn('m.role', ['school_admin', 'district_admin'])->whereNull('u.deleted_at')
            ->where(fn ($q) => $q->whereIn('m.school_id', array_keys($context['schools']))->orWhereIn('m.district_id', $districts));
        if ($term = $this->like($filters['q'] ?? null)) {
            $query->where(fn ($q) => $q->where('u.first_name', 'like', $term)->orWhere('u.last_name', 'like', $term)->orWhere('u.email', 'like', $term)
                ->orWhere('s.school_name', 'like', $term)->orWhere('d.name', 'like', $term));
        }
        if (!empty($filters['role'])) { $query->where('m.role', $filters['role']); }
        if (!empty($filters['status'])) { $query->where('m.status', $filters['status']); }
        $admins = $query->select('m.id', 'm.role', 'm.status', 'm.created_at', 'u.id as user_id', 'u.first_name', 'u.last_name', 'u.email', 'u.email_verified_at', 's.school_name', 'd.name as district_name')
            ->orderBy('m.status')->orderBy('u.first_name')->paginate(25)->withQueryString();
        return $this->render($request, $context, 'administrators', 'workspace.admin.administrators', compact('admins', 'filters')
            + ['districts' => WorkspaceAccess::platformWide($context) ? DB::table('workspace_districts')->orderBy('name')->get(['id', 'name']) : collect(),
                'schools' => $context['schools'], 'platform' => WorkspaceAccess::platformWide($context)]);
    }

    private function verifiedAccount(string $email): User
    {
        $user = User::where('email', $email)->whereNull('deleted_at')->first();
        abort_unless($user, 422, 'No PTM account uses this email. Ask the person to create an account first, or send them a School Admin invitation.');
        abort_unless((int) $user->status === 1 && $user->email_verified_at, 422, 'This account is suspended or its email is not verified yet.');
        return $user;
    }

    public function assignAdmin(Request $request)
    {
        $context = $this->context($request);
        abort_unless(in_array($context['role'], ['super_admin', 'district_admin'], true), 403);
        $data = $request->validate(['email' => 'required|email', 'school_id' => ['required', 'integer', Rule::in(array_keys($context['schools']))]]);
        $user = $this->verifiedAccount($data['email']);
        DB::transaction(function () use ($request, $data, $user) {
            DB::table('users')->where('id', $user->id)->lockForUpdate()->first();
            DB::table('workspace_memberships')->updateOrInsert(['user_id' => $user->id, 'school_id' => $data['school_id'], 'role' => 'school_admin'],
                ['status' => 'active', 'permissions' => '[]', 'approved_by' => $request->user()->id, 'starts_at' => now(), 'ends_at' => null, 'created_at' => now(), 'updated_at' => now()]);
            $this->access->audit($request->user(), $data['school_id'], 'school.admin.assigned', $user->id);
        });
        return $this->done($request, $user->first_name.' '.$user->last_name.' is now a School Admin.');
    }

    public function assignDistrictAdmin(Request $request)
    {
        $context = $this->context($request);
        $this->platformOnly($context);
        $data = $request->validate(['email' => 'required|email', 'district_id' => 'required|integer|exists:workspace_districts,id']);
        $user = $this->verifiedAccount($data['email']);
        abort_if($user->hasRole('Super Admin'), 422, 'A Super Admin already has full platform authority.');
        DB::transaction(function () use ($request, $data, $user) {
            DB::table('users')->where('id', $user->id)->lockForUpdate()->first();
            DB::table('workspace_memberships')->updateOrInsert(['user_id' => $user->id, 'district_id' => $data['district_id'], 'role' => 'district_admin'],
                ['school_id' => null, 'status' => 'active', 'permissions' => '[]', 'approved_by' => $request->user()->id, 'starts_at' => now(), 'ends_at' => null, 'created_at' => now(), 'updated_at' => now()]);
            $this->access->audit($request->user(), null, 'district.admin.assigned', $user->id.':'.$data['district_id']);
        });
        return $this->done($request, $user->first_name.' '.$user->last_name.' is now a District Admin.');
    }

    public function revokeAdmin(Request $request, int $id)
    {
        $context = $this->context($request, ['super_admin', 'district_admin']);
        DB::transaction(function () use ($request, $context, $id) {
            $m = DB::table('workspace_memberships')->whereIn('role', ['school_admin', 'district_admin'])->lockForUpdate()->find($id);
            abort_unless($m, 404);
            $allowed = WorkspaceAccess::platformWide($context) || ($m->role === 'school_admin' && isset($context['schools'][$m->school_id]));
            abort_unless($allowed, 403, 'Only the Super Admin can remove a District Admin.');
            abort_if((int) $m->user_id === (int) $request->user()->id, 422, 'You cannot remove your own administrator access.');
            DB::table('workspace_memberships')->where('id', $id)->update(['status' => 'revoked', 'ends_at' => now(), 'updated_at' => now()]);
            $this->access->audit($request->user(), $m->school_id, $m->role.'.revoked', $m->user_id);
        });
        return $this->done($request, 'Administrator access removed.');
    }

    // -------------------------------------------------------- Sub-Super Admins

    public function subSuperAdmins(Request $request)
    {
        $context = $this->context($request);
        $this->platformOnly($context);
        $filters = $request->validate(['q' => 'nullable|string|max:120', 'status' => 'nullable|in:active,revoked']);
        $query = DB::table('workspace_memberships as m')->join('users as u', 'u.id', '=', 'm.user_id')->leftJoin('schools as s', 's.id', '=', 'm.school_id')
            ->leftJoin('workspace_districts as d', 'd.id', '=', 'm.district_id')->where('m.role', 'sub_super_admin');
        if ($term = $this->like($filters['q'] ?? null)) { $query->where(fn ($q) => $q->where('u.first_name', 'like', $term)->orWhere('u.last_name', 'like', $term)->orWhere('u.email', 'like', $term)); }
        if (!empty($filters['status'])) { $query->where('m.status', $filters['status']); }
        $delegations = $query->select('m.*', 'u.first_name', 'u.last_name', 'u.email', 's.school_name', 'd.name as district_name')->orderBy('m.status')->orderByDesc('m.id')->paginate(25)->withQueryString();
        return $this->render($request, $context, 'sub-super-admins', 'workspace.admin.sub-super-admins', compact('delegations', 'filters') + [
            'schools' => $context['schools'], 'districts' => DB::table('workspace_districts')->orderBy('name')->get(['id', 'name'])]);
    }

    public function delegate(Request $request)
    {
        $context = $this->context($request);
        $this->platformOnly($context);
        $data = $request->validate(['email' => 'required|email', 'scope' => 'nullable|in:school,district', 'school_id' => 'nullable|integer|exists:schools,id',
            'district_id' => 'nullable|integer|exists:workspace_districts,id', 'permissions' => 'nullable|array',
            'permissions.*' => ['string', Rule::in(WorkspaceAccess::DELEGATED_PERMISSIONS)]]);
        if (($data['scope'] ?? null) === 'school') { $data['district_id'] = null; } elseif (($data['scope'] ?? null) === 'district') { $data['school_id'] = null; }
        abort_unless((!empty($data['school_id']) xor !empty($data['district_id'])), 422, 'Choose one school or one district for this delegation.');
        abort_unless(!empty($data['permissions']), 422, 'Choose at least one allowed action.');
        $user = $this->verifiedAccount($data['email']);
        abort_if($user->hasRole('Super Admin'), 422, 'A Super Admin already has full platform authority.');
        DB::transaction(function () use ($request, $data, $user) {
            DB::table('users')->where('id', $user->id)->lockForUpdate()->first();
            DB::table('workspace_memberships')->updateOrInsert(['user_id' => $user->id, 'role' => 'sub_super_admin',
                'school_id' => $data['school_id'] ?? null, 'district_id' => $data['district_id'] ?? null],
                ['permissions' => json_encode(array_values($data['permissions'])), 'status' => 'active', 'approved_by' => $request->user()->id,
                    'starts_at' => now(), 'ends_at' => null, 'created_at' => now(), 'updated_at' => now()]);
            $this->access->audit($request->user(), $data['school_id'] ?? null, 'delegation.saved', $user->id);
        });
        return $this->done($request, 'Sub-Super Admin access saved for '.$user->first_name.' '.$user->last_name.'.');
    }

    public function updateDelegation(Request $request, int $id)
    {
        $context = $this->context($request);
        $this->platformOnly($context);
        $data = $request->validate(['permissions' => 'required|array|min:1', 'permissions.*' => ['string', Rule::in(WorkspaceAccess::DELEGATED_PERMISSIONS)]]);
        $row = DB::table('workspace_memberships')->where('role', 'sub_super_admin')->find($id);
        abort_unless($row, 404);
        DB::table('workspace_memberships')->where('id', $id)->update(['permissions' => json_encode(array_values($data['permissions'])), 'updated_at' => now()]);
        $this->access->audit($request->user(), $row->school_id, 'delegation.updated', $row->user_id);
        return $this->done($request, 'Allowed actions updated.');
    }

    public function revoke(Request $request, int $id)
    {
        $this->platformOnly($this->context($request));
        DB::transaction(function () use ($request, $id) {
            $row = DB::table('workspace_memberships')->where('role', 'sub_super_admin')->lockForUpdate()->find($id);
            abort_unless($row, 404);
            DB::table('workspace_memberships')->where('id', $id)->update(['status' => 'revoked', 'ends_at' => now(), 'updated_at' => now()]);
            $this->access->audit($request->user(), $row->school_id, 'delegation.revoked', $row->user_id);
        });
        return $this->done($request, 'Delegation revoked.');
    }

    // --------------------------------------------------- Mobile role requests

    /** Requests from the mobile app to act as staff or an administrator at a school (Part A7). */
    public function roleRequests(Request $request, \App\Services\RoleContextService $contexts)
    {
        $context = $this->context($request, ['super_admin', 'school_admin']);
        $status = $request->validate(['status' => 'nullable|in:pending,active,suspended,rejected,ended,all'])['status'] ?? 'pending';
        $query = $contexts->administrableQuery($request->user())->with('user:id,first_name,last_name,email')->orderBy('requested_at', 'DESC');
        if ($status !== 'all') { $query->where('status', $status); }
        $requests = $query->paginate(25)->withQueryString();
        foreach ($requests as $item) {
            $item->organization_summary = $contexts->organizationSummary($item->organization_type, $item->organization_id);
            $item->effective_permissions = $contexts->effectivePermissions($item);
        }
        return $this->render($request, $context, 'role-requests', 'workspace.admin.role-requests', ['requests' => $requests, 'status' => $status,
            'permissions' => DB::table('context_permissions')->orderBy('key')->get()]);
    }

    // ------------------------------------------------------ Ad-free requests

    public function adFree(Request $request)
    {
        $context = $this->context($request);
        $filters = $request->validate(['status' => 'nullable|in:pending,approved,rejected']);
        $query = DB::table('workspace_ad_free_requests as r')->leftJoin('schools as s', 's.id', '=', 'r.school_id')->leftJoin('workspace_districts as d', 'd.id', '=', 'r.district_id')
            ->leftJoin('users as u', 'u.id', '=', 'r.requested_by');
        if (!WorkspaceAccess::platformWide($context)) {
            $district = $this->ownDistrictId($request, $context);
            $query->where(fn ($q) => $q->whereIn('r.school_id', array_keys($context['schools']))->when($district, fn ($q) => $q->orWhere('r.district_id', $district)));
        }
        if (!empty($filters['status'])) { $query->where('r.status', $filters['status']); }
        $requests = $query->select('r.*', 's.school_name', 'd.name as district_name', 'u.first_name', 'u.last_name')->orderByRaw("r.status = 'pending' desc")->orderByDesc('r.id')->paginate(25)->withQueryString();
        return $this->render($request, $context, 'ad-free', 'workspace.admin.ad-free', compact('requests', 'filters') + [
            'canRequest' => in_array($context['role'], ['school_admin', 'district_admin'], true), 'canReview' => WorkspaceAccess::platformWide($context),
            'schools' => $context['schools']]);
    }

    public function requestAdFree(Request $request)
    {
        $context = $this->context($request);
        abort_unless(in_array($context['role'], ['school_admin', 'district_admin']), 403);
        $data = $request->validate(['reason' => 'required|string|max:2000', 'school_id' => ['nullable', 'integer', Rule::in(array_keys($context['schools']))]]);
        $district = $context['role'] === 'district_admin' && empty($data['school_id']) ? $this->ownDistrictId($request, $context) : null;
        abort_unless($district || !empty($data['school_id']), 422, 'Choose a school.');
        DB::transaction(function () use ($request, $data, $district) {
            DB::table('users')->where('id', $request->user()->id)->lockForUpdate()->first();
            $id = DB::table('workspace_ad_free_requests')->insertGetId(['school_id' => $data['school_id'] ?? null,
                'district_id' => $district, 'requested_by' => $request->user()->id, 'reason' => $data['reason'],
                'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
            $this->access->audit($request->user(), $data['school_id'] ?? null, 'ad_free.requested', $id);
        });
        return $this->done($request, 'Ad-free request submitted for Super Admin review.');
    }

    public function decideAdFree(Request $request, int $id)
    {
        $this->platformOnly($this->context($request));
        $data = $request->validate(['status' => 'required|in:approved,rejected']);
        DB::transaction(function () use ($request, $data, $id) {
            $row = DB::table('workspace_ad_free_requests')->lockForUpdate()->find($id);
            abort_unless($row, 404); abort_unless($row->status === 'pending', 409);
            DB::table('workspace_ad_free_requests')->where('id', $id)->update(['status' => $data['status'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'updated_at' => now()]);
            $this->access->audit($request->user(), $row->school_id, 'ad_free.'.$data['status'], $id);
        });
        return $this->done($request, 'Request '.$data['status'].'.');
    }
}
