<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps the web panel's districts (workspace_districts + workspace_district_schools)
 * and the mobile app's districts (districts + schools.district_id) as one set.
 * Each web district links to one app district through app_district_id.
 */
class DistrictSync
{
    public const COUNTRIES = ['US' => 'United States', 'UK' => 'United Kingdom'];

    private function ready(): bool
    {
        return Schema::hasTable('districts') && Schema::hasColumn('workspace_districts', 'app_district_id');
    }

    public function countryId(string $market): int
    {
        return (int) DB::table('countries')->where('name', self::COUNTRIES[strtoupper($market)] ?? '')->value('id');
    }

    public function marketFor(int $countryId): ?string
    {
        $name = DB::table('countries')->where('id', $countryId)->value('name');
        return array_search($name, self::COUNTRIES, true) ?: null;
    }

    /** Web district changed: mirror name, country, status and member schools into the app tables. */
    public function toApp(int $workspaceDistrictId): void
    {
        if (!$this->ready()) { return; }
        $d = DB::table('workspace_districts')->find($workspaceDistrictId);
        if (!$d) { return; }
        $values = ['name' => $d->name, 'country_id' => $this->countryId($d->market), 'status' => ($d->status ?? 'active') === 'active' ? 'active' : 'inactive', 'updated_at' => now()];
        $appId = $d->app_district_id && DB::table('districts')->where('id', $d->app_district_id)->exists() ? (int) $d->app_district_id : null;
        $appId ??= DB::table('districts')->where('name', $d->name)->where('country_id', $values['country_id'])->value('id');
        if ($appId) { DB::table('districts')->where('id', $appId)->update($values); }
        else { $appId = DB::table('districts')->insertGetId($values + ['created_at' => now()]); }
        DB::table('workspace_districts')->where('id', $d->id)->update(['app_district_id' => $appId]);
        $members = DB::table('workspace_district_schools')->where('district_id', $d->id)->pluck('school_id');
        DB::table('schools')->where('district_id', $appId)->whereNotIn('id', $members)->update(['district_id' => null]);
        DB::table('schools')->whereIn('id', $members)->update(['district_id' => $appId]);
    }

    /** App district changed through the mobile admin API: mirror it into the web panel. US and UK only. */
    public function fromApp(int $appDistrictId): void
    {
        if (!$this->ready()) { return; }
        $a = DB::table('districts')->find($appDistrictId);
        $market = $a ? $this->marketFor((int) $a->country_id) : null;
        if (!$market) { return; }
        $values = ['name' => $a->name, 'market' => $market, 'status' => $a->status === 'active' ? 'active' : 'suspended', 'app_district_id' => $a->id, 'updated_at' => now()];
        $id = DB::table('workspace_districts')->where('app_district_id', $a->id)->value('id');
        $id ??= DB::table('workspace_districts')->where('name', $a->name)->whereNull('app_district_id')->value('id');
        if ($id) { DB::table('workspace_districts')->where('id', $id)->update($values); }
        else { $id = DB::table('workspace_districts')->insertGetId($values + ['created_at' => now()]); }
        $schools = DB::table('schools')->where('district_id', $a->id)->pluck('id');
        DB::table('workspace_district_schools')->where('district_id', $id)->whereNotIn('school_id', $schools)->delete();
        foreach ($schools as $school) {
            DB::table('workspace_district_schools')->where('school_id', $school)->where('district_id', '!=', $id)->delete();
            DB::table('workspace_district_schools')->insertOrIgnore(['district_id' => $id, 'school_id' => $school]);
        }
    }
}
