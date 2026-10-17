<?php

use App\Http\Controllers\AccountSecurityController;
use App\Http\Controllers\AdminAuditLogController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminDocumentRetentionSettingController;
use App\Http\Controllers\AdminEmailLogController;
use App\Http\Controllers\AdminFailedJobController;
use App\Http\Controllers\AdminLoadingController;
use App\Http\Controllers\AdminMailSettingController;
use App\Http\Controllers\AdminNotificationController;
use App\Http\Controllers\AdminOperatingScheduleController;
use App\Http\Controllers\AdminPermitAssignmentController;
use App\Http\Controllers\AdminPermitSearchController;
use App\Http\Controllers\AdminPermitSubmissionLimitController;
use App\Http\Controllers\AdminReportController;
use App\Http\Controllers\AdminScannerLogController;
use App\Http\Controllers\AdminScannerSettingController;
use App\Http\Controllers\AdminSecuritySettingController;
use App\Http\Controllers\AdminSlaSettingController;
use App\Http\Controllers\AdminSystemHealthController;
use App\Http\Controllers\AdminValidatorController;
use App\Http\Controllers\AdminWhatsappSettingController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EPController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\InitialPasswordController;
use App\Http\Controllers\LoadingPermitController;
use App\Http\Controllers\MEPController;
use App\Http\Controllers\PermitAssignmentController;
use App\Http\Controllers\PermitController;
use App\Http\Controllers\PermitDraftController;
use App\Http\Controllers\PermitRevisionApplicantController;
use App\Http\Controllers\PermitRevisionController;
use App\Http\Controllers\PermitRevocationController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ScannerController;
use App\Http\Controllers\SecretaryController;
use App\Http\Controllers\StaffNotificationController;
use App\Http\Controllers\StaffNotificationFeedController;
use App\Http\Controllers\StaffWorkPermitController;
use App\Http\Controllers\TRController;
use App\Http\Controllers\TwoFactorChallengeController;
use App\Http\Controllers\TwoFactorSetupController;
use App\Http\Controllers\WorkPermitApplicantController;
use App\Http\Controllers\WorkPermitController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureEPValidator;
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

// ─── Portal Beranda ────────────────────────────────────────────────────────
Route::controller(PortalController::class)->middleware(EnsureWebsiteOperating::class)->group(function () {
    Route::get('/', 'index')->name('portal.dashboard');
    Route::get('/help', 'help')->name('portal.help');
});
Route::match(['get', 'post'], '/track', [PortalController::class, 'track'])
    ->name('portal.track')
    ->middleware('throttle:30,1');

// ─── Permit Legacy (generic form — work, exhibition, event) ───────────────────
Route::controller(PermitController::class)->prefix('permits')->name('permits.')->middleware(EnsureWebsiteOperating::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/store', 'store')->name('store');
    Route::get('/success', 'success')->name('success');
});

// ─── Permohonan Loading In/Out ─────────────────────────────────────────────
Route::prefix('loading')->name('loading.')->group(function () {
    // Status dan surat yang sudah terbit tetap dapat dibaca di luar jam pengajuan.
    Route::post('/track', [LoadingPermitController::class, 'track'])->middleware('throttle:30,1')->name('track');
    Route::get('/track/{permitNumber}', [LoadingPermitController::class, 'show'])
        ->middleware('throttle:30,1')
        ->name('show')
        ->where('permitNumber', '.*');

    Route::get('/success/{permitNumber}', [LoadingPermitController::class, 'success'])
        ->middleware('signed')
        ->name('success')
        ->where('permitNumber', '.*');

    Route::get('/letter/{permitNumber}/preview', [LoadingPermitController::class, 'previewLetter'])->middleware('throttle:30,1')->name('letter.preview')->where('permitNumber', '.*');
    Route::get('/letter/{permitNumber}/inline', [LoadingPermitController::class, 'inlineLetter'])->middleware('throttle:20,1')->name('letter.inline')->where('permitNumber', '.*');
    Route::get('/letter/{permitNumber}', [LoadingPermitController::class, 'downloadLetter'])->middleware('throttle:20,1')->name('letter')->where('permitNumber', '.*');

    Route::middleware(EnsureWebsiteOperating::class)->group(function () {
        Route::get('/create', [LoadingPermitController::class, 'create'])->name('create');
        Route::post('/store', [LoadingPermitController::class, 'store'])->middleware('throttle:permit-submissions')->name('store');
        Route::get('/revision/{permitNumber}', [PermitRevisionApplicantController::class, 'editLoading'])
            ->middleware('throttle:30,1')
            ->name('revision.edit')
            ->where('permitNumber', '.*');
        Route::post('/revision/{permitNumber}', [PermitRevisionApplicantController::class, 'updateLoading'])
            ->middleware('throttle:10,1')
            ->name('revision.update')
            ->where('permitNumber', '.*');
    });
});

