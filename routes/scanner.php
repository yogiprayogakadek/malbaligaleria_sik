<?php

use App\Http\Controllers\ScannerController;
use App\Http\Controllers\ScannerGateController;
use App\Http\Controllers\ScannerOfficerSessionController;
use Illuminate\Support\Facades\Route;

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
