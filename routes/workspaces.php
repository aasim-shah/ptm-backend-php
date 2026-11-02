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
    Route::get('/dashboard/administration', [\App\Http\Controllers\Workspace\AdministrationController::class, 'index'])->name('workspace.administration');
    Route::post('/workspace/school-profile', [\App\Http\Controllers\Workspace\AdministrationController::class, 'schoolProfile'])->name('workspace.school-profile');
    Route::post('/workspace/school-admin', [\App\Http\Controllers\Workspace\AdministrationController::class, 'assignAdmin'])->name('workspace.school-admin');
    Route::get('/workspace/reports/schools.csv', [\App\Http\Controllers\Workspace\AdministrationController::class, 'export'])->name('workspace.export');
    Route::post('/workspace/delegations', [\App\Http\Controllers\Workspace\AdministrationController::class, 'delegate'])->name('workspace.delegate');
    Route::post('/workspace/delegations/{id}/revoke', [\App\Http\Controllers\Workspace\AdministrationController::class, 'revoke'])->name('workspace.delegation-revoke');
    Route::post('/workspace/ad-free', [\App\Http\Controllers\Workspace\AdministrationController::class, 'requestAdFree'])->name('workspace.ad-free');
    Route::post('/workspace/ad-free/{id}/review', [\App\Http\Controllers\Workspace\AdministrationController::class, 'decideAdFree'])->name('workspace.ad-free-review');
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
Route::post('/workspace/invitations', [\App\Http\Controllers\Workspace\InvitationsController::class, 'create'])->middleware(['auth:web', \App\Http\Middleware\AdministrativeWorkspace::class, 'throttle:10,1'])->name('workspace.invite');

Route::post('/workspace/logout', [DashboardController::class, 'logout'])->middleware('auth:web')->name('workspace.logout');
