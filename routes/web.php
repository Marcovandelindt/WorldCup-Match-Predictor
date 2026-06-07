<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PredictionController;
use App\Http\Controllers\ResultController;
use App\Http\Controllers\StatisticsController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/predict/{match}', [PredictionController::class, 'show'])->name('predict.show');
Route::post('/predict/{match}', [PredictionController::class, 'generate'])->name('predict.generate');
Route::get('/results', [ResultController::class, 'index'])->name('results.index');
Route::get('/statistics', [StatisticsController::class, 'index'])->name('statistics.index');
