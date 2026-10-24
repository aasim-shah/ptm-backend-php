<?php

namespace App\Http;

use App\Http\Middleware\DemoMiddleware;
use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
    * The application's global HTTP middleware stack.
    *
    * These middleware are run during every request to your application.
    *
    * @var array<int, class-string|string>
    */
    protected $middleware = [
        \App\Http\Middleware\ResponseSecurity::class,
        // \App\Http\Middleware\TrustHosts::class,
        \App\Http\Middleware\TrustProxies::class,
        \Illuminate\Http\Middleware\HandleCors::class,
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        DemoMiddleware::class,
        \App\Http\Middleware\HandleSanctumUnauthenticated::class,
        // \App\Http\Middleware\LanguageManager::class,
    ];

    /**
    * The application's route middleware groups.
    *
    * @var array<string, array<int, class-string|string>>
    */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\EnforceAccountSecurity::class,
        ],

        'api' => [
             \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            'handle.sanctum.unauthenticated.api', // Use the custom middleware instead of EnsureFrontendRequestsAreStateful
            'throttle:api',
            \App\Http\Middleware\NormalizeAppRequest::class,
            \App\Http\Middleware\ResolvePtmContext::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\EnforceAccountSecurity::class,
            // \App\Http\Middleware\CheckChild::class,
        ],
    ];

    /**
    * The application's route middleware.
    *
    * These middleware may be assigned to groups or used individually.
    *
    * @var array<string, class-string|string>
    */
    protected $routeMiddleware = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
        'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        'Role'=>\App\Http\Middleware\CheckRole::class,
        'checkChild'=>\App\Http\Middleware\CheckChild::class,
        'language' => \App\Http\Middleware\LanguageManager::class,
        'checkStudent'=>\App\Http\Middleware\CheckStudent::class,
        'userType' => \App\Http\Middleware\EnsureUserType::class,
        'ptm.role' => \App\Http\Middleware\RequireContextRole::class,
        'ptm.can' => \App\Http\Middleware\RequireContextPermission::class,
        'systemUpdate' => \App\Http\Middleware\EnsureSystemUpdateEnabled::class,
        'handle.sanctum.unauthenticated.api' => \App\Http\Middleware\HandleSanctumUnauthenticatedApi::class,
    ];
}
