<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserSchools;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Meeting start checks. The app sends meeting_date (Y-m-d) and meeting_time as wall-clock
 * local time ("14:30" or "2:30 PM", sometimes with a narrow no-break space) and no timezone,
 * so the comparison has to happen in the caller's timezone, resolved in this order:
 *   1. X-Timezone header or `timezone` input (IANA name, e.g. Asia/Karachi)
 *   2. `timezone_offset` input, then users.timezone_offset (seconds east of UTC)
 *   3. the user's school timezone, then its market (UK: Europe/London, US: America/Los_Angeles,
 *      the westernmost mainland zone so a valid US meeting is never rejected)
 *   4. the app timezone
 */
class MeetingTime
{
    public const GRACE_MINUTES = 5;
    public const PAST_MESSAGE = 'Meeting start time must be in the future';

    public static function normalize($time): string
    {
        $time = trim(str_replace(["\u{202F}", "\u{00A0}"], ' ', (string) $time));
        // A full date-time ("2026-11-08 00:05:00") carries its own date; keep only
        // the time so it is combined with meeting_date instead of failing to parse.
        if (preg_match('/(\d{1,2}:\d{2}(?::\d{2})?\s*(?:[AaPp][Mm])?)$/', $time, $m) && strlen($m[1]) < strlen($time)) {
            return trim($m[1]);
        }
        return $time;
    }

    /** True when meeting_date + meeting_time is more than 5 minutes ago in the caller's timezone. */
    public static function startIsPast($date, $time, ?User $user, ?Request $request = null): bool
    {
        if (!$date || !$time) {
            return false;
        }
        $timezone = self::timezoneFor($user, $request);
        try {
            $day = Carbon::parse((string) $date)->toDateString();
            $start = Carbon::parse($day . ' ' . self::normalize($time), $timezone);
        } catch (\Throwable $e) {
            return false; // Unparseable input is left to the normal validation.
        }
        return $start->lt(Carbon::now($timezone)->subMinutes(self::GRACE_MINUTES));
    }

    public static function timezoneFor(?User $user, ?Request $request = null): string
    {
        $named = $request ? ($request->header('X-Timezone') ?: $request->input('timezone')) : null;
        if (is_string($named) && in_array($named, timezone_identifiers_list(), true)) {
            return $named;
        }
        // The app sends X-Timezone-Offset (seconds east of UTC) on every request.
        foreach ([$request?->input('timezone_offset'), $request?->header('X-Timezone-Offset'), $user?->timezone_offset] as $offset) {
            if (is_numeric($offset) && abs((int) $offset) <= 14 * 3600) {
                $minutes = intdiv((int) $offset, 60);
                return sprintf('%s%02d:%02d', $minutes < 0 ? '-' : '+', intdiv(abs($minutes), 60), abs($minutes) % 60);
            }
        }
        if ($user) {
            $school = UserSchools::where('user_id', $user->id)->with('school')->first()?->school;
            if ($school && $school->timezone && in_array($school->timezone, timezone_identifiers_list(), true)) {
                return $school->timezone;
            }
            $market = strtoupper((string) ($school->market ?? ''));
            if ($market === 'UK') {
                return 'Europe/London';
            }
            if ($market === 'US') {
                return 'America/Los_Angeles';
            }
        }
        return config('app.timezone', 'UTC');
    }
}
