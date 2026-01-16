<?php

use App\Http\Controllers\MessagesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('messages')->name('messages.')->group(function () {
    Route::get('/', [MessagesController::class, 'index'])->name('index');
    Route::get('/create', [MessagesController::class, 'create'])->name('create');
    Route::post('/', [MessagesController::class, 'store'])->name('store');
    Route::get('/{message}/edit', [MessagesController::class, 'edit'])->name('edit');
    Route::patch('/{message}', [MessagesController::class, 'update'])->name('update');
    Route::post('/{message}/mark-as-read', [MessagesController::class, 'markAsRead'])->name('mark-as-read');
    Route::delete('/{message}', [MessagesController::class, 'destroy'])->name('destroy');
});
