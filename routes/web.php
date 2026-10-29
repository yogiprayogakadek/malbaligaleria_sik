<?php

use App\Http\Controllers\AccountProfileController;
use App\Http\Controllers\AccountSecurityController;
use App\Http\Controllers\AdminAccountController;
use App\Http\Controllers\AdminAnnouncementController;
use App\Http\Controllers\AdminAuditLogController;
use App\Http\Controllers\AdminCommunicationLogController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminDataMaintenanceController;
use App\Http\Controllers\AdminDocumentRetentionSettingController;
use App\Http\Controllers\AdminDocumentTemplateController;
use App\Http\Controllers\AdminEmailLogController;
use App\Http\Controllers\AdminEmergencyValidationController;
use App\Http\Controllers\AdminEventRequirementController;
use App\Http\Controllers\AdminEventWhatsappGroupController;
use App\Http\Controllers\AdminFailedJobController;
use App\Http\Controllers\AdminGuideController;
use App\Http\Controllers\AdminLoadingController;
use App\Http\Controllers\AdminMailSettingController;
use App\Http\Controllers\AdminMessageTemplateController;
use App\Http\Controllers\AdminMonitoringSettingController;
use App\Http\Controllers\AdminNotificationController;
use App\Http\Controllers\AdminOperatingScheduleController;
use App\Http\Controllers\AdminPermitNumberSettingController;
use App\Http\Controllers\AdminPermitSearchController;
use App\Http\Controllers\AdminPermitSubmissionLimitController;
use App\Http\Controllers\AdminQueueReminderController;
use App\Http\Controllers\AdminReportController;
use App\Http\Controllers\AdminScannerBlockController;
use App\Http\Controllers\AdminScannerLogController;
use App\Http\Controllers\AdminScannerSettingController;
use App\Http\Controllers\AdminSecurityOfficerController;
use App\Http\Controllers\AdminSecuritySettingController;
use App\Http\Controllers\AdminSensitiveActionController;
use App\Http\Controllers\AdminSlaSettingController;
use App\Http\Controllers\AdminSystemHealthController;
use App\Http\Controllers\AdminTenantController;
use App\Http\Controllers\AdminUploadSecuritySettingController;
use App\Http\Controllers\AdminValidatorController;
use App\Http\Controllers\AdminWhatsappSettingController;
use App\Http\Controllers\AdminWorkPermitCategoryController;
use App\Http\Controllers\AdminWorkPermitDurationSettingController;
use App\Http\Controllers\ApprovalVerificationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EPController;
use App\Http\Controllers\EventPermitController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\InitialPasswordController;
use App\Http\Controllers\LoadingGateEvidenceController;
use App\Http\Controllers\LoadingPermitController;
use App\Http\Controllers\MEPController;
use App\Http\Controllers\PermitController;
use App\Http\Controllers\PermitDraftController;
use App\Http\Controllers\PermitRevisionApplicantController;
use App\Http\Controllers\PermitRevisionController;
use App\Http\Controllers\PermitRevocationController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ResetPasswordController;
use App\Http\Controllers\ScannerController;
use App\Http\Controllers\ScannerGateController;
use App\Http\Controllers\ScannerOfficerSessionController;
use App\Http\Controllers\SecretaryController;
use App\Http\Controllers\SecurityController;
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
use App\Http\Middleware\EnsureSecurityValidator;
use App\Http\Middleware\EnsureTRValidator;
use App\Http\Middleware\EnsureWebsiteOperating;
use App\Http\Middleware\MonitorPermitSubmission;
use App\Http\Middleware\RequireSensitiveActionConfirmation;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — Sistem Informasi Surat Izin Mal Bali Galeria
|--------------------------------------------------------------------------
*/

// ─── Autentikasi ───────────────────────────────────────────────────────────
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

// ─── Portal Beranda ────────────────────────────────────────────────────────
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
        Route::post('/store', [LoadingPermitController::class, 'store'])->middleware(['throttle:permit-submissions', MonitorPermitSubmission::class])->name('store');
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
        Route::post('/', [WorkPermitController::class, 'store'])->middleware(['throttle:permit-submissions', MonitorPermitSubmission::class])->name('store');
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

