<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AvisosController;
use App\Http\Controllers\Api\BoletinsController;
use App\Http\Controllers\Api\ExercisesController;
use App\Http\Controllers\Api\MessagesController;
use App\Http\Controllers\Api\NotasController;
use App\Http\Controllers\Api\ProfessoresController;
use App\Http\Controllers\Api\PushTokensController;
use App\Http\Controllers\Api\StudentsController;
use App\Http\Controllers\Api\TeacherController;
use App\Http\Controllers\Api\TestsController;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile')->name('mobile.')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('me/foto', [AuthController::class, 'updateFoto'])->name('me.foto');

        // Push tokens (Expo)
        Route::post('push-tokens', [PushTokensController::class, 'store'])->name('push-tokens.store');
        Route::delete('push-tokens', [PushTokensController::class, 'destroy'])->name('push-tokens.destroy');

        // Students endpoints (only for responsaveis)
        Route::get('students', [StudentsController::class, 'index'])->name('students.index');
        Route::get('students/{id}', [StudentsController::class, 'show'])->name('students.show');
        Route::get('students/{id}/boletim', [BoletinsController::class, 'show'])->name('students.boletim');
        Route::get('students/{id}/notas', [NotasController::class, 'index'])->name('students.notas');
        Route::get('students/{id}/professores', [ProfessoresController::class, 'index'])->name('students.professores');

        // Avisos endpoints (school notices for students / responsaveis)
        Route::get('avisos', [AvisosController::class, 'index'])->name('avisos.index');
        Route::get('avisos/{id}', [AvisosController::class, 'show'])->name('avisos.show');

        // Messages endpoints
        Route::get('messages', [MessagesController::class, 'index'])->name('messages.index');
        Route::get('messages/conversas/{conversaId}', [MessagesController::class, 'showConversa'])->name('messages.conversas.show');
        Route::patch('messages/conversas/{conversaId}/read', [MessagesController::class, 'markConversaAsRead'])->name('messages.conversas.mark-as-read');
        Route::get('messages/{id}', [MessagesController::class, 'show'])->name('messages.show');
        Route::post('messages', [MessagesController::class, 'store'])->name('messages.store');
        Route::patch('messages/{id}/read', [MessagesController::class, 'markAsRead'])->name('messages.mark-as-read');
        Route::delete('messages/{id}', [MessagesController::class, 'destroy'])->name('messages.destroy');

        // Exercises endpoints
        Route::get('exercises', [ExercisesController::class, 'index'])->name('exercises.index');
        Route::get('exercises/{id}', [ExercisesController::class, 'show'])->name('exercises.show');
        Route::post('exercises', [ExercisesController::class, 'store'])->name('exercises.store');
        Route::put('exercises/{id}', [ExercisesController::class, 'update'])->name('exercises.update');
        Route::delete('exercises/{id}', [ExercisesController::class, 'destroy'])->name('exercises.destroy');

        // Tests endpoints
        Route::get('tests', [TestsController::class, 'index'])->name('tests.index');
        Route::get('tests/{id}', [TestsController::class, 'show'])->name('tests.show');
        Route::post('tests', [TestsController::class, 'store'])->name('tests.store');
        Route::put('tests/{id}', [TestsController::class, 'update'])->name('tests.update');
        Route::delete('tests/{id}', [TestsController::class, 'destroy'])->name('tests.destroy');

        // Teacher endpoints
        Route::prefix('teacher')->name('teacher.')->group(function () {
            Route::get('turmas', [TeacherController::class, 'turmas'])->name('turmas');
            Route::get('disciplinas', [TeacherController::class, 'disciplinas'])->name('disciplinas');
            Route::get('alunos', [TeacherController::class, 'alunos'])->name('alunos');
            Route::get('turmas/{id}/alunos', [TeacherController::class, 'turmasAlunos'])->name('turmas.alunos');
        });
    });
});
