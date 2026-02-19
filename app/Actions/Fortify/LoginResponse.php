<?php

namespace App\Actions\Fortify;

use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request)
    {
        $user = $request->user();

        if ($user) {
            // Carregar roles e tenants explicitamente
            $user->load('roles', 'tenants');

            // Verificar se é Administrador Geral - verificar tanto pelo método quanto pelos nomes das roles
            $roleNames = $user->roles->pluck('name')->toArray();
            $isAdminGeral = $user->hasRole('Administrador Geral') || in_array('Administrador Geral', $roleNames);

            // Se o usuário não é admin geral, garantir que o tenant_id esteja na sessão
            if (! $isAdminGeral) {
                $tenantId = $request->session()->get('tenant_id');

                // Se não tem tenant_id na sessão, tentar pegar da chave temporária
                if (! $tenantId) {
                    $tenantId = $request->session()->get('_pending_tenant_id');
                }

                // Se ainda não tem, tentar pegar do request
                if (! $tenantId) {
                    $tenantId = $request->input('tenant_id');
                }

                // Se ainda não tem e o usuário tem apenas um tenant, usar esse
                if (! $tenantId && $user->tenants->count() === 1) {
                    $tenantId = $user->tenants->first()->id;
                }

                // Salvar na sessão se tiver um tenant_id válido
                if ($tenantId) {
                    $hasAccess = $user->tenants()->where('tenants.id', $tenantId)->exists();
                    if ($hasAccess) {
                        $request->session()->put('tenant_id', $tenantId);
                        // Remover a chave temporária
                        $request->session()->forget('_pending_tenant_id');
                        // Garantir que a sessão seja salva
                        $request->session()->save();
                    }
                }
            }
        }

        return redirect()->intended(route('dashboard'));
    }
}
