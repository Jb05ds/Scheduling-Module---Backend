<?php

use App\Http\Controllers\ScheduleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/schedules', [ScheduleController::class, 'store']);

Route::get('/schedules', [ScheduleController::class, 'index']);

Route::get('/schedules/{schedule}', [ScheduleController::class, 'show']);

Route::put('/schedules/{schedule}', [ScheduleController::class, 'update']);

Route::patch('/schedules/{schedule}/cancel', [ScheduleController::class, 'cancel']);

Route::delete('/schedules/{schedule}', [ScheduleController::class, 'destroy']);