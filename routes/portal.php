<?php

use App\Http\Controllers\ApprovalVerificationController;
use App\Http\Controllers\PortalController;
use App\Http\Middleware\EnsureWebsiteOperating;
use Illuminate\Support\Facades\Route;

Route::controller(PortalController::class)->middleware(EnsureWebsiteOperating::class)->group(function () {
    Route::get('/', 'index')->name('portal.dashboard');
    Route::get('/help', 'help')->name('portal.help');
});

Route::match(['get', 'post'], '/track', [PortalController::class, 'track'])
    ->name('portal.track')
    ->middleware('throttle:30,1');

Route::get('/verify/approval/{documentId}', ApprovalVerificationController::class)
    ->where('documentId', '[0-9a-fA-F-]{36}')
    ->middleware('throttle:60,1')
    ->name('permit-approvals.verify');
