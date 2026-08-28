<?php

use App\Http\Controllers\Api\MasterDataController;
use Illuminate\Support\Facades\Route;

// ── Master Reference Data API Endpoints ─────────────────────────────────────
Route::get('/airports', [MasterDataController::class, 'airports'])->name('api.airports');
Route::get('/airlines', [MasterDataController::class, 'airlines'])->name('api.airlines');
Route::get('/flights',  [MasterDataController::class, 'flights'])->name('api.flights');
