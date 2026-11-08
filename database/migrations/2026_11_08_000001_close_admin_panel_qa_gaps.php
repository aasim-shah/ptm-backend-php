<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema for the 3 November QA report: organization lifecycle (status, market,
 * timezone), USA/UK market configuration, platform settings, scheduled
 * communications and bulk imports.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $t) {
            if (!Schema::hasColumn('schools', 'status')) { $t->string('status', 16)->default('active'); }
            if (!Schema::hasColumn('schools', 'market')) { $t->string('market', 2)->nullable()->index(); }
            if (!Schema::hasColumn('schools', 'timezone')) { $t->string('timezone', 64)->nullable(); }
        });
        Schema::table('workspace_districts', function (Blueprint $t) {
            if (!Schema::hasColumn('workspace_districts', 'status')) { $t->string('status', 16)->default('active'); }
            if (!Schema::hasColumn('workspace_districts', 'timezone')) { $t->string('timezone', 64)->nullable(); }
        });
        Schema::table('workspace_posts', function (Blueprint $t) {
            if (!Schema::hasColumn('workspace_posts', 'publish_at')) { $t->timestamp('publish_at')->nullable()->index(); }
            if (!Schema::hasColumn('workspace_posts', 'notified_at')) { $t->timestamp('notified_at')->nullable(); }
        });

        Schema::create('workspace_markets', function (Blueprint $t) {
            $t->string('code', 2)->primary();
            $t->string('name', 80);
            $t->boolean('enabled')->default(true);
            $t->string('currency', 3);
            $t->string('currency_symbol', 4);
            $t->string('locale', 10);
            $t->string('timezone', 64);
            $t->string('date_format', 16);
            $t->string('phone_prefix', 6);
            $t->string('postal_label', 32);
            $t->string('region_label', 32);
            $t->json('terminology');
            $t->string('privacy_url')->nullable();
            $t->string('terms_url')->nullable();
            $t->string('support_email')->nullable();
            $t->unsignedSmallInteger('minimum_age')->default(13);
            $t->foreignId('updated_by')->nullable()->constrained('users');
            $t->timestamps();
        });
        $now = now();
        DB::table('workspace_markets')->insert([
            ['code' => 'US', 'name' => 'United States', 'enabled' => true, 'currency' => 'USD', 'currency_symbol' => '$', 'locale' => 'en_US',
                'timezone' => 'America/New_York', 'date_format' => 'm/d/Y', 'phone_prefix' => '+1', 'postal_label' => 'ZIP code', 'region_label' => 'State',
                'terminology' => json_encode(['grade' => 'Grade', 'school_admin' => 'Principal', 'district' => 'District', 'guardian' => 'Parent / Guardian', 'term' => 'Semester']),
                'privacy_url' => null, 'terms_url' => null, 'support_email' => null, 'minimum_age' => 13, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'UK', 'name' => 'United Kingdom', 'enabled' => true, 'currency' => 'GBP', 'currency_symbol' => '£', 'locale' => 'en_GB',
                'timezone' => 'Europe/London', 'date_format' => 'd/m/Y', 'phone_prefix' => '+44', 'postal_label' => 'Postcode', 'region_label' => 'County',
                'terminology' => json_encode(['grade' => 'Year', 'school_admin' => 'Headteacher', 'district' => 'Trust / Local authority', 'guardian' => 'Parent / Carer', 'term' => 'Term']),
                'privacy_url' => null, 'terms_url' => null, 'support_email' => null, 'minimum_age' => 13, 'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::create('workspace_settings', function (Blueprint $t) {
            $t->string('key', 80)->primary();
            $t->json('value');
            $t->foreignId('updated_by')->nullable()->constrained('users');
            $t->timestamps();
        });

        Schema::create('workspace_imports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users');
            $t->string('context', 80);
            $t->string('kind', 16);
            $t->string('status', 16)->default('preview');
            $t->json('rows');
            $t->json('summary')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'status']);
        });

        // One set of districts: link each web district to the mobile app's districts table.
        Schema::table('workspace_districts', function (Blueprint $t) {
            if (!Schema::hasColumn('workspace_districts', 'app_district_id')) { $t->unsignedBigInteger('app_district_id')->nullable()->unique(); }
        });
        if (Schema::hasTable('districts') && Schema::hasTable('countries')) {
            $sync = new \App\Services\DistrictSync();
            foreach (DB::table('workspace_districts')->orderBy('id')->get() as $d) {
                $match = DB::table('districts')->where('name', $d->name)->where('country_id', $sync->countryId($d->market))->value('id');
                if ($match) {
                    // Keep schools the app already placed in the matching district.
                    foreach (DB::table('schools')->where('district_id', $match)->pluck('id') as $school) {
                        DB::table('workspace_district_schools')->insertOrIgnore(['district_id' => $d->id, 'school_id' => $school]);
                    }
                }
                $sync->toApp($d->id);
            }
            foreach (DB::table('districts')->whereNotIn('id', DB::table('workspace_districts')->whereNotNull('app_district_id')->select('app_district_id'))->pluck('id') as $id) {
                $sync->fromApp((int) $id);
            }
        }

        // Markets for existing organizations: a district's market wins, then the school's country.
        DB::table('workspace_district_schools as ds')->join('workspace_districts as d', 'd.id', '=', 'ds.district_id')
            ->select('ds.school_id', 'd.market')->orderBy('ds.school_id')->get()
            ->each(fn ($row) => DB::table('schools')->where('id', $row->school_id)->whereNull('market')->update(['market' => strtoupper($row->market)]));
        if (!Schema::hasTable('countries')) { return; }
        $countries = DB::table('countries')->whereIn('name', ['United Kingdom', 'United States'])->pluck('id', 'name');
        if (isset($countries['United Kingdom'])) { DB::table('schools')->whereNull('market')->where('country_id', $countries['United Kingdom'])->update(['market' => 'UK']); }
        if (isset($countries['United States'])) { DB::table('schools')->whereNull('market')->where('country_id', $countries['United States'])->update(['market' => 'US']); }
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_imports');
        Schema::dropIfExists('workspace_settings');
        Schema::dropIfExists('workspace_markets');
        foreach (['workspace_posts' => ['publish_at', 'notified_at'], 'workspace_districts' => ['status', 'timezone', 'app_district_id'], 'schools' => ['market', 'timezone']] as $table => $columns) {
            $existing = array_values(array_filter($columns, fn ($c) => Schema::hasColumn($table, $c)));
            if ($existing) { Schema::table($table, fn (Blueprint $t) => $t->dropColumn($existing)); }
        }
    }
};
