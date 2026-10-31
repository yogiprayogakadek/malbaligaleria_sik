<?php

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
use App\Http\Controllers\AdminFormPresenceController;
use App\Http\Controllers\AdminGuideController;
use App\Http\Controllers\AdminLoadingController;
use App\Http\Controllers\AdminMailSettingController;
use App\Http\Controllers\AdminMessageTemplateController;
use App\Http\Controllers\AdminMonitoringSettingController;
use App\Http\Controllers\AdminNotificationController;
use App\Http\Controllers\AdminOperatingScheduleController;
use App\Http\Controllers\AdminOperationTraceController;
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
use App\Http\Controllers\LoadingGateEvidenceController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\StaffNotificationController;
use App\Http\Controllers\StaffNotificationFeedController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\RequireSensitiveActionConfirmation;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware(['auth', EnsureAdmin::class])->group(function () {
    Route::get('/sensitive-confirm', [AdminSensitiveActionController::class, 'show'])->name('sensitive-confirm.show');
    Route::post('/sensitive-confirm', [AdminSensitiveActionController::class, 'verify'])->middleware('throttle:5,1')->name('sensitive-confirm.verify');
    Route::get('/', AdminDashboardController::class)->name('dashboard');
    Route::get('/search', [AdminPermitSearchController::class, 'index'])->middleware('throttle:60,1')->name('search');
    Route::post('/search', [AdminPermitSearchController::class, 'store'])->middleware('throttle:30,1')->name('search.store');
    Route::get('/reports', [AdminReportController::class, 'index'])->name('reports.index');
    Route::get('/accounts', [AdminAccountController::class, 'index'])->middleware(RequireSensitiveActionConfirmation::class)->name('accounts.index');
    Route::patch('/accounts/{account}/status', [AdminAccountController::class, 'updateStatus'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:20,1'])->name('accounts.status');
    Route::delete('/accounts/{account}', [AdminAccountController::class, 'destroy'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:5,1'])->name('accounts.destroy');
    Route::get('/data-maintenance', [AdminDataMaintenanceController::class, 'index'])->middleware(RequireSensitiveActionConfirmation::class)->name('data-maintenance.index');
    Route::delete('/data-maintenance', [AdminDataMaintenanceController::class, 'destroy'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:5,1'])->name('data-maintenance.destroy');
    Route::get('/emergency-validation', [AdminEmergencyValidationController::class, 'index'])->middleware(RequireSensitiveActionConfirmation::class)->name('emergency-validation.index');
    Route::post('/emergency-validation/loading/{loadingPermit}', [AdminEmergencyValidationController::class, 'loading'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:10,1'])->name('emergency-validation.loading');
    Route::post('/emergency-validation/work/{workPermit}', [AdminEmergencyValidationController::class, 'work'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:10,1'])->name('emergency-validation.work');
    Route::resource('/work-categories', AdminWorkPermitCategoryController::class)
        ->except(['show', 'destroy'])
        ->middleware(RequireSensitiveActionConfirmation::class)
        ->parameters(['work-categories' => 'workCategory']);
    Route::resource('/event-requirements', AdminEventRequirementController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->middleware(RequireSensitiveActionConfirmation::class);
    Route::get('/settings/work-permit-durations', [AdminWorkPermitDurationSettingController::class, 'edit'])->middleware(RequireSensitiveActionConfirmation::class)->name('settings.work-permit-durations.edit');
    Route::put('/settings/work-permit-durations', [AdminWorkPermitDurationSettingController::class, 'update'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:10,1'])->name('settings.work-permit-durations.update');
    Route::resource('/tenants', AdminTenantController::class)->except(['show', 'destroy'])->middleware(RequireSensitiveActionConfirmation::class);
    Route::resource('/message-templates', AdminMessageTemplateController::class)->only(['index', 'create', 'store', 'edit', 'update'])->middleware(RequireSensitiveActionConfirmation::class);

    Route::get('/announcements', [AdminAnnouncementController::class, 'index'])->name('announcements.index');
    Route::post('/announcements', [AdminAnnouncementController::class, 'store'])->middleware('throttle:announcement-campaigns')->name('announcements.store');
    Route::put('/announcements/limiter', [AdminAnnouncementController::class, 'updateLimiter'])->middleware('throttle:10,1')->name('announcements.limiter');
    Route::patch('/announcements/{campaign}/cancel', [AdminAnnouncementController::class, 'cancel'])->middleware('throttle:10,1')->name('announcements.cancel');
    Route::get('/queue-reminders', [AdminQueueReminderController::class, 'index'])->name('queue-reminders.index');
    Route::post('/queue-reminders', [AdminQueueReminderController::class, 'store'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:5,1'])->name('queue-reminders.store');
    Route::get('/communication-logs', [AdminCommunicationLogController::class, 'index'])->name('communication-logs.index');
    Route::post('/communication-logs/whatsapp/{whatsappLog}/retry', [AdminCommunicationLogController::class, 'retryWhatsapp'])->middleware('throttle:10,1')->name('communication-logs.whatsapp.retry');
    Route::post('/communication-logs/whatsapp/{whatsappLog}/fallback', [AdminCommunicationLogController::class, 'fallbackToEmail'])->middleware('throttle:10,1')->name('communication-logs.whatsapp.fallback');

    Route::get('/reports/excel', [AdminReportController::class, 'excel'])->middleware('throttle:6,1')->name('reports.excel');
    Route::get('/reports/pdf', [AdminReportController::class, 'pdf'])->middleware('throttle:6,1')->name('reports.pdf');
    Route::get('/guide', [AdminGuideController::class, 'index'])->name('guide.index');
    Route::get('/guide/download', [AdminGuideController::class, 'download'])->middleware('throttle:6,1')->name('guide.download');
    Route::get('/audit-logs', AdminAuditLogController::class)->name('audit.index');
    Route::get('/scanner-logs', AdminScannerLogController::class)->name('scanner-logs.index');
    Route::get('/scanner-gate-events/{securityGateEvent}/evidence', [AdminScannerLogController::class, 'evidence'])->middleware('throttle:30,1')->name('scanner-gate-events.evidence');
    Route::delete('/scanner-blocks/{scannerIpBlock}', [AdminScannerBlockController::class, 'destroy'])->middleware('throttle:10,1')->name('scanner-blocks.destroy');

    Route::get('/security-officers', [AdminSecurityOfficerController::class, 'index'])->middleware(RequireSensitiveActionConfirmation::class)->name('security-officers.index');
    Route::post('/security-officers', [AdminSecurityOfficerController::class, 'store'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:20,1'])->name('security-officers.store');
    Route::post('/security-officers/import', [AdminSecurityOfficerController::class, 'import'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:5,1'])->name('security-officers.import');
    Route::get('/security-officers/template', [AdminSecurityOfficerController::class, 'template'])->middleware(RequireSensitiveActionConfirmation::class)->name('security-officers.template');
    Route::patch('/security-officers/{securityOfficer}/status', [AdminSecurityOfficerController::class, 'updateStatus'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:20,1'])->name('security-officers.status');
    Route::post('/security-officers/{securityOfficer}/reset-pin', [AdminSecurityOfficerController::class, 'resetPin'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:10,1'])->name('security-officers.reset-pin');
    Route::get('/system-health', AdminSystemHealthController::class)->middleware([RequireSensitiveActionConfirmation::class, 'throttle:10,1'])->name('health.index');
    Route::get('/operation-traces', [AdminOperationTraceController::class, 'index'])->middleware(RequireSensitiveActionConfirmation::class)->name('operation-traces.index');
    Route::get('/operation-traces/{trace}', [AdminOperationTraceController::class, 'show'])->middleware(RequireSensitiveActionConfirmation::class)->name('operation-traces.show');
    Route::get('/form-activity', [AdminFormPresenceController::class, 'index'])->name('form-presence.index');
    Route::get('/form-activity/snapshot', [AdminFormPresenceController::class, 'snapshot'])->middleware('throttle:60,1')->name('form-presence.snapshot');

    Route::get('/settings/permit-numbers', [AdminPermitNumberSettingController::class, 'edit'])->middleware(RequireSensitiveActionConfirmation::class)->name('settings.permit-numbers.edit');
    Route::put('/settings/permit-numbers', [AdminPermitNumberSettingController::class, 'update'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:10,1'])->name('settings.permit-numbers.update');
    Route::get('/settings/document-templates', [AdminDocumentTemplateController::class, 'index'])->middleware(RequireSensitiveActionConfirmation::class)->name('settings.document-templates.index');
    Route::put('/settings/document-templates', [AdminDocumentTemplateController::class, 'update'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:10,1'])->name('settings.document-templates.update');
    Route::post('/settings/document-templates/test', [AdminDocumentTemplateController::class, 'test'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:3,1'])->name('settings.document-templates.test');
    Route::post('/settings/document-templates/upload', [AdminDocumentTemplateController::class, 'store'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:5,1'])->name('settings.document-templates.store');
    Route::patch('/settings/document-templates/{documentTemplate}/activate', [AdminDocumentTemplateController::class, 'activate'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:10,1'])->name('settings.document-templates.activate');
    Route::get('/settings/document-templates/{documentTemplate}/download', [AdminDocumentTemplateController::class, 'download'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:20,1'])->name('settings.document-templates.download');
    Route::put('/settings/monitoring', [AdminMonitoringSettingController::class, 'update'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:10,1'])->name('settings.monitoring.update');
    Route::post('/settings/monitoring/test', [AdminMonitoringSettingController::class, 'test'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:3,1'])->name('settings.monitoring.test');
    Route::get('/email-logs', [AdminEmailLogController::class, 'index'])->name('email-logs.index');
    Route::post('/email-logs/{emailLog}/retry', [AdminEmailLogController::class, 'retry'])->middleware('throttle:10,1')->name('email-logs.retry');
    Route::get('/failed-jobs', [AdminFailedJobController::class, 'index'])->name('failed-jobs.index');
    Route::get('/failed-jobs/{uuid}/retry', [AdminFailedJobController::class, 'confirm'])->whereUuid('uuid')->name('failed-jobs.confirm');
    Route::post('/failed-jobs/{uuid}/retry', [AdminFailedJobController::class, 'retry'])->whereUuid('uuid')->middleware('throttle:5,1')->name('failed-jobs.retry');

    Route::get('/loading', [AdminLoadingController::class, 'index'])->name('loading.index');
    Route::get('/loading-evidence', [LoadingGateEvidenceController::class, 'index'])->name('loading-evidence.index');
    Route::get('/loading-evidence/{securityGateEvent}', [LoadingGateEvidenceController::class, 'evidence'])->middleware('throttle:30,1')->name('loading-evidence.evidence');
    Route::get('/loading-documents/{documentToken}', [AdminLoadingController::class, 'document'])->middleware('signed')->where('documentToken', '[A-Za-z0-9]{64}')->name('loading.document');
    Route::get('/loading-documents/{documentToken}/preview', [AdminLoadingController::class, 'previewDocument'])->middleware('signed')->where('documentToken', '[A-Za-z0-9]{64}')->name('loading.document.preview');
    Route::get('/loading-instruction-letters/{documentToken}', [AdminLoadingController::class, 'instructionLetter'])->middleware('signed')->where('documentToken', '[A-Za-z0-9]{64}')->name('loading.instruction-letter');
    Route::get('/loading-instruction-letters/{documentToken}/preview', [AdminLoadingController::class, 'previewInstructionLetter'])->middleware('signed')->where('documentToken', '[A-Za-z0-9]{64}')->name('loading.instruction-letter.preview');
    Route::get('/loading/{permitNumber}', [AdminLoadingController::class, 'show'])->where('permitNumber', '.*')->name('loading.show');

    Route::get('/validators', [AdminValidatorController::class, 'index'])->middleware(RequireSensitiveActionConfirmation::class)->name('validators.index');
    Route::get('/validators/create', [AdminValidatorController::class, 'create'])->middleware(RequireSensitiveActionConfirmation::class)->name('validators.create');
    Route::post('/validators', [AdminValidatorController::class, 'store'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:10,1'])->name('validators.store');
    Route::post('/validators/check-availability', [AdminValidatorController::class, 'checkAvailability'])->middleware('throttle:60,1')->name('validators.availability');
    Route::get('/validators/{validator}/edit', [AdminValidatorController::class, 'edit'])->middleware(RequireSensitiveActionConfirmation::class)->name('validators.edit');
    Route::put('/validators/{validator}', [AdminValidatorController::class, 'update'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:20,1'])->name('validators.update');
    Route::post('/validators/{validator}/check-availability', [AdminValidatorController::class, 'checkAvailability'])->middleware('throttle:60,1')->name('validators.availability.edit');
    Route::patch('/validators/{validator}/status', [AdminValidatorController::class, 'updateStatus'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:20,1'])->name('validators.status');

    Route::get('/operating-hours', [AdminOperatingScheduleController::class, 'edit'])->middleware(RequireSensitiveActionConfirmation::class)->name('schedule.edit');
    Route::put('/operating-hours', [AdminOperatingScheduleController::class, 'update'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:10,1'])->name('schedule.update');
    Route::post('/operating-hours/exceptions', [AdminOperatingScheduleController::class, 'storeException'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:10,1'])->name('schedule.exceptions.store');
    Route::delete('/operating-hours/exceptions/{exception}', [AdminOperatingScheduleController::class, 'destroyException'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:10,1'])->name('schedule.exceptions.destroy');

    Route::get('/event-whatsapp-group', [AdminEventWhatsappGroupController::class, 'index'])->middleware(RequireSensitiveActionConfirmation::class)->name('event-whatsapp-group.index');
    Route::put('/event-whatsapp-group', [AdminEventWhatsappGroupController::class, 'update'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:10,1'])->name('event-whatsapp-group.update');
    Route::post('/event-whatsapp-group/sync', [AdminEventWhatsappGroupController::class, 'syncGroups'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:event-group-sync'])->name('event-whatsapp-group.sync');
    Route::post('/event-whatsapp-group/outbound-only', [AdminEventWhatsappGroupController::class, 'enableOutboundOnly'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:event-group-outbound'])->name('event-whatsapp-group.outbound-only');
    Route::put('/event-whatsapp-group/limiter', [AdminEventWhatsappGroupController::class, 'updateLimiter'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:10,1'])->name('event-whatsapp-group.limiter');
    Route::post('/event-whatsapp-group/members', [AdminEventWhatsappGroupController::class, 'storeMember'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:20,1'])->name('event-whatsapp-group.members.store');
    Route::delete('/event-whatsapp-group/members/{member}', [AdminEventWhatsappGroupController::class, 'destroyMember'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:20,1'])->name('event-whatsapp-group.members.destroy');
    Route::post('/event-whatsapp-group/rules', [AdminEventWhatsappGroupController::class, 'storeRule'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:20,1'])->name('event-whatsapp-group.rules.store');
    Route::delete('/event-whatsapp-group/rules/{rule}', [AdminEventWhatsappGroupController::class, 'destroyRule'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:20,1'])->name('event-whatsapp-group.rules.destroy');
    Route::post('/event-whatsapp-group/deliveries/{delivery}/retry', [AdminEventWhatsappGroupController::class, 'retry'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:5,1'])->name('event-whatsapp-group.deliveries.retry');

    Route::get('/settings/email', [AdminMailSettingController::class, 'edit'])->middleware(RequireSensitiveActionConfirmation::class)->name('settings.mail.edit');
    Route::put('/settings/email', [AdminMailSettingController::class, 'update'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:5,1'])->name('settings.mail.update');
    Route::post('/settings/email/test', [AdminMailSettingController::class, 'test'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:mail-tests'])->name('settings.mail.test');
    Route::get('/settings/scanner', [AdminScannerSettingController::class, 'edit'])->middleware(RequireSensitiveActionConfirmation::class)->name('settings.scanner.edit');
    Route::put('/settings/scanner', [AdminScannerSettingController::class, 'update'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:10,1'])->name('settings.scanner.update');
    Route::get('/settings/security', [AdminSecuritySettingController::class, 'edit'])->name('settings.security.edit');
    Route::put('/settings/security', [AdminSecuritySettingController::class, 'update'])->middleware('throttle:5,1')->name('settings.security.update');
    Route::get('/settings/upload-security', [AdminUploadSecuritySettingController::class, 'edit'])->middleware(RequireSensitiveActionConfirmation::class)->name('settings.upload-security.edit');
    Route::put('/settings/upload-security', [AdminUploadSecuritySettingController::class, 'update'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:5,1'])->name('settings.upload-security.update');
    Route::get('/settings/document-retention', [AdminDocumentRetentionSettingController::class, 'edit'])->middleware(RequireSensitiveActionConfirmation::class)->name('settings.retention.edit');
    Route::put('/settings/document-retention', [AdminDocumentRetentionSettingController::class, 'update'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:5,1'])->name('settings.retention.update');
    Route::post('/settings/document-retention/preview', [AdminDocumentRetentionSettingController::class, 'preview'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:10,1'])->name('settings.retention.preview');
    Route::get('/settings/sla', [AdminSlaSettingController::class, 'edit'])->name('settings.sla.edit');
    Route::put('/settings/sla', [AdminSlaSettingController::class, 'update'])->middleware('throttle:10,1')->name('settings.sla.update');
    Route::get('/settings/permit-limits', [AdminPermitSubmissionLimitController::class, 'edit'])->middleware(RequireSensitiveActionConfirmation::class)->name('settings.permit-limits.edit');
    Route::put('/settings/permit-limits', [AdminPermitSubmissionLimitController::class, 'update'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:10,1'])->name('settings.permit-limits.update');
    Route::get('/settings/whatsapp', [AdminWhatsappSettingController::class, 'edit'])->middleware(RequireSensitiveActionConfirmation::class)->name('settings.whatsapp.edit');
    Route::put('/settings/whatsapp', [AdminWhatsappSettingController::class, 'update'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:10,1'])->name('settings.whatsapp.update');
    Route::put('/settings/whatsapp/recipients', [AdminWhatsappSettingController::class, 'updateRecipients'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:10,1'])->name('settings.whatsapp.recipients');
    Route::post('/settings/whatsapp/test', [AdminWhatsappSettingController::class, 'test'])->middleware([RequireSensitiveActionConfirmation::class, 'throttle:whatsapp-tests'])->name('settings.whatsapp.test');

    Route::post('/notifications/clear', [StaffNotificationController::class, 'clear'])->name('notifications.clear');
    Route::post('/notifications/{notification}/read', [AdminNotificationController::class, 'read'])->name('notifications.read');
    Route::get('/notification-feed', StaffNotificationFeedController::class)->middleware('throttle:30,1')->name('notifications.feed');
    Route::middleware('throttle:20,1')->group(function () {
        Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
        Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');
    });
});
