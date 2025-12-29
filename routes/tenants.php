<?php

use App\Http\Controllers\TenantsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('admin')->group(function () {
    Route::get('tenants', [TenantsController::class, 'index'])->name('tenants.index');
    Route::get('tenants/create', [TenantsController::class, 'create'])->name('tenants.create');
    Route::post('tenants', [TenantsController::class, 'store'])->name('tenants.store');
    Route::get('tenants/{tenant}', [TenantsController::class, 'show'])->name('tenants.show');
    Route::get('tenants/{tenant}/edit', [TenantsController::class, 'edit'])->name('tenants.edit');
    Route::patch('tenants/{tenant}', [TenantsController::class, 'update'])->name('tenants.update');
    Route::delete('tenants/{tenant}', [TenantsController::class, 'destroy'])->name('tenants.destroy');
});

