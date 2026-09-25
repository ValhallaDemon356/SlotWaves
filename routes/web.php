<?php

use App\Http\Controllers\UploadController;
use App\Http\Controllers\TimelineController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MasterDataViewController;
use App\Http\Controllers\DauComparisonController;
use App\Http\Controllers\FlightDailyReportController;
use App\Http\Controllers\Api\MasterDataController;
use Illuminate\Support\Facades\Route;

// ── Home / Dashboard / Upload Portal ───────────────────────────────────────
Route::get('/',                           [UploadController::class, 'index'])->name('home');
Route::get('/dashboard',                  [UploadController::class, 'dashboardRedirect'])->name('dashboard');
Route::get('/import',                     [UploadController::class, 'uploadPage'])->name('upload.index');
Route::get('/upload',                     [UploadController::class, 'uploadPage'])->name('upload.page');
Route::get('/reset',                      [UploadController::class, 'resetSession'])->name('schedule.reset');
Route::get('/new-schedule',               [UploadController::class, 'resetSession'])->name('schedule.new');
Route::post('/upload',                    [UploadController::class, 'store'])->name('upload.store');
Route::post('/upload/validate-template',  [UploadController::class, 'validateTemplate'])->name('upload.validate-template');
Route::post('/upload/compare-file',       [UploadController::class, 'uploadCompareFile'])->name('upload.compare-file');
Route::post('/upload/chunk',              [UploadController::class, 'uploadChunk'])->name('upload.chunk');
Route::get('/upload/{upload}/status',     [UploadController::class, 'status'])->name('upload.status');
Route::post('/upload/{upload}/process',   [UploadController::class, 'process'])->name('upload.process');

// ── DAU-02 Historical Comparison ───────────────────────────────────────────
Route::post('/dau/compare/validate',      [DauComparisonController::class, 'validateComparison'])->name('dau.compare.validate');
Route::get('/dau/compare',                [DauComparisonController::class, 'show'])->name('dau.compare');
Route::get('/dau/compare/export/pdf',     [DauComparisonController::class, 'exportPdf'])->name('dau.compare.export.pdf');
Route::get('/dau/compare/export/csv',     [DauComparisonController::class, 'exportCsv'])->name('dau.compare.export.csv');

// ── DAU Reference Templates Download ───────────────────────────────────────
Route::get('/templates/download/{reportType}', [\App\Http\Controllers\DauDashboardController::class, 'downloadTemplate'])->name('templates.download');

// ── DAU Analytical Dashboards & Exports ────────────────────────────────────
Route::get('/dau/{upload}', function($upload) {
    return redirect()->route('dau.dashboard', array_merge(['upload' => $upload], request()->query()));
});
Route::get('/dau-10a/{upload}', function($upload) {
    return redirect()->route('dau.dashboard', array_merge(['upload' => $upload], request()->query()));
});
Route::prefix('dau/{upload}')->group(function () {
    Route::get('/dashboard',    [\App\Http\Controllers\DauDashboardController::class, 'show'])->name('dau.dashboard');
    Route::match(['POST', 'PATCH'], '/operational-settings', [\App\Http\Controllers\DauDashboardController::class, 'saveOperationalSettings'])->name('dau.operational-settings.save');
    Route::get('/export/pdf',   [\App\Http\Controllers\DauDashboardController::class, 'exportPdf'])->name('dau.export.pdf');
    Route::get('/export/excel', [\App\Http\Controllers\DauDashboardController::class, 'exportExcel'])->name('dau.export.excel');
});

// ── Flight Daily Report (FDR) Analytical Intelligence ───────────────────────
Route::prefix('fdr')->name('fdr.')->group(function () {
    Route::get('/',                           [FlightDailyReportController::class, 'configRedirect'])->name('index');
    Route::get('/config/{upload?}',           [FlightDailyReportController::class, 'config'])->name('config');
    Route::post('/upload',                    [FlightDailyReportController::class, 'store'])->name('upload');
    Route::post('/use-reference',             [FlightDailyReportController::class, 'useReference'])->name('use-reference');
    Route::get('/{upload}/dashboard',         [FlightDailyReportController::class, 'dashboard'])->name('dashboard');
    Route::get('/{upload}/filter',            [FlightDailyReportController::class, 'filterApi'])->name('filter');
    Route::get('/{upload}/flight/{flightIndex}', [FlightDailyReportController::class, 'flightDetails'])->name('flight-details');
    Route::get('/{upload}/export/csv',        [FlightDailyReportController::class, 'exportCsv'])->name('export.csv');
    Route::get('/{upload}/export/pdf',        [FlightDailyReportController::class, 'exportPdf'])->name('export.pdf');
});

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
