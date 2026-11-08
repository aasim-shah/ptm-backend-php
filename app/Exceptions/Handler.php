<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Str;
class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'code',
        'token',
        'pincode',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
        // A rejected admin form returns to the page with a readable message instead of a bare error page.
        $this->renderable(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, $request) {
            if ($request->expectsJson() || $request->isMethod('GET') || !in_array($e->getStatusCode(), [409, 422], true)) { return null; }
            if (!Str::startsWith($request->path(), ['workspace/', 'security'])) { return null; }
            return back()->withInput($request->except($this->dontFlash))->withErrors(['action' => $e->getMessage() ?: 'This change could not be saved.']);
        });
    }
    protected function unauthenticated($request, AuthenticationException $exception)
    {

        if (Str::startsWith($request->getRequestUri(), '/api')) {
            return response()->json(['status' => 400, 'error' => 'Unauthenticated.', 'message' => 'Unauthenticated.'], 401);
        }


        return redirect()->guest(route('login'));

    }
}