Route::prefix('work-permits')->name('work-permits.')->group(function () {
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
    Route::get('/status/{token}/refund-proof', [WorkPermitApplicantController::class, 'refundProof'])
        ->middleware(['signed', 'throttle:30,1'])
        ->where('token', '[A-Za-z0-9]{64}')
        ->name('refund-proof');
    Route::get('/status/{token}/refund-proof/preview', [WorkPermitApplicantController::class, 'previewRefundProof'])
        ->middleware(['signed', 'throttle:30,1'])
        ->where('token', '[A-Za-z0-9]{64}')
        ->name('refund-proof.preview');

    Route::middleware(EnsureWebsiteOperating::class)->group(function () {
        Route::get('/create', [WorkPermitController::class, 'create'])->name('create');
        Route::post('/', [WorkPermitController::class, 'store'])->middleware('throttle:permit-submissions')->name('store');
        Route::get('/status/{token}/revision', [PermitRevisionApplicantController::class, 'editWork'])
            ->middleware('throttle:30,1')
            ->where('token', '[A-Za-z0-9]{64}')
            ->name('revision.edit');
        Route::post('/status/{token}/revision', [PermitRevisionApplicantController::class, 'updateWork'])
            ->middleware('throttle:10,1')
            ->where('token', '[A-Za-z0-9]{64}')
            ->name('revision.update');
        Route::post('/status/{token}/payment-proof', [WorkPermitApplicantController::class, 'uploadPaymentProof'])
            ->middleware('throttle:10,1')
            ->where('token', '[A-Za-z0-9]{64}')
            ->name('payment-proof.store');
    });
});

Route::prefix('permit-drafts')->name('permit-drafts.')->middleware([EnsureWebsiteOperating::class, 'throttle:60,1'])->group(function () {
    Route::post('/{type}', [PermitDraftController::class, 'store'])
        ->whereIn('type', ['loading', 'work'])
        ->name('store');
    Route::delete('/{type}', [PermitDraftController::class, 'destroy'])
        ->whereIn('type', ['loading', 'work'])
        ->name('destroy');
});

Route::post('/staff/permit-revisions/{type}/{permitId}', [PermitRevisionController::class, 'store'])
    ->middleware(['auth', 'throttle:20,1'])
    ->whereIn('type', ['loading', 'work'])
    ->whereNumber('permitId')
    ->name('staff.permit-revisions.store');

Route::middleware(['auth', 'throttle:10,1'])->prefix('staff/permit-revocations')->name('staff.permit-revocations.')->group(function () {
    Route::post('/loading/{permitNumber}', [PermitRevocationController::class, 'loading'])
        ->where('permitNumber', '.*')
        ->name('loading');
    Route::post('/work/{token}', [PermitRevocationController::class, 'work'])
        ->where('token', '[A-Za-z0-9]{64}')
        ->name('work');
});

