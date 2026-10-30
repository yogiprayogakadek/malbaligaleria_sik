<?php

use App\Http\Controllers\AccountProfileController;
use App\Http\Controllers\AccountSecurityController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\InitialPasswordController;
use App\Http\Controllers\ResetPasswordController;
use App\Http\Controllers\TwoFactorChallengeController;
use App\Http\Controllers\TwoFactorSetupController;
use Illuminate\Support\Facades\Route;

Route::controller(AuthController::class)->group(function () {
    Route::get('/staff-access', 'showLogin')->name('login');
    Route::post('/staff-access', 'login')->name('login.post');
    Route::post('/logout', 'logout')->middleware('auth')->name('logout');
});

Route::middleware('guest')->group(function () {
    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('password.email');
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])
        ->middleware('throttle:20,1')
        ->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('password.update');
});

Route::middleware('guest')->prefix('two-factor')->name('two-factor.challenge.')->group(function () {
    Route::get('/challenge', [TwoFactorChallengeController::class, 'show'])->name('show');
    Route::post('/challenge', [TwoFactorChallengeController::class, 'verify'])
        ->middleware('throttle:6,1')
        ->name('verify');
});

Route::middleware('auth')->prefix('two-factor')->name('two-factor.setup.')->group(function () {
    Route::get('/setup', [TwoFactorSetupController::class, 'show'])->name('show');
    Route::post('/setup', [TwoFactorSetupController::class, 'confirm'])
        ->middleware('throttle:6,1')
        ->name('confirm');
    Route::post('/recovery-codes', [TwoFactorSetupController::class, 'regenerate'])
        ->middleware('throttle:3,1')
        ->name('recovery-codes.regenerate');
});

Route::middleware('auth')->prefix('password')->name('password.required.')->group(function () {
    Route::get('/required', [InitialPasswordController::class, 'edit'])->name('edit');
    Route::put('/required', [InitialPasswordController::class, 'update'])
        ->middleware('throttle:5,1')
        ->name('update');
});

Route::middleware('auth')->prefix('account/security')->name('account.security.')->group(function () {
    Route::get('/', [AccountSecurityController::class, 'index'])->name('index');
    Route::delete('/sessions/others', [AccountSecurityController::class, 'destroyOthers'])
        ->middleware('throttle:5,1')
        ->name('sessions.others.destroy');
    Route::delete('/sessions/{sessionId}', [AccountSecurityController::class, 'destroy'])
        ->middleware('throttle:10,1')
        ->where('sessionId', '[A-Za-z0-9_-]+')
        ->name('sessions.destroy');
});

Route::middleware('auth')->prefix('account/profile')->name('account.profile.')->group(function () {
    Route::get('/', [AccountProfileController::class, 'edit'])->name('edit');
    Route::put('/', [AccountProfileController::class, 'update'])->middleware('throttle:10,1')->name('update');
});
