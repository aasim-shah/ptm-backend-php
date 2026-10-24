<?php
namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\WorkspaceAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdministrationController extends Controller
{
    public function __construct(private WorkspaceAccess $access) {}

    private function context(Request $request): array
    {
        $context = $this->access->resolve($request->user(), $request->input('context', $request->session()->get('workspace_context')));
        abort_unless(in_array($context['role'], ['super_admin', 'sub_super_admin', 'district_admin', 'school_admin']), 403);
        return $context;
    }

    public function index(Request $request)
    {
        $context = $this->context($request);
        $schools = DB::table('schools')->whereIn('id', array_keys($context['schools']))->get(['id', 'school_name', 'address', 'post_code']);
        $districts = DB::table('workspace_districts')->whereIn('id', DB::table('workspace_district_schools')->whereIn('school_id', $schools->pluck('id'))->select('district_id'))->get();
        if ($context['role'] === 'super_admin') { $districts = DB::table('workspace_districts')->get(); }
        $requests = DB::table('workspace_ad_free_requests');
        if ($context['role'] !== 'super_admin') {
            $requests->whereIn('school_id', $schools->pluck('id'));
            if ($context['role'] === 'district_admin') {
                $districtId = $this->access->memberships($request->user())->where('id', $context['membership_id'])->value('district_id');
                $requests->orWhere('district_id', $districtId);
            }
        }
        $audit = DB::table('workspace_audit')->whereIn('school_id', $schools->pluck('id'));
        if ($context['role'] === 'super_admin') { $audit = DB::table('workspace_audit'); }
        $mayAudit = $this->access->can($context, 'audit.view');
        return view('workspace.administration', [
            'context' => $context, 'contexts' => $this->access->webContexts($request->user()), 'access' => $this->access,
            'page' => 'administration', 'schools' => $schools, 'districts' => $districts,
            'requests' => $requests->orderByDesc('id')->limit(100)->get(),
            'audit' => $mayAudit ? $audit->orderByDesc('id')->limit(100)->get() : collect(),
            'delegations' => $context['role'] === 'super_admin' ? DB::table('workspace_memberships as m')->join('users as u', 'u.id', '=', 'm.user_id')->where('m.role', 'sub_super_admin')->select('m.*', 'u.email')->get() : collect(),
        ]);
    }

    public function schoolProfile(Request $request)
    {
        $context = $this->context($request);
        abort_unless(in_array($context['role'], ['super_admin', 'district_admin', 'school_admin']), 403);
        $data = $request->validate(['school_id'=>['required','integer',Rule::in(array_keys($context['schools']))],
            'school_name'=>'required|string|max:191', 'address'=>'required|string|max:255', 'post_code'=>'required|string|max:32']);
        DB::transaction(function () use ($request, $data) {
            DB::table('schools')->where('id',$data['school_id'])->update(['school_name'=>$data['school_name'],
                'address'=>$data['address'],'post_code'=>$data['post_code'],'updated_at'=>now()]);
            $this->access->audit($request->user(),$data['school_id'],'school.profile.updated',$data['school_id']);
        });
        return back()->with('workspace_success','School profile updated.');
    }

    public function assignAdmin(Request $request)
    {
        $context = $this->context($request);
        abort_unless(in_array($context['role'], ['super_admin','district_admin']),403);
        $data=$request->validate(['email'=>'required|email','school_id'=>['required','integer',Rule::in(array_keys($context['schools']))]]);
        $user=User::where('email',$data['email'])->where('status',1)->whereNotNull('email_verified_at')->firstOrFail();
        DB::transaction(function () use ($request,$data,$user) {
            DB::table('users')->where('id',$user->id)->lockForUpdate()->first();
            DB::table('workspace_memberships')->updateOrInsert(['user_id'=>$user->id,'school_id'=>$data['school_id'],'role'=>'school_admin'],
                ['status'=>'active','permissions'=>'[]','approved_by'=>$request->user()->id,'starts_at'=>now(),'ends_at'=>null,'created_at'=>now(),'updated_at'=>now()]);
            $this->access->audit($request->user(),$data['school_id'],'school.admin.assigned',$user->id);
        });
        return back()->with('workspace_success','School Admin assigned.');
    }

    public function export(Request $request)
    {
        $context = $this->context($request);
        abort_unless($this->access->can($context, 'reports.export'), 403);
        $schools = DB::table('schools')->whereIn('id', array_keys($context['schools']))->orderBy('id')->get(['id', 'school_name']);
        $this->access->audit($request->user(), count($context['schools']) === 1 ? array_key_first($context['schools']) : null, 'report.schools.exported', $context['id']);
        return response()->streamDownload(function () use ($schools) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['School ID', 'School', 'Active memberships', 'Students']);
            foreach ($schools as $school) {
                // Prevent spreadsheet formulas from being evaluated on opening the export.
                $name = preg_match('/^[\s]*[=+@\-]/u', $school->school_name) ? "'".$school->school_name : $school->school_name;
                $members = DB::table('workspace_memberships')->where('school_id', $school->id)->where('status', 'active')
                    ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                    ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))->count();
                $students = \App\Models\Students::whereHas('class_section.class', fn ($q) => $q->where('school_id', $school->id))->count();
                fputcsv($out, [$school->id, $name, $members, $students]);
            }
            fclose($out);
        }, 'ptm-schools.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    public function delegate(Request $request)
    {
        $context = $this->context($request);
        abort_unless($context['role'] === 'super_admin', 403);
        $data = $request->validate(['email' => 'required|email', 'school_id' => 'nullable|integer|exists:schools,id',
            'district_id' => 'nullable|integer|exists:workspace_districts,id', 'permissions' => 'nullable|array',
            'permissions.*' => ['string', Rule::in(WorkspaceAccess::DELEGATED_PERMISSIONS)]]);
        abort_unless((!empty($data['school_id']) xor !empty($data['district_id'])), 422, 'Select exactly one school or district.');
        $user = User::where('email', $data['email'])->where('status', 1)->whereNotNull('email_verified_at')->firstOrFail();
        abort_if($user->hasRole('Super Admin'), 422, 'A Super Admin already has full platform authority.');
        DB::transaction(function () use ($request, $data, $user) {
            DB::table('users')->where('id', $user->id)->lockForUpdate()->first();
            DB::table('workspace_memberships')->updateOrInsert(['user_id' => $user->id, 'role' => 'sub_super_admin',
                'school_id' => $data['school_id'] ?? null, 'district_id' => $data['district_id'] ?? null],
                ['permissions' => json_encode($data['permissions'] ?? []), 'status' => 'active', 'approved_by' => $request->user()->id,
                    'starts_at' => now(), 'ends_at' => null, 'created_at' => now(), 'updated_at' => now()]);
            $this->access->audit($request->user(), $data['school_id'] ?? null, 'delegation.saved', $user->id);
        });
        return back()->with('workspace_success', 'Sub-Super Admin delegation saved.');
    }

    public function revoke(Request $request, int $id)
    {
        abort_unless($this->context($request)['role'] === 'super_admin', 403);
        DB::transaction(function () use ($request, $id) {
            $row = DB::table('workspace_memberships')->where('role', 'sub_super_admin')->lockForUpdate()->find($id);
            abort_unless($row, 404);
            DB::table('workspace_memberships')->where('id', $id)->update(['status' => 'revoked', 'ends_at' => now(), 'updated_at' => now()]);
            $this->access->audit($request->user(), $row->school_id, 'delegation.revoked', $id);
        });
        return back()->with('workspace_success', 'Delegation revoked.');
    }

    public function requestAdFree(Request $request)
    {
        $context = $this->context($request);
        abort_unless(in_array($context['role'], ['school_admin', 'district_admin']), 403);
        $data = $request->validate(['reason' => 'required|string|max:2000', 'school_id' => ['nullable', 'integer', Rule::in(array_keys($context['schools']))]]);
        $district = $context['role'] === 'district_admin' && empty($data['school_id'])
            ? $this->access->memberships($request->user())->where('id', $context['membership_id'])->value('district_id') : null;
        abort_unless($district || !empty($data['school_id']), 422, 'Choose a school.');
        DB::transaction(function () use ($request, $data, $district) {
            DB::table('users')->where('id', $request->user()->id)->lockForUpdate()->first();
            $id = DB::table('workspace_ad_free_requests')->insertGetId(['school_id' => $data['school_id'] ?? null,
                'district_id' => $district, 'requested_by' => $request->user()->id, 'reason' => $data['reason'],
                'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
            $this->access->audit($request->user(), $data['school_id'] ?? null, 'ad_free.requested', $id);
        });
        return back()->with('workspace_success', 'Ad-free request submitted for Super Admin review.');
    }

    public function decideAdFree(Request $request, int $id)
    {
        abort_unless($this->context($request)['role'] === 'super_admin', 403);
        $data = $request->validate(['status' => 'required|in:approved,rejected']);
        DB::transaction(function () use ($request, $data, $id) {
            $row = DB::table('workspace_ad_free_requests')->lockForUpdate()->find($id);
            abort_unless($row, 404); abort_unless($row->status === 'pending', 409);
            DB::table('workspace_ad_free_requests')->where('id', $id)->update(['status' => $data['status'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'updated_at' => now()]);
            $this->access->audit($request->user(), $row->school_id, 'ad_free.'.$data['status'], $id);
        });
        return back()->with('workspace_success', 'Request reviewed.');
    }
}