Route::prefix('event-permits')->name('event-permits.')->group(function () {
    Route::get('/success/{token}', [EventPermitController::class, 'success'])
        ->middleware('signed')
        ->where('token', '[A-Za-z0-9]{64}')
        ->name('success');
    Route::get('/status/{token}', [EventPermitController::class, 'status'])
        ->middleware('throttle:30,1')
        ->where('token', '[A-Za-z0-9]{64}')
        ->name('status');
    Route::get('/status/{token}/letter', [EventPermitController::class, 'downloadLetter'])
        ->middleware('throttle:20,1')
        ->where('token', '[A-Za-z0-9]{64}')
        ->name('letter');
    Route::get('/status/{token}/letter/preview', [EventPermitController::class, 'previewLetter'])
        ->middleware('throttle:30,1')
        ->where('token', '[A-Za-z0-9]{64}')
        ->name('letter.preview');
    Route::get('/status/{token}/letter/inline', [EventPermitController::class, 'inlineLetter'])
        ->middleware('throttle:20,1')
        ->where('token', '[A-Za-z0-9]{64}')
        ->name('letter.inline');

    Route::middleware(EnsureWebsiteOperating::class)->group(function () {
        Route::get('/create', [EventPermitController::class, 'create'])->name('create');
        Route::post('/', [EventPermitController::class, 'store'])
            ->middleware(['throttle:permit-submissions', MonitorPermitSubmission::class])
            ->name('store');
        Route::get('/status/{token}/revision', [PermitRevisionApplicantController::class, 'editEvent'])
            ->middleware('throttle:30,1')
            ->where('token', '[A-Za-z0-9]{64}')
            ->name('revision.edit');
        Route::post('/status/{token}/revision', [PermitRevisionApplicantController::class, 'updateEvent'])
            ->middleware('throttle:10,1')
            ->where('token', '[A-Za-z0-9]{64}')
            ->name('revision.update');
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
    Route::get('/sensitive-confirm', [AdminSensitiveActionController::class, 'show'])->name('sensitive-confirm.show');
    Route::post('/sensitive-confirm', [AdminSensitiveActionController::class, 'verify'])->middleware('throttle:5,1')->name('sensitive-confirm.verify');
    Route::get('/', AdminDashboardController::class)->name('dashboard');
    Route::get('/search', [AdminPermitSearchController::class, 'index'])
        ->middleware('throttle:60,1')
        ->name('search');
    Route::post('/search', [AdminPermitSearchController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('search.store');
    Route::get('/reports', [AdminReportController::class, 'index'])->name('reports.index');
    Route::get('/accounts', [AdminAccountController::class, 'index'])
        ->middleware(RequireSensitiveActionConfirmation::class)
        ->name('accounts.index');
    Route::patch('/accounts/{account}/status', [AdminAccountController::class, 'updateStatus'])
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:20,1'])
        ->name('accounts.status');
    Route::delete('/accounts/{account}', [AdminAccountController::class, 'destroy'])
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:5,1'])
        ->name('accounts.destroy');
    Route::get('/data-maintenance', [AdminDataMaintenanceController::class, 'index'])
        ->middleware(RequireSensitiveActionConfirmation::class)
        ->name('data-maintenance.index');
    Route::delete('/data-maintenance', [AdminDataMaintenanceController::class, 'destroy'])
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:5,1'])
        ->name('data-maintenance.destroy');
    Route::get('/emergency-validation', [AdminEmergencyValidationController::class, 'index'])
        ->middleware(RequireSensitiveActionConfirmation::class)
        ->name('emergency-validation.index');
    Route::post('/emergency-validation/loading/{loadingPermit}', [AdminEmergencyValidationController::class, 'loading'])
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:10,1'])
        ->name('emergency-validation.loading');
    Route::post('/emergency-validation/work/{workPermit}', [AdminEmergencyValidationController::class, 'work'])
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:10,1'])
        ->name('emergency-validation.work');
    Route::resource('/work-categories', AdminWorkPermitCategoryController::class)
        ->except(['show', 'destroy'])
        ->middleware(RequireSensitiveActionConfirmation::class)
        ->parameters(['work-categories' => 'workCategory']);
    Route::resource('/event-requirements', AdminEventRequirementController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->middleware(RequireSensitiveActionConfirmation::class);
    Route::get('/settings/work-permit-durations', [AdminWorkPermitDurationSettingController::class, 'edit'])
        ->middleware(RequireSensitiveActionConfirmation::class)
        ->name('settings.work-permit-durations.edit');
    Route::put('/settings/work-permit-durations', [AdminWorkPermitDurationSettingController::class, 'update'])
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:10,1'])
        ->name('settings.work-permit-durations.update');
    Route::resource('/tenants', AdminTenantController::class)
        ->except(['show', 'destroy'])
        ->middleware(RequireSensitiveActionConfirmation::class);
    Route::resource('/message-templates', AdminMessageTemplateController::class)
        ->only(['index', 'create', 'store', 'edit', 'update'])
        ->middleware(RequireSensitiveActionConfirmation::class);
    Route::get('/announcements', [AdminAnnouncementController::class, 'index'])->name('announcements.index');
    Route::get('/queue-reminders', [AdminQueueReminderController::class, 'index'])->name('queue-reminders.index');
    Route::post('/queue-reminders', [AdminQueueReminderController::class, 'store'])
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:5,1'])
        ->name('queue-reminders.store');
    Route::get('/communication-logs', [AdminCommunicationLogController::class, 'index'])->name('communication-logs.index');
    Route::post('/communication-logs/whatsapp/{whatsappLog}/retry', [AdminCommunicationLogController::class, 'retryWhatsapp'])->middleware('throttle:10,1')->name('communication-logs.whatsapp.retry');
    Route::post('/communication-logs/whatsapp/{whatsappLog}/fallback', [AdminCommunicationLogController::class, 'fallbackToEmail'])->middleware('throttle:10,1')->name('communication-logs.whatsapp.fallback');
    Route::post('/announcements', [AdminAnnouncementController::class, 'store'])
        ->middleware('throttle:announcement-campaigns')
        ->name('announcements.store');
    Route::put('/announcements/limiter', [AdminAnnouncementController::class, 'updateLimiter'])
        ->middleware('throttle:10,1')
        ->name('announcements.limiter');
    Route::patch('/announcements/{campaign}/cancel', [AdminAnnouncementController::class, 'cancel'])
        ->middleware('throttle:10,1')
        ->name('announcements.cancel');
    Route::get('/reports/excel', [AdminReportController::class, 'excel'])
        ->middleware('throttle:6,1')
        ->name('reports.excel');
    Route::get('/reports/pdf', [AdminReportController::class, 'pdf'])
        ->middleware('throttle:6,1')
        ->name('reports.pdf');
    Route::get('/guide', [AdminGuideController::class, 'index'])->name('guide.index');
    Route::get('/guide/download', [AdminGuideController::class, 'download'])
        ->middleware('throttle:6,1')
        ->name('guide.download');
    Route::get('/audit-logs', AdminAuditLogController::class)->name('audit.index');
    Route::get('/scanner-logs', AdminScannerLogController::class)->name('scanner-logs.index');
    Route::get('/scanner-gate-events/{securityGateEvent}/evidence', [AdminScannerLogController::class, 'evidence'])
        ->middleware('throttle:30,1')
        ->name('scanner-gate-events.evidence');
    Route::delete('/scanner-blocks/{scannerIpBlock}', [AdminScannerBlockController::class, 'destroy'])
        ->middleware('throttle:10,1')
        ->name('scanner-blocks.destroy');
    Route::get('/security-officers', [AdminSecurityOfficerController::class, 'index'])
        ->middleware(RequireSensitiveActionConfirmation::class)
        ->name('security-officers.index');
    Route::post('/security-officers', [AdminSecurityOfficerController::class, 'store'])
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:20,1'])
        ->name('security-officers.store');
    Route::post('/security-officers/import', [AdminSecurityOfficerController::class, 'import'])
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:5,1'])
        ->name('security-officers.import');
    Route::get('/security-officers/template', [AdminSecurityOfficerController::class, 'template'])
        ->middleware(RequireSensitiveActionConfirmation::class)
        ->name('security-officers.template');
    Route::patch('/security-officers/{securityOfficer}/status', [AdminSecurityOfficerController::class, 'updateStatus'])
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:20,1'])
        ->name('security-officers.status');
    Route::post('/security-officers/{securityOfficer}/reset-pin', [AdminSecurityOfficerController::class, 'resetPin'])
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:10,1'])
        ->name('security-officers.reset-pin');
    Route::get('/system-health', AdminSystemHealthController::class)
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:10,1'])
        ->name('health.index');
    Route::get('/settings/permit-numbers', [AdminPermitNumberSettingController::class, 'edit'])
        ->middleware(RequireSensitiveActionConfirmation::class)
        ->name('settings.permit-numbers.edit');
    Route::put('/settings/permit-numbers', [AdminPermitNumberSettingController::class, 'update'])
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:10,1'])
        ->name('settings.permit-numbers.update');
    Route::get('/settings/document-templates', [AdminDocumentTemplateController::class, 'index'])
        ->middleware(RequireSensitiveActionConfirmation::class)
        ->name('settings.document-templates.index');
    Route::put('/settings/document-templates', [AdminDocumentTemplateController::class, 'update'])
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:10,1'])
        ->name('settings.document-templates.update');
    Route::post('/settings/document-templates/test', [AdminDocumentTemplateController::class, 'test'])
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:3,1'])
        ->name('settings.document-templates.test');
    Route::post('/settings/document-templates/upload', [AdminDocumentTemplateController::class, 'store'])
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:5,1'])
        ->name('settings.document-templates.store');
    Route::patch('/settings/document-templates/{documentTemplate}/activate', [AdminDocumentTemplateController::class, 'activate'])
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:10,1'])
        ->name('settings.document-templates.activate');
    Route::get('/settings/document-templates/{documentTemplate}/download', [AdminDocumentTemplateController::class, 'download'])
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:20,1'])
        ->name('settings.document-templates.download');
    Route::put('/settings/monitoring', [AdminMonitoringSettingController::class, 'update'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:10,1'])->name('settings.monitoring.update');
    Route::post('/settings/monitoring/test', [AdminMonitoringSettingController::class, 'test'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:3,1'])->name('settings.monitoring.test');
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
    Route::get('/loading-evidence', [LoadingGateEvidenceController::class, 'index'])
        ->name('loading-evidence.index');
    Route::get('/loading-evidence/{securityGateEvent}', [LoadingGateEvidenceController::class, 'evidence'])
        ->middleware('throttle:30,1')
        ->name('loading-evidence.evidence');
    Route::get('/loading-documents/{documentToken}', [AdminLoadingController::class, 'document'])
        ->middleware('signed')
        ->where('documentToken', '[A-Za-z0-9]{64}')
        ->name('loading.document');
    Route::get('/loading-documents/{documentToken}/preview', [AdminLoadingController::class, 'previewDocument'])
        ->middleware('signed')
        ->where('documentToken', '[A-Za-z0-9]{64}')
        ->name('loading.document.preview');
    Route::get('/loading-instruction-letters/{documentToken}', [AdminLoadingController::class, 'instructionLetter'])
        ->middleware('signed')
        ->where('documentToken', '[A-Za-z0-9]{64}')
        ->name('loading.instruction-letter');
    Route::get('/loading-instruction-letters/{documentToken}/preview', [AdminLoadingController::class, 'previewInstructionLetter'])
        ->middleware('signed')
        ->where('documentToken', '[A-Za-z0-9]{64}')
        ->name('loading.instruction-letter.preview');
    Route::get('/loading/{permitNumber}', [AdminLoadingController::class, 'show'])
        ->where('permitNumber', '.*')
        ->name('loading.show');
    Route::get('/validators', [AdminValidatorController::class, 'index'])->middleware(RequireSensitiveActionConfirmation::class)->name('validators.index');
    Route::get('/validators/create', [AdminValidatorController::class, 'create'])->middleware(RequireSensitiveActionConfirmation::class)->name('validators.create');
    Route::post('/validators', [AdminValidatorController::class, 'store'])
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:10,1'])
        ->name('validators.store');
    Route::post('/validators/check-availability', [AdminValidatorController::class, 'checkAvailability'])
        ->middleware('throttle:60,1')
        ->name('validators.availability');
    Route::get('/validators/{validator}/edit', [AdminValidatorController::class, 'edit'])->middleware(RequireSensitiveActionConfirmation::class)->name('validators.edit');
    Route::put('/validators/{validator}', [AdminValidatorController::class, 'update'])
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:20,1'])
        ->name('validators.update');
    Route::post('/validators/{validator}/check-availability', [AdminValidatorController::class, 'checkAvailability'])
        ->middleware('throttle:60,1')
        ->name('validators.availability.edit');
    Route::patch('/validators/{validator}/status', [AdminValidatorController::class, 'updateStatus'])
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:20,1'])
        ->name('validators.status');

    Route::get('/operating-hours', [AdminOperatingScheduleController::class, 'edit'])->middleware(RequireSensitiveActionConfirmation::class)->name('schedule.edit');
    Route::put('/operating-hours', [AdminOperatingScheduleController::class, 'update'])
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:10,1'])
        ->name('schedule.update');
    Route::post('/operating-hours/exceptions', [AdminOperatingScheduleController::class, 'storeException'])
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:10,1'])
        ->name('schedule.exceptions.store');
    Route::delete('/operating-hours/exceptions/{exception}', [AdminOperatingScheduleController::class, 'destroyException'])
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:10,1'])
        ->name('schedule.exceptions.destroy');

    Route::get('/event-whatsapp-group', [AdminEventWhatsappGroupController::class, 'index'])
        ->middleware(RequireSensitiveActionConfirmation::class)
        ->name('event-whatsapp-group.index');
    Route::put('/event-whatsapp-group', [AdminEventWhatsappGroupController::class, 'update'])
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:10,1'])
        ->name('event-whatsapp-group.update');
    Route::post('/event-whatsapp-group/sync', [AdminEventWhatsappGroupController::class, 'syncGroups'])
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:event-group-sync'])
        ->name('event-whatsapp-group.sync');
    Route::post('/event-whatsapp-group/outbound-only', [AdminEventWhatsappGroupController::class, 'enableOutboundOnly'])
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:event-group-outbound'])
        ->name('event-whatsapp-group.outbound-only');
    Route::put('/event-whatsapp-group/limiter', [AdminEventWhatsappGroupController::class, 'updateLimiter'])
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:10,1'])
        ->name('event-whatsapp-group.limiter');
    Route::post('/event-whatsapp-group/members', [AdminEventWhatsappGroupController::class, 'storeMember'])
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:20,1'])
        ->name('event-whatsapp-group.members.store');
    Route::delete('/event-whatsapp-group/members/{member}', [AdminEventWhatsappGroupController::class, 'destroyMember'])
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:20,1'])
        ->name('event-whatsapp-group.members.destroy');
    Route::post('/event-whatsapp-group/rules', [AdminEventWhatsappGroupController::class, 'storeRule'])
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:20,1'])
        ->name('event-whatsapp-group.rules.store');
    Route::delete('/event-whatsapp-group/rules/{rule}', [AdminEventWhatsappGroupController::class, 'destroyRule'])
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:20,1'])
        ->name('event-whatsapp-group.rules.destroy');
    Route::post('/event-whatsapp-group/deliveries/{delivery}/retry', [AdminEventWhatsappGroupController::class, 'retry'])
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:5,1'])
        ->name('event-whatsapp-group.deliveries.retry');

    Route::get('/settings/email', [AdminMailSettingController::class, 'edit'])->middleware(RequireSensitiveActionConfirmation::class)->name('settings.mail.edit');
    Route::put('/settings/email', [AdminMailSettingController::class, 'update'])
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:5,1'])
        ->name('settings.mail.update');
    Route::post('/settings/email/test', [AdminMailSettingController::class, 'test'])
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:mail-tests'])
        ->name('settings.mail.test');

    Route::get('/settings/scanner', [AdminScannerSettingController::class, 'edit'])
        ->middleware(RequireSensitiveActionConfirmation::class)
        ->name('settings.scanner.edit');
    Route::put('/settings/scanner', [AdminScannerSettingController::class, 'update'])
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:10,1'])
        ->name('settings.scanner.update');

    Route::get('/settings/security', [AdminSecuritySettingController::class, 'edit'])
        ->name('settings.security.edit');
    Route::put('/settings/security', [AdminSecuritySettingController::class, 'update'])
        ->middleware('throttle:5,1')
        ->name('settings.security.update');

    Route::get('/settings/upload-security', [AdminUploadSecuritySettingController::class, 'edit'])
        ->middleware(RequireSensitiveActionConfirmation::class)
        ->name('settings.upload-security.edit');
    Route::put('/settings/upload-security', [AdminUploadSecuritySettingController::class, 'update'])
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:5,1'])
        ->name('settings.upload-security.update');

    Route::get('/settings/document-retention', [AdminDocumentRetentionSettingController::class, 'edit'])
        ->middleware(RequireSensitiveActionConfirmation::class)
        ->name('settings.retention.edit');
    Route::put('/settings/document-retention', [AdminDocumentRetentionSettingController::class, 'update'])
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:5,1'])
        ->name('settings.retention.update');
    Route::post('/settings/document-retention/preview', [AdminDocumentRetentionSettingController::class, 'preview'])
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:10,1'])
        ->name('settings.retention.preview');

    Route::get('/settings/sla', [AdminSlaSettingController::class, 'edit'])
        ->name('settings.sla.edit');
    Route::put('/settings/sla', [AdminSlaSettingController::class, 'update'])
        ->middleware('throttle:10,1')
        ->name('settings.sla.update');

    Route::get('/settings/permit-limits', [AdminPermitSubmissionLimitController::class, 'edit'])
        ->middleware(RequireSensitiveActionConfirmation::class)
        ->name('settings.permit-limits.edit');
    Route::put('/settings/permit-limits', [AdminPermitSubmissionLimitController::class, 'update'])
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:10,1'])
        ->name('settings.permit-limits.update');

    Route::get('/settings/whatsapp', [AdminWhatsappSettingController::class, 'edit'])
        ->middleware(RequireSensitiveActionConfirmation::class)
        ->name('settings.whatsapp.edit');
    Route::put('/settings/whatsapp', [AdminWhatsappSettingController::class, 'update'])
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:10,1'])
        ->name('settings.whatsapp.update');
    Route::put('/settings/whatsapp/recipients', [AdminWhatsappSettingController::class, 'updateRecipients'])
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:10,1'])
        ->name('settings.whatsapp.recipients');
    Route::post('/settings/whatsapp/test', [AdminWhatsappSettingController::class, 'test'])
        ->middleware([RequireSensitiveActionConfirmation::class, 'throttle:whatsapp-tests'])
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
    Route::get('/instruction-letters/{documentToken}', [TRController::class, 'viewInstructionLetter'])
        ->middleware('signed')
        ->where('documentToken', '[A-Za-z0-9]{64}')
        ->name('instruction-letter');
    Route::get('/instruction-letters/{documentToken}/preview', [TRController::class, 'previewInstructionLetter'])
        ->middleware('signed')
        ->where('documentToken', '[A-Za-z0-9]{64}')
        ->name('instruction-letter.preview');
    Route::get('/work-permits', [TRController::class, 'workPermits'])->name('work-permits.index');
    Route::get('/loading-evidence', [LoadingGateEvidenceController::class, 'index'])
        ->name('loading-evidence.index');
    Route::get('/loading-evidence/{securityGateEvent}', [LoadingGateEvidenceController::class, 'evidence'])
        ->middleware('throttle:30,1')
        ->name('loading-evidence.evidence');
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
Route::post('/scanner/gate-events', [ScannerGateController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('scanner.gate-events.store');
Route::post('/scanner/officer-session', [ScannerOfficerSessionController::class, 'store'])
    ->middleware('throttle:8,1')
    ->name('scanner.officer-session.store');
Route::delete('/scanner/officer-session', [ScannerOfficerSessionController::class, 'destroy'])
    ->name('scanner.officer-session.destroy');

Route::prefix('security')->name('security.')->middleware(['auth', EnsureSecurityValidator::class])->group(function () {
    Route::get('/', [SecurityController::class, 'index'])->name('index');
    Route::post('/notifications/clear', [StaffNotificationController::class, 'clear'])->name('notifications.clear');
    Route::post('/notifications/{notification}/read', [SecurityController::class, 'readNotification'])->name('notifications.read');
    Route::get('/notification-feed', StaffNotificationFeedController::class)->middleware('throttle:30,1')->name('notifications.feed');
    Route::middleware('throttle:20,1')->group(function () {
        Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
        Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');
    });
});