// ─── Administrator ────────────────────────────────────────────────────────
Route::prefix('admin')->name('admin.')->middleware(['auth', EnsureAdmin::class])->group(function () {
    Route::get('/', AdminDashboardController::class)->name('dashboard');
    Route::get('/search', [AdminPermitSearchController::class, 'index'])
        ->middleware('throttle:60,1')
        ->name('search');
    Route::post('/search', [AdminPermitSearchController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('search.store');
    Route::get('/reports', [AdminReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/excel', [AdminReportController::class, 'excel'])
        ->middleware('throttle:6,1')
        ->name('reports.excel');
    Route::get('/reports/pdf', [AdminReportController::class, 'pdf'])
        ->middleware('throttle:6,1')
        ->name('reports.pdf');
    Route::get('/audit-logs', AdminAuditLogController::class)->name('audit.index');
    Route::get('/scanner-logs', AdminScannerLogController::class)->name('scanner-logs.index');
    Route::get('/system-health', AdminSystemHealthController::class)
        ->middleware('throttle:10,1')
        ->name('health.index');
    Route::get('/email-logs', [AdminEmailLogController::class, 'index'])->name('email-logs.index');
    Route::post('/email-logs/{emailLog}/retry', [AdminEmailLogController::class, 'retry'])
        ->middleware('throttle:10,1')
        ->name('email-logs.retry');
    Route::get('/failed-jobs', [AdminFailedJobController::class, 'index'])->name('failed-jobs.index');
    Route::get('/failed-jobs/{uuid}/retry', [AdminFailedJobController::class, 'confirm'])
        ->whereUuid('uuid')
        ->name('failed-jobs.confirm');
    Route::post('/failed-jobs/{uuid}/retry', [AdminFailedJobController::class, 'retry'])
        ->whereUuid('uuid')
        ->middleware('throttle:5,1')
        ->name('failed-jobs.retry');

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
    Route::put('/loading/{permitNumber}/assignment', [AdminPermitAssignmentController::class, 'loading'])
        ->where('permitNumber', '.*')
        ->middleware('throttle:30,1')
        ->name('loading.assignment');
    Route::put('/work-permits/{token}/assignment', [AdminPermitAssignmentController::class, 'work'])
        ->where('token', '[A-Za-z0-9]{64}')
        ->middleware('throttle:30,1')
        ->name('work-permits.assignment');

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
    Route::post('/operating-hours/exceptions', [AdminOperatingScheduleController::class, 'storeException'])
        ->middleware('throttle:10,1')
        ->name('schedule.exceptions.store');
    Route::delete('/operating-hours/exceptions/{exception}', [AdminOperatingScheduleController::class, 'destroyException'])
        ->middleware('throttle:10,1')
        ->name('schedule.exceptions.destroy');

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

    Route::get('/settings/security', [AdminSecuritySettingController::class, 'edit'])
        ->name('settings.security.edit');
    Route::put('/settings/security', [AdminSecuritySettingController::class, 'update'])
        ->middleware('throttle:5,1')
        ->name('settings.security.update');

    Route::get('/settings/document-retention', [AdminDocumentRetentionSettingController::class, 'edit'])
        ->name('settings.retention.edit');
    Route::put('/settings/document-retention', [AdminDocumentRetentionSettingController::class, 'update'])
        ->middleware('throttle:5,1')
        ->name('settings.retention.update');
    Route::post('/settings/document-retention/preview', [AdminDocumentRetentionSettingController::class, 'preview'])
        ->middleware('throttle:10,1')
        ->name('settings.retention.preview');

    Route::get('/settings/sla', [AdminSlaSettingController::class, 'edit'])
        ->name('settings.sla.edit');
    Route::put('/settings/sla', [AdminSlaSettingController::class, 'update'])
        ->middleware('throttle:10,1')
        ->name('settings.sla.update');

    Route::get('/settings/permit-limits', [AdminPermitSubmissionLimitController::class, 'edit'])
        ->name('settings.permit-limits.edit');
    Route::put('/settings/permit-limits', [AdminPermitSubmissionLimitController::class, 'update'])
        ->middleware('throttle:10,1')
        ->name('settings.permit-limits.update');

    Route::get('/settings/whatsapp', [AdminWhatsappSettingController::class, 'edit'])
        ->name('settings.whatsapp.edit');
    Route::put('/settings/whatsapp', [AdminWhatsappSettingController::class, 'update'])
        ->middleware('throttle:10,1')
        ->name('settings.whatsapp.update');
    Route::put('/settings/whatsapp/recipients', [AdminWhatsappSettingController::class, 'updateRecipients'])
        ->middleware('throttle:10,1')
        ->name('settings.whatsapp.recipients');
    Route::post('/settings/whatsapp/test', [AdminWhatsappSettingController::class, 'test'])
        ->middleware('throttle:whatsapp-tests')
        ->name('settings.whatsapp.test');

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
    Route::post('/{permitNumber}/assignment', [PermitAssignmentController::class, 'loading'])
        ->middleware('throttle:30,1')
        ->name('assignment')
        ->where('permitNumber', '.*');
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

Route::prefix('ep')->name('ep.')->middleware(['auth', EnsureEPValidator::class])->group(function () {
    Route::get('/', [EPController::class, 'index'])->name('index');
    Route::post('/work-permits/{token}/decision', [EPController::class, 'decide'])
        ->where('token', '[A-Za-z0-9]{64}')
        ->name('work-permits.decision');
    Route::post('/notifications/clear', [StaffNotificationController::class, 'clear'])->name('notifications.clear');
    Route::post('/notifications/{notification}/read', [EPController::class, 'readNotification'])->name('notifications.read');
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
    Route::post('/{token}/assignment', [PermitAssignmentController::class, 'work'])
        ->where('token', '[A-Za-z0-9]{64}')
        ->middleware('throttle:30,1')
        ->name('assignment');
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
    Route::get('/{token}/assignment-letter', [StaffWorkPermitController::class, 'assignmentLetter'])
        ->middleware('signed')
        ->where('token', '[A-Za-z0-9]{64}')
        ->name('assignment-letter');
    Route::get('/{token}/assignment-letter/preview', [StaffWorkPermitController::class, 'previewAssignmentLetter'])
        ->middleware('signed')
        ->where('token', '[A-Za-z0-9]{64}')
        ->name('assignment-letter.preview');
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
