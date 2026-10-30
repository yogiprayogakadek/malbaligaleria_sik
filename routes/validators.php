<?php

use App\Http\Controllers\EPController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\LoadingGateEvidenceController;
use App\Http\Controllers\MEPController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\SecretaryController;
use App\Http\Controllers\SecurityController;
use App\Http\Controllers\StaffNotificationController;
use App\Http\Controllers\StaffNotificationFeedController;
use App\Http\Controllers\TRController;
use App\Http\Middleware\EnsureEPValidator;
use App\Http\Middleware\EnsureFinanceValidator;
use App\Http\Middleware\EnsureMEPValidator;
use App\Http\Middleware\EnsureSecretary;
use App\Http\Middleware\EnsureSecurityValidator;
use App\Http\Middleware\EnsureTRValidator;
use Illuminate\Support\Facades\Route;

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
    Route::get('/loading-evidence', [LoadingGateEvidenceController::class, 'index'])->name('loading-evidence.index');
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
    Route::post('/notifications/{notification}/read', [MEPController::class, 'readNotification'])->name('notifications.read');
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
    Route::post('/notifications/{notification}/read', [SecretaryController::class, 'readNotification'])->name('notifications.read');
    Route::get('/notification-feed', StaffNotificationFeedController::class)
        ->middleware('throttle:30,1')
        ->name('notifications.feed');
    Route::middleware('throttle:20,1')->group(function () {
        Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
        Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');
    });
});

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
