<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\District;
use App\Models\Schools;
use App\Services\RoleContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Web Panel API for School Districts (Document 12, J1: PTM creates the organization, then its
 * admin joins). The mobile app does not call it; it lists districts through
 * GET /get/schools?organization_type=district and District Admins join with POST /auth/role-requests.
 *
 * Super Admin only: districts are institution records, not something a signed-up user creates.
 */
class DistrictAdminController extends Controller
{
    public function __construct(private RoleContextService $contexts)
    {
    }

    /** GET /admin/districts?country_id=231&status=active|inactive|all (default all) */
    public function index(Request $request)
    {
        if ($denied = $this->denyUnlessSuperAdmin($request)) {
            return $denied;
        }
        $query = District::with('country')->withCount('schools')->orderBy('name');
        if ($request->filled('country_id')) {
            $query->where('country_id', $request->get('country_id'));
        }
        $status = $request->get('status', 'all');
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        return response()->json(['error' => false, 'message' => 'Districts fetched', 'data' => $query->paginate(20), 'code' => 200]);
    }

    /** POST /admin/districts  { "name": "...", "country_id": 231, "status": "active" } */
    public function store(Request $request)
    {
        if ($denied = $this->denyUnlessSuperAdmin($request)) {
            return $denied;
        }
        $validator = Validator::make($request->all(), [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('districts', 'name')->where('country_id', $request->input('country_id')),
            ],
            'country_id' => 'required|integer|exists:countries,id',
            'status' => 'nullable|in:active,inactive',
        ], [
            'name.unique' => 'A district with this name already exists in this country.',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $district = District::create([
            'name' => trim($request->input('name')),
            'country_id' => (int) $request->input('country_id'),
            'status' => $request->input('status', 'active'),
        ]);
        AuditLog::record($request->user()->id, 'district.created', $district);
        app(\App\Services\DistrictSync::class)->fromApp($district->id);

        return response()->json(['error' => false, 'message' => 'District created', 'data' => $district->load('country'), 'code' => 201], 201);
    }

    /** PUT /admin/districts/{id}  { "name": "...", "status": "inactive" }. Inactive districts drop out of the app's list. */
    public function update(Request $request, $id)
    {
        if ($denied = $this->denyUnlessSuperAdmin($request)) {
            return $denied;
        }
        $district = District::find($id);
        if (!$district) {
            return $this->error('District not found.', 404);
        }
        $validator = Validator::make($request->all(), [
            'name' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('districts', 'name')->where('country_id', $district->country_id)->ignore($district->id),
            ],
            'status' => 'sometimes|required|in:active,inactive',
        ], [
            'name.unique' => 'A district with this name already exists in this country.',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $district->fill($request->only(['name', 'status']))->save();
        AuditLog::record($request->user()->id, 'district.updated', $district, null, $request->only(['name', 'status']));
        app(\App\Services\DistrictSync::class)->fromApp($district->id);

        return response()->json(['error' => false, 'message' => 'District updated', 'data' => $district->load('country'), 'code' => 200]);
    }

    /**
     * POST /admin/districts/{id}/schools  { "school_ids": [5, 6] }
     * Puts the schools in this district (schools.district_id). Schools must be in the district's country.
     */
    public function assignSchools(Request $request, $id)
    {
        if ($denied = $this->denyUnlessSuperAdmin($request)) {
            return $denied;
        }
        $district = District::find($id);
        if (!$district) {
            return $this->error('District not found.', 404);
        }
        $validator = Validator::make($request->all(), [
            'school_ids' => 'required|array|min:1',
            'school_ids.*' => 'integer|distinct|exists:schools,id',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $schoolIds = array_map('intval', $request->input('school_ids'));
        $outsideCountry = Schools::whereIn('id', $schoolIds)->where('country_id', '!=', $district->country_id)->pluck('school_name');
        if ($outsideCountry->isNotEmpty()) {
            return $this->error('These schools are outside the district\'s country: ' . $outsideCountry->implode(', '), 422);
        }

        Schools::whereIn('id', $schoolIds)->update(['district_id' => $district->id]);
        AuditLog::record($request->user()->id, 'district.schools_assigned', $district, null, ['school_ids' => $schoolIds]);
        $sync = app(\App\Services\DistrictSync::class);
        $sync->fromApp($district->id);
        // A school moved here may have left another web district; refresh those too.
        foreach (DB::table('workspace_districts')->whereNotNull('app_district_id')->where('app_district_id', '!=', $district->id)->pluck('app_district_id') as $other) { $sync->fromApp((int) $other); }

        return response()->json([
            'error' => false,
            'message' => 'Schools assigned',
            'data' => $district->load('country')->loadCount('schools'),
            'code' => 200,
        ]);
    }

    private function denyUnlessSuperAdmin(Request $request)
    {
        return $this->contexts->isSuperAdmin($request->user())
            ? null
            : $this->error(trans('no_permission_message'), 403);
    }

    private function error(string $message, int $code)
    {
        return response()->json(['error' => true, 'message' => $message, 'code' => $code], $code);
    }
}
