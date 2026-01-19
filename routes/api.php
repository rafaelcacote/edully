<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StudentsController;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile')->name('mobile.')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->name('login');
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('me', [AuthController::class, 'me'])->name('me');
        
        // Students endpoints (only for responsaveis)
        Route::get('students', [StudentsController::class, 'index'])->name('students.index');
        Route::get('students/{id}', [StudentsController::class, 'show'])->name('students.show');
    });
});
