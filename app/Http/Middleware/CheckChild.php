<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckChild
{
    public function handle(Request $request, Closure $next)
    {
        $parent = $request->user()?->parent;
        $child = $parent ? $parent->children()->where('id', $request->child_id)->first() : null;
        if (empty($child)) {
            return response()->json(array(
                'error' => true,
                'message' => "Invalid Child ID Passed.",
                'code' => 105,
            ), 403);
        }
        return $next($request);
    }
}
