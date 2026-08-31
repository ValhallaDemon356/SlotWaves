<?php

use App\Http\Controllers\Api\MasterDataController;
use Illuminate\Support\Facades\Route;

// ── Master Reference Data API Endpoints ─────────────────────────────────────
Route::get('/airports', [MasterDataController::class, 'airports'])->name('api.airports');
Route::get('/airlines', [MasterDataController::class, 'airlines'])->name('api.airlines');
Route::get('/flights',  [MasterDataController::class, 'flights'])->name('api.flights');

// ── Upload Lifecycle API Endpoints ──────────────────────────────────────────
Route::get('/upload/{upload}/status',   [\App\Http\Controllers\UploadController::class, 'status'])->name('api.upload.status');
Route::post('/upload/{upload}/process', [\App\Http\Controllers\UploadController::class, 'process'])->name('api.upload.process');
