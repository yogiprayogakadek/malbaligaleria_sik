<?php

use App\Http\Controllers\EventPermitController;
use App\Http\Controllers\FormPresenceController;
use App\Http\Controllers\LoadingPermitController;
use App\Http\Controllers\PermitController;
use App\Http\Controllers\PermitDraftController;
use App\Http\Controllers\PermitRevisionApplicantController;
use App\Http\Controllers\WorkPermitApplicantController;
use App\Http\Controllers\WorkPermitController;
use App\Http\Middleware\EnsureWebsiteOperating;
use App\Http\Middleware\MonitorPermitSubmission;
use Illuminate\Support\Facades\Route;

Route::prefix('form-presence')->name('form-presence.')->middleware('throttle:form-presence')->group(function () {
    Route::post('/{type}/heartbeat', [FormPresenceController::class, 'heartbeat'])
        ->whereIn('type', ['loading', 'work', 'event'])
        ->name('heartbeat');
    Route::post('/{type}/leave', [FormPresenceController::class, 'leave'])
        ->whereIn('type', ['loading', 'work', 'event'])
        ->name('leave');
});

Route::controller(PermitController::class)->prefix('permits')->name('permits.')->middleware(EnsureWebsiteOperating::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/store', 'store')->name('store');
    Route::get('/success', 'success')->name('success');
});

Route::prefix('loading')->name('loading.')->group(function () {
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
