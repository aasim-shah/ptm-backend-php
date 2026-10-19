<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Smooths over request quirks the current mobile build sends (API_CONTRACT §15, Part B3).
 *
 * - Query parameters sent as the strings "null" / "undefined" are treated as absent
 *   (subject_id=null on view-attendance, page=null on class reports count, ...).
 * - "fcm id" (with a space, JSON bodies) is copied to fcm_id.
 * - POST /add/school: webSite -> website, email "null" -> empty.
 */
class NormalizeAppRequest
{
    public function handle(Request $request, Closure $next)
    {
        foreach ($request->query->all() as $key => $value) {
            if (is_string($value) && in_array(strtolower($value), ['null', 'undefined'], true)) {
                $request->query->remove($key);
            }
        }

        $input = $request->all();
        $changes = [];
        if (array_key_exists('fcm id', $input) && !$request->filled('fcm_id')) {
            $changes['fcm_id'] = $input['fcm id'];
        }
        if ($request->is('api/add/school')) {
            if (array_key_exists('webSite', $input) && !$request->filled('website')) {
                $changes['website'] = $input['webSite'];
            }
            if (is_string($input['email'] ?? null) && strtolower($input['email']) === 'null') {
                $changes['email'] = null;
            }
        }
        if ($changes) {
            $request->merge($changes);
        }

        return $next($request);
    }
}
