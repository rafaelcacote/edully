<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
        $this->configureAuthentication();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);

        // Registrar a resposta customizada de login
        $this->app->singleton(
            \Laravel\Fortify\Contracts\LoginResponse::class,
            \App\Actions\Fortify\LoginResponse::class
        );
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/Login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'canRegister' => Features::enabled(Features::registration()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/Register'));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/TwoFactorChallenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $identifier = $request->input(Fortify::username());
            // Normalize identifier for rate limiting (email or CPF)
            $normalized = filter_var($identifier, FILTER_VALIDATE_EMAIL)
                ? strtolower($identifier)
                : preg_replace('/[^0-9]/', '', $identifier);
            $throttleKey = Str::transliterate($normalized.'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }

    /**
     * Configure authentication.
     */
    private function configureAuthentication(): void
    {
        Fortify::authenticateUsing(function (Request $request) {
            $identifier = $request->input(Fortify::username());

            // Check if identifier is email or CPF
            $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL);

            $user = $isEmail
                ? \App\Models\User::where('email', strtolower($identifier))->first()
                : \App\Models\User::where('cpf', preg_replace('/[^0-9]/', '', $identifier))->first();

            if ($user && \Illuminate\Support\Facades\Hash::check($request->password, $user->password_hash)) {
                // Verificar se o usuário está ativo
                if (! $user->ativo) {
                    return null;
                }

                // Carregar roles e tenants explicitamente
                $user->load('roles', 'tenants');

                // Verificar se é Administrador Geral - verificar tanto pelo método quanto pelos nomes das roles
                $roleNames = $user->roles->pluck('name')->toArray();
                $isAdminGeral = $user->hasRole('Administrador Geral') || in_array('Administrador Geral', $roleNames);

                // Se o usuário não é admin geral, verificar se tem tenant selecionado
                if (! $isAdminGeral) {
                    $tenantId = $request->input('tenant_id');

                    // Se não tem tenants, não permitir login
                    if ($user->tenants->count() === 0) {
                        return null;
                    }

                    // Se não tem tenant selecionado, verificar se tem apenas um tenant
                    if (! $tenantId && $user->tenants->count() === 1) {
                        $tenantId = $user->tenants->first()->id;
                    }

                    // Se ainda não tem tenant e tem múltiplos, não permitir login
                    if (! $tenantId && $user->tenants->count() > 1) {
                        return null;
                    }

                    // Se tem tenant selecionado, verificar se o usuário tem acesso
                    if ($tenantId) {
                        $hasAccess = $user->tenants()->where('tenants.id', $tenantId)->exists();

                        if ($hasAccess) {
                            // Armazenar o tenant selecionado na sessão ANTES do login
                            // Isso garante que será preservado mesmo após regeneração da sessão
                            $request->session()->put('tenant_id', $tenantId);
                            // Também armazenar em uma chave temporária para garantir persistência
                            $request->session()->put('_pending_tenant_id', $tenantId);
                        } else {
                            // Tenant inválido para o usuário
                            return null;
                        }
                    }
                }

                return $user;
            }
        });
    }
}
