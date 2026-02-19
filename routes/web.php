<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
})->name('home');

// Rota temporária para limpar sessão e fazer logout forçado
Route::get('/force-logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login');
})->name('force-logout');

Route::get('/welcome', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->middleware('guest')->name('welcome');

Route::get('dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Rota pública para buscar escolas por CPF (antes do login)
Route::get('api/auth/tenants-by-cpf', [\App\Http\Controllers\Auth\TenantController::class, 'getByCpf'])
    ->name('api.auth.tenants-by-cpf');

require __DIR__.'/settings.php';
require __DIR__.'/users.php';
require __DIR__.'/tenants.php';
require __DIR__.'/roles.php';
require __DIR__.'/permissions.php';
require __DIR__.'/plans.php';
require __DIR__.'/subscriptions.php';
require __DIR__.'/audit-logs.php';
require __DIR__.'/school.php';
