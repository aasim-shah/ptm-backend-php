<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DemoMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(Request $request, Closure $next) {
//        echo $request->getRequestUri();
        $exclude_uri = array(
            '/login',
            '/api/student/login',
            '/api/parent/login',
            '/api/teacher/login'
        );
//        dd($request->getRequestUri());
        if (config('environment.DEMO_MODE')) {
            if (!$request->isMethod('get') && !in_array($request->getRequestUri(), $exclude_uri)) {
                return response()->json(array(
                    'error' => true,
                    'message' => "This is not allowed in the Demo Version.",
                    'code' => 112
                ));
            }

            // For Demo
            // if (config('environment.DEMO_MODE') && Auth::user() && Auth::user()->email !== "demomodeoff@gmail.com" && !$request->isMethod('get') && !$request->is($exclude_uri)) {
            //     if($request->ajax()){
            //         return response()->json(array(
            //             'error' => true,
            //             'message' => "This is not allowed in the Demo Version.",
            //             'code' => 112
            //         ));
            //     }else{
            //         return redirect()->back()->withErrors(array(
            //             'message' => "This is not allowed in the Demo Version.",
            //         ));
            //     }
            // }
        }
        return $next($request);
    }
}
