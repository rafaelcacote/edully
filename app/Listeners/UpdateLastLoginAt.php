<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Session;

class UpdateLastLoginAt
{
    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        $user = $event->user;
        $user->update(['last_login_at' => now()]);

        // Garantir que o tenant_id seja salvo na sessão após o login
        // Carregar roles e tenants explicitamente
        $user->load('roles', 'tenants');

        // Verificar se é Administrador Geral - verificar tanto pelo método quanto pelos nomes das roles
        $roleNames = $user->roles->pluck('name')->toArray();
        $isAdminGeral = $user->hasRole('Administrador Geral') || in_array('Administrador Geral', $roleNames);

        if (! $isAdminGeral) {
            $tenantId = Session::get('tenant_id');

            // Se não tem tenant_id na sessão e o usuário tem apenas um tenant, usar esse
            if (! $tenantId && $user->tenants->count() === 1) {
                $tenantId = $user->tenants->first()->id;
                Session::put('tenant_id', $tenantId);
            }
        }
    }
}
