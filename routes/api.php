<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\JadwalController;
use Illuminate\Support\Facades\Route;

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);

    // Jadwal CRUD & highlight
    Route::get('jadwals/highlight', [JadwalController::class, 'highlight']);
    Route::apiResource('jadwals', JadwalController::class);

    // Timer
    Route::post('/jadwals/{id}/start-timer', [JadwalController::class, 'startTimer']);
    Route::post('/jadwals/{id}/stop-timer', [JadwalController::class, 'stopTimer']);
});

Route::get('/ping', function () {
    return response()->json(['message' => 'Laravel is working!']);
});
