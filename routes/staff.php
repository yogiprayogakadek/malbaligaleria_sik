<?php

use App\Http\Controllers\PermitRevisionController;
use App\Http\Controllers\PermitRevocationController;
use App\Http\Controllers\StaffWorkPermitController;
use Illuminate\Support\Facades\Route;

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
