<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminLoadingController;
use App\Http\Controllers\AdminMailSettingController;
use App\Http\Controllers\AdminNotificationController;
use App\Http\Controllers\AdminOperatingScheduleController;
use App\Http\Controllers\AdminScannerSettingController;
use App\Http\Controllers\AdminValidatorController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\InitialPasswordController;
use App\Http\Controllers\LoadingPermitController;
use App\Http\Controllers\MEPController;
use App\Http\Controllers\PermitController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ScannerController;
use App\Http\Controllers\SecretaryController;
use App\Http\Controllers\StaffNotificationController;
use App\Http\Controllers\StaffNotificationFeedController;
use App\Http\Controllers\StaffWorkPermitController;
use App\Http\Controllers\TRController;
use App\Http\Controllers\WorkPermitApplicantController;
use App\Http\Controllers\WorkPermitController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureFinanceValidator;
use App\Http\Middleware\EnsureMEPValidator;
use App\Http\Middleware\EnsureSecretary;
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

Route::middleware('auth')->prefix('password')->name('password.required.')->group(function () {
    Route::get('/required', [InitialPasswordController::class, 'edit'])->name('edit');
    Route::put('/required', [InitialPasswordController::class, 'update'])
        ->middleware('throttle:5,1')
        ->name('update');
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
    Route::get('/letter/{permitNumber}/preview', [LoadingPermitController::class, 'previewLetter'])->name('letter.preview')->where('permitNumber', '.*');
    Route::get('/letter/{permitNumber}/inline', [LoadingPermitController::class, 'inlineLetter'])->name('letter.inline')->where('permitNumber', '.*');
    Route::get('/letter/{permitNumber}', [LoadingPermitController::class, 'downloadLetter'])->name('letter')->where('permitNumber', '.*');
});

Route::prefix('work-permits')->name('work-permits.')->middleware(EnsureWebsiteOperating::class)->group(function () {
    Route::get('/create', [WorkPermitController::class, 'create'])->name('create');
    Route::post('/', [WorkPermitController::class, 'store'])->middleware('throttle:10,1')->name('store');
    Route::get('/success/{token}', [WorkPermitController::class, 'success'])
        ->middleware('signed')
        ->where('token', '[A-Za-z0-9]{64}')
        ->name('success');
    Route::get('/status/{token}', [WorkPermitApplicantController::class, 'show'])
        ->middleware('throttle:30,1')
        ->where('token', '[A-Za-z0-9]{64}')
        ->name('status');
    Route::get('/status/{token}/letter', [WorkPermitApplicantController::class, 'downloadLetter'])
        ->middleware('throttle:20,1')
        ->where('token', '[A-Za-z0-9]{64}')
        ->name('letter');
    Route::get('/status/{token}/letter/preview', [WorkPermitApplicantController::class, 'previewLetter'])
        ->middleware('throttle:30,1')
        ->where('token', '[A-Za-z0-9]{64}')
        ->name('letter.preview');
    Route::get('/status/{token}/letter/inline', [WorkPermitApplicantController::class, 'inlineLetter'])
        ->middleware('throttle:20,1')
        ->where('token', '[A-Za-z0-9]{64}')
        ->name('letter.inline');
    Route::post('/status/{token}/payment-proof', [WorkPermitApplicantController::class, 'uploadPaymentProof'])
        ->middleware('throttle:10,1')
        ->where('token', '[A-Za-z0-9]{64}')
        ->name('payment-proof.store');
    Route::get('/status/{token}/refund-proof', [WorkPermitApplicantController::class, 'refundProof'])
        ->middleware(['signed', 'throttle:30,1'])
        ->where('token', '[A-Za-z0-9]{64}')
        ->name('refund-proof');
    Route::get('/status/{token}/refund-proof/preview', [WorkPermitApplicantController::class, 'previewRefundProof'])
        ->middleware(['signed', 'throttle:30,1'])
        ->where('token', '[A-Za-z0-9]{64}')
        ->name('refund-proof.preview');
});

