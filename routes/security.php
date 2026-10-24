<?php
use App\Http\Controllers\Security\SecurityController;
use Illuminate\Support\Facades\Route;
Route::middleware('auth:web')->group(function () {
    Route::get('/security', [SecurityController::class, 'index'])->name('security.index');
    Route::post('/security/skip', [SecurityController::class, 'skip'])->middleware('throttle:10,1')->name('security.skip');
    Route::post('/security/setup', [SecurityController::class, 'begin'])->middleware('throttle:5,1')->name('security.begin');
    Route::post('/security/confirm', [SecurityController::class, 'confirm'])->middleware('throttle:5,1')->name('security.confirm');
    Route::post('/security/challenge', [SecurityController::class, 'challenge'])->middleware('throttle:5,1')->name('security.challenge');
    Route::post('/security/sessions/{id}/revoke', [SecurityController::class, 'revoke'])->middleware('throttle:5,1')->name('security.revoke');
    Route::post('/security/sessions/revoke-others', [SecurityController::class, 'revokeOthers'])->middleware('throttle:5,1')->name('security.revoke-others');
});
