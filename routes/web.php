<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminLoadingController;
use App\Http\Controllers\AdminMailSettingController;
use App\Http\Controllers\AdminNotificationController;
use App\Http\Controllers\AdminOperatingScheduleController;
use App\Http\Controllers\AdminValidatorController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\LoadingPermitController;
use App\Http\Controllers\PermitController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ScannerController;
use App\Http\Controllers\StaffNotificationFeedController;
use App\Http\Controllers\TRController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureTRValidator;
use App\Http\Middleware\EnsureWebsiteOperating;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — Sistem Informasi Surat Izin Mal Bali Galeria
|--------------------------------------------------------------------------
*/

// ─── Autentikasi ───────────────────────────────────────────────────────────
Route::controller(AuthController::class)->group(function () {
    Route::get('/login', 'showLogin')->name('login');
    Route::post('/login', 'login')->name('login.post');
    Route::middleware(EnsureWebsiteOperating::class)->group(function () {
        Route::get('/register', 'showRegister')->name('register');
        Route::post('/register', 'register')->name('register.post');
    });
    Route::post('/logout', 'logout')->middleware('auth')->name('logout');
});

// ─── Portal Beranda ────────────────────────────────────────────────────────
Route::controller(PortalController::class)->middleware(EnsureWebsiteOperating::class)->group(function () {
    Route::get('/', 'index')->name('portal.dashboard');
    Route::get('/help', 'help')->name('portal.help');
    Route::match(['get', 'post'], '/track', 'track')->name('portal.track')->middleware('throttle:30,1');
});

// ─── Permit Legacy (generic form — work, exhibition, event) ───────────────────
Route::controller(PermitController::class)->prefix('permits')->name('permits.')->middleware(EnsureWebsiteOperating::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/store', 'store')->name('store');
    Route::get('/success', 'success')->name('success');
});

// ─── Permohonan Loading In/Out ─────────────────────────────────────────────
Route::prefix('loading')->name('loading.')->middleware(EnsureWebsiteOperating::class)->group(function () {
    // Public: track status via nomor surat
    Route::post('/track', [LoadingPermitController::class, 'track'])->name('track');
    Route::get('/track/{permitNumber}', [LoadingPermitController::class, 'show'])->name('show')->where('permitNumber', '.*');

    // Formulir (public — guest boleh isi, login untuk auto-fill)
    Route::get('/create', [LoadingPermitController::class, 'create'])->name('create');
    Route::post('/store', [LoadingPermitController::class, 'store'])->name('store');
    Route::get('/success/{permitNumber}', [LoadingPermitController::class, 'success'])
        ->middleware('signed')
        ->name('success')
        ->where('permitNumber', '.*');

    // Surat resmi yang sudah approved
    Route::get('/letter/{permitNumber}', [LoadingPermitController::class, 'downloadLetter'])->name('letter')->where('permitNumber', '.*');
});

// ─── Administrator ────────────────────────────────────────────────────────
Route::prefix('admin')->name('admin.')->middleware(['auth', EnsureAdmin::class])->group(function () {
    Route::get('/', AdminDashboardController::class)->name('dashboard');

    Route::get('/loading', [AdminLoadingController::class, 'index'])->name('loading.index');
    Route::get('/loading/{permitNumber}', [AdminLoadingController::class, 'show'])
        ->where('permitNumber', '.*')
        ->name('loading.show');

    Route::get('/validators', [AdminValidatorController::class, 'index'])->name('validators.index');
    Route::get('/validators/create', [AdminValidatorController::class, 'create'])->name('validators.create');
    Route::post('/validators', [AdminValidatorController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('validators.store');
    Route::post('/validators/check-availability', [AdminValidatorController::class, 'checkAvailability'])
        ->middleware('throttle:60,1')
        ->name('validators.availability');
    Route::get('/validators/{validator}/edit', [AdminValidatorController::class, 'edit'])->name('validators.edit');
    Route::put('/validators/{validator}', [AdminValidatorController::class, 'update'])
        ->middleware('throttle:20,1')
        ->name('validators.update');
    Route::post('/validators/{validator}/check-availability', [AdminValidatorController::class, 'checkAvailability'])
        ->middleware('throttle:60,1')
        ->name('validators.availability.edit');
    Route::patch('/validators/{validator}/status', [AdminValidatorController::class, 'updateStatus'])
        ->middleware('throttle:20,1')
        ->name('validators.status');

    Route::get('/operating-hours', [AdminOperatingScheduleController::class, 'edit'])->name('schedule.edit');
    Route::put('/operating-hours', [AdminOperatingScheduleController::class, 'update'])
        ->middleware('throttle:10,1')
        ->name('schedule.update');

    Route::get('/settings/email', [AdminMailSettingController::class, 'edit'])->name('settings.mail.edit');
    Route::put('/settings/email', [AdminMailSettingController::class, 'update'])
        ->middleware('throttle:5,1')
        ->name('settings.mail.update');

    Route::post('/notifications/{notification}/read', [AdminNotificationController::class, 'read'])
        ->name('notifications.read');
    Route::get('/notification-feed', StaffNotificationFeedController::class)
        ->middleware('throttle:30,1')
        ->name('notifications.feed');
    Route::middleware('throttle:20,1')->group(function () {
        Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
        Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');
    });
});

// ─── Divisi TR (Tenant Relationship) — Validator ──────────────────────────
Route::prefix('tr')->name('tr.')->middleware(['auth', EnsureTRValidator::class])->group(function () {
    Route::get('/', [TRController::class, 'index'])->name('index');
    Route::middleware('throttle:20,1')->group(function () {
        Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
        Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');
    });
    Route::post('/notifications/{notification}/read', [TRController::class, 'readNotification'])->name('notifications.read');
    Route::get('/notification-feed', StaffNotificationFeedController::class)
        ->middleware('throttle:30,1')
        ->name('notifications.feed');
    Route::get('/documents/{documentToken}', [TRController::class, 'viewIdDoc'])
        ->middleware('signed')
        ->where('documentToken', '[A-Za-z0-9]{64}')
        ->name('id-doc');
    Route::post('/{permitNumber}/approve', [TRController::class, 'approve'])->name('approve')->where('permitNumber', '.*');
    Route::post('/{permitNumber}/reject', [TRController::class, 'reject'])->name('reject')->where('permitNumber', '.*');
    Route::get('/{permitNumber}', [TRController::class, 'show'])->name('show')->where('permitNumber', '.*');
});

// ─── Scanner QR (Publik, Mobile) ───────────────────────────────────────────
Route::get('/scanner', [ScannerController::class, 'index'])->name('scanner.index');
Route::get('/scanner/verify', [ScannerController::class, 'verify'])->name('scanner.verify');
