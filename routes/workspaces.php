<?php

use App\Http\Controllers\Workspace\DashboardController;
use Illuminate\Support\Facades\Route;

// Four administrative contexts share scoped workspace routes. Mobile-only roles
// cannot use this surface; staff require an explicit web.access capability.
Route::middleware(['auth:web', \App\Http\Middleware\AdministrativeWorkspace::class])->group(function () {
    Route::post('/workspace/files', [\App\Http\Controllers\Workspace\FilesController::class, 'upload'])->middleware('throttle:10,1')->name('workspace.file-upload');
    Route::get('/workspace/files/{id}/download', [\App\Http\Controllers\Workspace\FilesController::class, 'download'])->middleware('signed')->name('workspace.file-download');
    Route::get('/workspace/notifications', [\App\Http\Controllers\Workspace\NotificationsController::class, 'index'])->name('workspace.notifications');
    Route::post('/workspace/notifications/{id}/read', [\App\Http\Controllers\Workspace\NotificationsController::class, 'read'])->name('workspace.notification-read');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('workspace.home');
    // Organizations: each section is its own page (QA D19).
    Route::controller(\App\Http\Controllers\Workspace\AdministrationController::class)->group(function () {
        Route::get('/dashboard/administration', 'index')->name('workspace.administration');
        Route::get('/dashboard/schools', 'schools')->name('workspace.schools');
        Route::get('/dashboard/schools/{id}', 'school')->whereNumber('id')->name('workspace.school');
        Route::post('/workspace/schools', 'createSchool')->name('workspace.school-create');
        Route::post('/workspace/school-profile', 'schoolProfile')->name('workspace.school-profile');
        Route::post('/workspace/schools/{id}/status', 'schoolStatus')->whereNumber('id')->name('workspace.school-status');
        Route::get('/workspace/reports/schools.csv', 'export')->name('workspace.export');
        Route::get('/dashboard/districts', 'districts')->name('workspace.districts');
        Route::get('/dashboard/districts/{id}', 'district')->whereNumber('id')->name('workspace.district');
        Route::post('/workspace/districts', 'createDistrict')->name('workspace.district-create');
        Route::post('/workspace/districts/{id}', 'updateDistrict')->whereNumber('id')->name('workspace.district-update');
        Route::post('/workspace/districts/{id}/status', 'districtStatus')->whereNumber('id')->name('workspace.district-status');
        Route::post('/workspace/districts/{id}/schools', 'districtSchool')->whereNumber('id')->name('workspace.district-school');
        Route::get('/dashboard/administrators', 'administrators')->name('workspace.administrators');
        Route::post('/workspace/school-admin', 'assignAdmin')->name('workspace.school-admin');
        Route::post('/workspace/district-admin', 'assignDistrictAdmin')->name('workspace.district-admin');
        Route::post('/workspace/administrators/{id}/revoke', 'revokeAdmin')->whereNumber('id')->name('workspace.admin-revoke');
        Route::get('/dashboard/sub-super-admins', 'subSuperAdmins')->name('workspace.sub-super-admins');
        Route::post('/workspace/delegations', 'delegate')->name('workspace.delegate');
        Route::post('/workspace/delegations/{id}', 'updateDelegation')->whereNumber('id')->name('workspace.delegation-update');
        Route::post('/workspace/delegations/{id}/revoke', 'revoke')->name('workspace.delegation-revoke');
        Route::get('/dashboard/ad-free', 'adFree')->name('workspace.ad-free-requests');
        Route::get('/dashboard/role-requests', 'roleRequests')->name('workspace.role-requests');
        Route::post('/workspace/ad-free', 'requestAdFree')->name('workspace.ad-free');
        Route::post('/workspace/ad-free/{id}/review', 'decideAdFree')->name('workspace.ad-free-review');
    });
    // People directories (QA D13).
    Route::controller(\App\Http\Controllers\Workspace\DirectoryController::class)->group(function () {
        Route::get('/dashboard/parents', 'index')->defaults('role', 'parent')->name('workspace.parents');
        Route::get('/dashboard/teachers', 'index')->defaults('role', 'teacher')->name('workspace.teachers');
        Route::get('/dashboard/staff', 'index')->defaults('role', 'staff')->name('workspace.staff');
        Route::get('/dashboard/members/{id}', 'show')->whereNumber('id')->name('workspace.member');
        Route::post('/workspace/members/{id}/account', 'account')->whereNumber('id')->name('workspace.member-account');
        Route::post('/workspace/members/{id}/password-reset', 'passwordReset')->whereNumber('id')->middleware('throttle:6,1')->name('workspace.member-reset');
        Route::post('/workspace/memberships/{id}/restore', 'restore')->whereNumber('id')->name('workspace.restore');
        Route::get('/dashboard/students', 'students')->name('workspace.students');
    });
    // Platform configuration, integrations, billing and imports (QA B5, B6, D7-D11).
    Route::controller(\App\Http\Controllers\Workspace\PlatformController::class)->group(function () {
        Route::get('/dashboard/settings', 'settings')->name('workspace.settings');
        Route::post('/workspace/settings', 'saveSettings')->name('workspace.settings-save');
        Route::post('/workspace/markets/{code}', 'saveMarket')->where('code', 'US|UK')->name('workspace.market-save');
        Route::get('/dashboard/integrations', 'integrations')->name('workspace.integrations');
        Route::post('/workspace/integrations/ads', 'saveAds')->name('workspace.ads-save');
        Route::post('/workspace/billing/plans/{id}', 'planStatus')->name('workspace.plan-status');
        Route::post('/workspace/billing/minutes', 'adjustMinutes')->name('workspace.minutes-adjust');
        Route::get('/dashboard/billing/subscriptions/{id}', 'subscription')->name('workspace.subscription');
        Route::post('/workspace/billing/subscriptions/{id}/cancel', 'cancelSubscription')->name('workspace.subscription-cancel');
        Route::post('/workspace/billing/subscriptions/{id}/refund', 'refund')->middleware('throttle:10,1')->name('workspace.subscription-refund');
    });
    Route::controller(\App\Http\Controllers\Workspace\ImportsController::class)->group(function () {
        Route::get('/dashboard/imports', 'index')->name('workspace.imports');
        Route::get('/workspace/imports/template.csv', 'template')->name('workspace.import-template');
        Route::post('/workspace/imports', 'upload')->middleware('throttle:10,1')->name('workspace.import-upload');
        Route::post('/workspace/imports/{id}/confirm', 'confirm')->whereNumber('id')->name('workspace.import-confirm');
        Route::post('/workspace/imports/{id}/discard', 'discard')->whereNumber('id')->name('workspace.import-discard');
    });
    Route::get('/dashboard/analytics', [\App\Http\Controllers\Workspace\InsightsController::class, 'analytics'])->name('workspace.analytics');
    Route::get('/dashboard/reports', [\App\Http\Controllers\Workspace\InsightsController::class, 'reports'])->name('workspace.reports');
    Route::post('/workspace/reports/exports', [\App\Http\Controllers\Workspace\InsightsController::class, 'requestExport'])->middleware('throttle:20,1')->name('workspace.report-export');
    Route::get('/workspace/reports/exports/{id}', [\App\Http\Controllers\Workspace\InsightsController::class, 'exportFile'])->middleware('throttle:30,1')->name('workspace.report-file');
    Route::get('/dashboard/video', [\App\Http\Controllers\Workspace\InsightsController::class, 'video'])->name('workspace.video');
    Route::get('/dashboard/audit', [\App\Http\Controllers\Workspace\InsightsController::class, 'audit'])->name('workspace.audit');
    Route::get('/dashboard/billing', [\App\Http\Controllers\Workspace\InsightsController::class, 'billing'])->name('workspace.billing');
    Route::get('/dashboard/support', [\App\Http\Controllers\Workspace\SupportController::class, 'index'])->name('workspace.support');
    Route::get('/dashboard/support/{id}', [\App\Http\Controllers\Workspace\SupportController::class, 'show'])->whereNumber('id')->name('workspace.support-ticket');
    Route::post('/workspace/support', [\App\Http\Controllers\Workspace\SupportController::class, 'store'])->middleware('throttle:10,1')->name('workspace.support-store');
    Route::post('/workspace/support/{id}/replies', [\App\Http\Controllers\Workspace\SupportController::class, 'reply'])->middleware('throttle:30,1')->name('workspace.support-reply');
    Route::post('/workspace/support/{id}/status', [\App\Http\Controllers\Workspace\SupportController::class, 'status'])->name('workspace.support-status');
    Route::get('/dashboard/{page}', [DashboardController::class, 'index'])->name('workspace.page');
    Route::post('/workspace/select', [DashboardController::class, 'select'])->name('workspace.select');
    Route::post('/workspace/classes', [DashboardController::class, 'classSection'])->name('workspace.classes');
    Route::post('/workspace/posts', [DashboardController::class, 'post'])->name('workspace.posts');
    Route::get('/workspace/posts/recipients', [DashboardController::class, 'recipients'])->middleware('throttle:60,1')->name('workspace.post-recipients');
    Route::post('/workspace/updates', [DashboardController::class, 'update'])->name('workspace.updates');
    Route::post('/workspace/messages', [DashboardController::class, 'message'])->name('workspace.messages');
    Route::post('/workspace/messages/{id}/read', [DashboardController::class, 'read'])->name('workspace.read');
    Route::post('/workspace/appointments', [DashboardController::class, 'appointment'])->name('workspace.appointments');
    Route::post('/workspace/appointments/{id}/status', [DashboardController::class, 'appointmentStatus'])->name('workspace.appointment-status');
    Route::post('/workspace/memberships', [DashboardController::class, 'membership'])->name('workspace.memberships');
    Route::post('/workspace/memberships/{id}/revoke', [DashboardController::class, 'revoke'])->name('workspace.revoke');
    Route::post('/workspace/guardians', [DashboardController::class, 'guardian'])->name('workspace.guardians');
    Route::post('/workspace/guardians/{id}/status', [DashboardController::class, 'guardianStatus'])->name('workspace.guardian-status');
    Route::post('/workspace/profile', [DashboardController::class, 'profile'])->name('workspace.profile');
});

Route::get('/invitations/{token}', [\App\Http\Controllers\Workspace\InvitationsController::class, 'show'])->middleware(['signed','throttle:20,1'])->name('invitation.show');
Route::post('/invitations/{token}', [\App\Http\Controllers\Workspace\InvitationsController::class, 'accept'])->middleware(['signed','throttle:5,1'])->name('invitation.accept');
Route::post('/workspace/invitations/{id}/revoke', [\App\Http\Controllers\Workspace\InvitationsController::class, 'revoke'])->whereNumber('id')->middleware(['auth:web', \App\Http\Middleware\AdministrativeWorkspace::class])->name('workspace.invitation-revoke');
Route::post('/workspace/invitations', [\App\Http\Controllers\Workspace\InvitationsController::class, 'create'])->middleware(['auth:web', \App\Http\Middleware\AdministrativeWorkspace::class, 'throttle:10,1'])->name('workspace.invite');

Route::post('/workspace/logout', [DashboardController::class, 'logout'])->middleware('auth:web')->name('workspace.logout');