// ─── Administrator ────────────────────────────────────────────────────────
Route::prefix('admin')->name('admin.')->middleware(['auth', EnsureAdmin::class])->group(function () {
    Route::get('/', AdminDashboardController::class)->name('dashboard');

    Route::get('/loading', [AdminLoadingController::class, 'index'])->name('loading.index');
    Route::get('/loading-documents/{documentToken}', [AdminLoadingController::class, 'document'])
        ->middleware('signed')
        ->where('documentToken', '[A-Za-z0-9]{64}')
        ->name('loading.document');
    Route::get('/loading-documents/{documentToken}/preview', [AdminLoadingController::class, 'previewDocument'])
        ->middleware('signed')
        ->where('documentToken', '[A-Za-z0-9]{64}')
        ->name('loading.document.preview');
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
    Route::post('/settings/email/test', [AdminMailSettingController::class, 'test'])
        ->middleware('throttle:mail-tests')
        ->name('settings.mail.test');

    Route::get('/settings/scanner', [AdminScannerSettingController::class, 'edit'])
        ->name('settings.scanner.edit');
    Route::put('/settings/scanner', [AdminScannerSettingController::class, 'update'])
        ->middleware('throttle:10,1')
        ->name('settings.scanner.update');

    Route::post('/notifications/clear', [StaffNotificationController::class, 'clear'])
        ->name('notifications.clear');
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
    Route::post('/notifications/clear', [StaffNotificationController::class, 'clear'])->name('notifications.clear');
    Route::post('/notifications/{notification}/read', [TRController::class, 'readNotification'])->name('notifications.read');
    Route::get('/notification-feed', StaffNotificationFeedController::class)
        ->middleware('throttle:30,1')
        ->name('notifications.feed');
    Route::get('/documents/{documentToken}', [TRController::class, 'viewIdDoc'])
        ->middleware('signed')
        ->where('documentToken', '[A-Za-z0-9]{64}')
        ->name('id-doc');
    Route::get('/documents/{documentToken}/preview', [TRController::class, 'previewIdDoc'])
        ->middleware('signed')
        ->where('documentToken', '[A-Za-z0-9]{64}')
        ->name('id-doc.preview');
    Route::get('/work-permits', [TRController::class, 'workPermits'])->name('work-permits.index');
    Route::post('/work-permits/{token}/decision', [TRController::class, 'reviewWorkPermit'])
        ->where('token', '[A-Za-z0-9]{64}')
        ->name('work-permits.decision');
    Route::post('/{permitNumber}/approve', [TRController::class, 'approve'])->name('approve')->where('permitNumber', '.*');
    Route::post('/{permitNumber}/reject', [TRController::class, 'reject'])->name('reject')->where('permitNumber', '.*');
    Route::get('/{permitNumber}', [TRController::class, 'show'])->name('show')->where('permitNumber', '.*');
});

Route::prefix('mep')->name('mep.')->middleware(['auth', EnsureMEPValidator::class])->group(function () {
    Route::get('/', [MEPController::class, 'index'])->name('index');
    Route::post('/notifications/clear', [StaffNotificationController::class, 'clear'])->name('notifications.clear');
    Route::post('/notifications/{notification}/read', [MEPController::class, 'readNotification'])
        ->name('notifications.read');
    Route::get('/notification-feed', StaffNotificationFeedController::class)
        ->middleware('throttle:30,1')
        ->name('notifications.feed');
    Route::middleware('throttle:20,1')->group(function () {
        Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
        Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');
    });
    Route::post('/work-permits/{token}/deposit', [MEPController::class, 'decideDeposit'])->where('token', '[A-Za-z0-9]{64}')->name('work-permits.deposit');
    Route::post('/work-permits/{token}/decision', [MEPController::class, 'finalDecision'])->where('token', '[A-Za-z0-9]{64}')->name('work-permits.decision');
    Route::post('/work-permits/{token}/complete', [MEPController::class, 'complete'])->where('token', '[A-Za-z0-9]{64}')->name('work-permits.complete');
    Route::post('/work-permits/{token}/refund/start', [MEPController::class, 'startRefund'])->where('token', '[A-Za-z0-9]{64}')->name('work-permits.refund.start');
    Route::post('/work-permits/{token}/refund/finish', [MEPController::class, 'finishRefund'])->where('token', '[A-Za-z0-9]{64}')->name('work-permits.refund.finish');
});

