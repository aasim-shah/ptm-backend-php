<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-wide settings that a Super Admin manages from the web panel. Each
 * key has a typed default, so the platform behaves the same before anyone
 * saves a value. Secrets never live here; they stay in the server environment.
 */
class PlatformSettings
{
    /** key => [default, validation rule, label, help] */
    public const DEFINITIONS = [
        'trial.minutes' => [60, 'required|integer|min:0|max:6000', 'Trial video minutes', 'Video minutes a new account receives with its free trial.'],
        'trial.days' => [5, 'required|integer|min:0|max:365', 'Trial length (days)', 'How long the free trial lasts.'],
        'trial.call_count' => [5, 'required|integer|min:0|max:1000', 'Trial call limit', 'Calls allowed during the trial.'],
        'video.rounding' => ['minute_up', 'required|in:second,minute_up', 'Video minute rounding', 'How call time is counted against a plan.'],
        'ads.enabled' => [true, 'required|boolean', 'Show ads in the mobile app', 'Global kill switch for AdMob. Off hides ads for everyone.'],
        'ads.markets' => [['US' => true, 'UK' => true], 'array', 'Ads by market', 'Ads can be switched off for one market.'],
        'policy.terms_version' => ['2026-11', 'required|string|max:20', 'Terms of service version', 'Raise this to ask every user to accept the new terms.'],
        'policy.privacy_version' => ['2026-11', 'required|string|max:20', 'Privacy policy version', 'Raise this to ask every user to acknowledge the new policy.'],
        'policy.consent_required' => [true, 'required|boolean', 'Require consent at sign-up', 'Users must accept the terms and privacy policy to create an account.'],
        'retention.audit_days' => [730, 'required|integer|min:90|max:3650', 'Keep the activity log (days)', 'Activity log entries older than this are deleted each night.'],
    ];

    /** Until a Super Admin saves a value, these keys keep following the server environment. */
    private const ENVIRONMENT = ['trial.minutes' => 'environment.TRAIL', 'trial.days' => 'environment.TRAIL_DAYS', 'trial.call_count' => 'environment.TRAIL_CALL_COUNT'];

    private ?array $cache = null;

    public function get(string $key, $default = null)
    {
        $values = $this->stored();
        if (array_key_exists($key, $values)) { return $values[$key]; }
        if (isset(self::ENVIRONMENT[$key]) && config(self::ENVIRONMENT[$key]) !== null) { return (int) config(self::ENVIRONMENT[$key]); }
        return $default ?? (self::DEFINITIONS[$key][0] ?? null);
    }

    public function all(): array
    {
        $values = $this->stored();
        $out = [];
        foreach (array_keys(self::DEFINITIONS) as $key) { $out[$key] = $this->get($key); }
        return $out;
    }

    public function set(string $key, $value, ?int $userId): void
    {
        abort_unless(isset(self::DEFINITIONS[$key]), 422, 'Unknown setting.');
        DB::table('workspace_settings')->updateOrInsert(['key' => $key],
            ['value' => json_encode($value), 'updated_by' => $userId, 'created_at' => now(), 'updated_at' => now()]);
        $this->cache = null;
    }

    /** Whether AdMob ads may show for a market (US or UK); the global switch wins. */
    public function adsEnabled(?string $market = null): bool
    {
        if (!$this->get('ads.enabled')) { return false; }
        $markets = (array) $this->get('ads.markets');
        return $market === null || ($markets[strtoupper($market)] ?? true);
    }

    /**
     * Whether a signed-in app user should see ads: the global and market switches must be on,
     * and none of the user's schools (or their districts) may hold an approved ad-free request.
     */
    public function adsEnabledFor(?\App\Models\User $user, ?string $market = null): bool
    {
        if (!$user) { return $this->adsEnabled($market); }
        $schools = DB::table('workspace_memberships')->where('user_id', $user->id)->where('status', 'active')->whereNotNull('school_id')->pluck('school_id');
        $market ??= DB::table('schools')->whereIn('id', $schools)->whereNotNull('market')->value('market');
        if (!$this->adsEnabled($market)) { return false; }
        if ($schools->isEmpty()) { return true; }
        $districts = DB::table('workspace_district_schools')->whereIn('school_id', $schools)->pluck('district_id');
        return !DB::table('workspace_ad_free_requests')->where('status', 'approved')
            ->where(fn ($q) => $q->whereIn('school_id', $schools)->orWhereIn('district_id', $districts))->exists();
    }

    private function stored(): array
    {
        if ($this->cache !== null) { return $this->cache; }
        if (!Schema::hasTable('workspace_settings')) { return $this->cache = []; }
        return $this->cache = DB::table('workspace_settings')->pluck('value', 'key')
            ->map(fn ($v) => json_decode($v, true))->all();
    }
}
