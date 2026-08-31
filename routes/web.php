<?php

use App\Http\Controllers\UploadController;
use App\Http\Controllers\TimelineController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MasterDataViewController;
use App\Http\Controllers\Api\MasterDataController;
use Illuminate\Support\Facades\Route;

// ── Home / Dashboard / Upload Portal ───────────────────────────────────────
Route::get('/',                        [UploadController::class, 'index'])->name('home');
Route::get('/dashboard',               [UploadController::class, 'dashboardRedirect'])->name('dashboard');
Route::get('/import',                  [UploadController::class, 'uploadPage'])->name('upload.index');
Route::get('/upload',                  [UploadController::class, 'uploadPage'])->name('upload.page');
Route::get('/reset',                   [UploadController::class, 'resetSession'])->name('schedule.reset');
Route::get('/new-schedule',            [UploadController::class, 'resetSession'])->name('schedule.new');
Route::post('/upload',                 [UploadController::class, 'store'])->name('upload.store');
Route::get('/upload/{upload}/status',  [UploadController::class, 'status'])->name('upload.status');
Route::post('/upload/{upload}/process',[UploadController::class, 'process'])->name('upload.process');

// ── Master Reference Data Web View ─────────────────────────────────────────
Route::get('/master-data', [MasterDataViewController::class, 'index'])->name('master-data.index');

// ── Timeline ───────────────────────────────────────────────────────────────
Route::get('/timeline/{upload}',             [TimelineController::class, 'show'])->name('timeline.show');
Route::get('/timeline/{upload}/pdf',         [TimelineController::class, 'pdf'])->name('timeline.pdf');
Route::patch('/timeline-position/{position}',[TimelineController::class, 'updatePosition'])->name('timeline-position.update');
Route::match(['POST', 'PATCH'], '/timeline/{upload}/ops-hours', [TimelineController::class, 'saveOpsHours'])->name('timeline.ops-hours.save');

// ── Generated File Dashboard & Reports ────────────────────────────────────
Route::prefix('schedule/{upload}')->group(function () {
    Route::get('/dashboard',        [DashboardController::class, 'show'])->name('schedule.dashboard');
    Route::match(['POST', 'PATCH'], '/ops-hours', [TimelineController::class, 'saveOpsHours'])->name('schedule.ops-hours.save');
    Route::match(['POST', 'PATCH'], '/operational-settings', [DashboardController::class, 'saveOperationalSettings'])->name('schedule.operational-settings.save');
    Route::get('/preview/combined',       [DashboardController::class, 'previewCombined'])->name('schedule.preview.combined');
    Route::get('/preview/time',           [DashboardController::class, 'previewTime'])->name('schedule.preview.time');
    Route::get('/preview/dos',            [DashboardController::class, 'previewDos'])->name('schedule.preview.dos');
    Route::get('/report/download',        [DashboardController::class, 'downloadCombined'])->name('schedule.report.download');
    Route::get('/report/daily-movements', [DashboardController::class, 'downloadDailyMovements'])->name('schedule.report.daily-movements');
});

// ── REST API Routes ────────────────────────────────────────────────────────
Route::prefix('api')->group(function () {
    Route::get('/airports', [MasterDataController::class, 'airports']);
    Route::get('/airlines', [MasterDataController::class, 'airlines']);
    Route::get('/flights',  [MasterDataController::class, 'flights']);
});