Route::prefix('finance')->name('finance.')->middleware(['auth', EnsureFinanceValidator::class])->group(function () {
    Route::get('/', [FinanceController::class, 'index'])->name('index');
    Route::post('/work-permits/{token}/verify', [FinanceController::class, 'verify'])
        ->where('token', '[A-Za-z0-9]{64}')
        ->name('work-permits.verify');
    Route::post('/notifications/clear', [StaffNotificationController::class, 'clear'])->name('notifications.clear');
    Route::post('/notifications/{notification}/read', [FinanceController::class, 'readNotification'])->name('notifications.read');
    Route::get('/notification-feed', StaffNotificationFeedController::class)->middleware('throttle:30,1')->name('notifications.feed');
    Route::middleware('throttle:20,1')->group(function () {
        Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
        Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');
    });
});

Route::prefix('secretary')->name('secretary.')->middleware(['auth', EnsureSecretary::class])->group(function () {
    Route::get('/', [SecretaryController::class, 'index'])->name('index');
    Route::get('/loading/{permitNumber}', [SecretaryController::class, 'showLoading'])
        ->where('permitNumber', '.*')
        ->name('loading.show');
    Route::post('/notifications/clear', [StaffNotificationController::class, 'clear'])->name('notifications.clear');
    Route::post('/notifications/{notification}/read', [SecretaryController::class, 'readNotification'])
        ->name('notifications.read');
    Route::get('/notification-feed', StaffNotificationFeedController::class)
        ->middleware('throttle:30,1')
        ->name('notifications.feed');
    Route::middleware('throttle:20,1')->group(function () {
        Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
        Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');
    });
});

Route::prefix('staff/work-permits')->name('staff.work-permits.')->middleware('auth')->group(function () {
    Route::get('/{token}', [StaffWorkPermitController::class, 'show'])
        ->where('token', '[A-Za-z0-9]{64}')
        ->name('show');
    Route::get('/{token}/letter', [StaffWorkPermitController::class, 'letter'])
        ->middleware('throttle:20,1')
        ->where('token', '[A-Za-z0-9]{64}')
        ->name('letter');
    Route::get('/{token}/letter/preview', [StaffWorkPermitController::class, 'previewLetter'])
        ->middleware('throttle:30,1')
        ->where('token', '[A-Za-z0-9]{64}')
        ->name('letter.preview');
    Route::get('/{token}/letter/inline', [StaffWorkPermitController::class, 'inlineLetter'])
        ->middleware('throttle:20,1')
        ->where('token', '[A-Za-z0-9]{64}')
        ->name('letter.inline');
    Route::get('/{token}/document', [StaffWorkPermitController::class, 'document'])
        ->middleware('signed')
        ->where('token', '[A-Za-z0-9]{64}')
        ->name('document');
    Route::get('/{token}/document/preview', [StaffWorkPermitController::class, 'previewDocument'])
        ->middleware('signed')
        ->where('token', '[A-Za-z0-9]{64}')
        ->name('document.preview');
    Route::get('/{token}/payment-proof', [StaffWorkPermitController::class, 'paymentProof'])
        ->middleware('signed')
        ->where('token', '[A-Za-z0-9]{64}')
        ->name('payment-proof');
    Route::get('/{token}/payment-proof/preview', [StaffWorkPermitController::class, 'previewPaymentProof'])
        ->middleware('signed')
        ->where('token', '[A-Za-z0-9]{64}')
        ->name('payment-proof.preview');
    Route::get('/{token}/refund-proof', [StaffWorkPermitController::class, 'refundProof'])
        ->middleware('signed')
        ->where('token', '[A-Za-z0-9]{64}')
        ->name('refund-proof');
    Route::get('/{token}/refund-proof/preview', [StaffWorkPermitController::class, 'previewRefundProof'])
        ->middleware('signed')
        ->where('token', '[A-Za-z0-9]{64}')
        ->name('refund-proof.preview');
});

// ─── Scanner QR (Publik, Mobile) ───────────────────────────────────────────
Route::get('/scanner', [ScannerController::class, 'index'])->name('scanner.index');
Route::get('/scanner/verify', [ScannerController::class, 'verify'])
    ->middleware('throttle:30,1')
    ->name('scanner.verify');
