<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PermitController;
use App\Http\Controllers\LoadingPermitController;
use App\Http\Controllers\TRController;
use App\Http\Controllers\ScannerController;
use App\Http\Middleware\EnsureTRValidator;

/*
|--------------------------------------------------------------------------
| Web Routes — Sistem Informasi Surat Izin Mal Bali Galeria
|--------------------------------------------------------------------------
*/

// ─── Autentikasi ───────────────────────────────────────────────────────────
Route::controller(AuthController::class)->group(function () {
    Route::get('/login', 'showLogin')->name('login');
    Route::post('/login', 'login')->name('login.post');
    Route::get('/register', 'showRegister')->name('register');
    Route::post('/register', 'register')->name('register.post');
    Route::match(['get', 'post'], '/logout', 'logout')->name('logout');
});

// ─── Portal Beranda ────────────────────────────────────────────────────────
Route::controller(PortalController::class)->group(function () {
    Route::get('/', 'index')->name('portal.dashboard');
    Route::get('/help', 'help')->name('portal.help');
    Route::match(['get', 'post'], '/track', 'track')->name('portal.track')->middleware('throttle:30,1');
});

// ─── Permit Legacy (generic form — work, exhibition, event) ───────────────────
Route::controller(PermitController::class)->prefix('permits')->name('permits.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/store', 'store')->name('store');
    Route::get('/success', 'success')->name('success');
});

// ─── Permohonan Loading In/Out ─────────────────────────────────────────────
Route::prefix('loading')->name('loading.')->group(function () {
    // Public: track status via nomor surat
    Route::post('/track', [LoadingPermitController::class, 'track'])->name('track');
    Route::get('/track/{permitNumber}', [LoadingPermitController::class, 'show'])->name('show')->where('permitNumber', '.*');

    // Formulir (public — guest boleh isi, login untuk auto-fill)
    Route::get('/create', [LoadingPermitController::class, 'create'])->name('create');
    Route::post('/store', [LoadingPermitController::class, 'store'])->name('store');
    Route::get('/success/{permitNumber}', [LoadingPermitController::class, 'success'])->name('success')->where('permitNumber', '.*');

    // Surat resmi yang sudah approved
    Route::get('/letter/{permitNumber}', [LoadingPermitController::class, 'downloadLetter'])->name('letter')->where('permitNumber', '.*');
});

// ─── Divisi TR (Tenant Relationship) — Validator ──────────────────────────
Route::prefix('tr')->name('tr.')->middleware(['auth', EnsureTRValidator::class])->group(function () {
    Route::get('/', [TRController::class, 'index'])->name('index');
    Route::post('/{permitNumber}/approve', [TRController::class, 'approve'])->name('approve')->where('permitNumber', '.*');
    Route::post('/{permitNumber}/reject', [TRController::class, 'reject'])->name('reject')->where('permitNumber', '.*');
    Route::get('/{permitNumber}/id-doc', [TRController::class, 'viewIdDoc'])->name('id-doc')->where('permitNumber', '.*');
    Route::get('/{permitNumber}', [TRController::class, 'show'])->name('show')->where('permitNumber', '.*');
});

// ─── Scanner QR (Publik, Mobile) ───────────────────────────────────────────
Route::get('/scanner', [ScannerController::class, 'index'])->name('scanner.index');
Route::get('/scanner/verify', [ScannerController::class, 'verify'])->name('scanner.verify');
