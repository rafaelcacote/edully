<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdminGeral
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        $user = $request->user();
        // Carregar roles explicitamente para garantir que estão disponíveis
        $user->load('roles');

        // Verificar se é Administrador Geral - verificar tanto pelo método quanto pelos nomes das roles
        $roleNames = $user->roles->pluck('name')->toArray();
        $isAdminGeral = $user->hasRole('Administrador Geral') || in_array('Administrador Geral', $roleNames);

        if (! $isAdminGeral) {
            abort(403, 'Acesso negado. Apenas administradores gerais podem acessar esta área.');
        }

        return $next($request);
    }
}
