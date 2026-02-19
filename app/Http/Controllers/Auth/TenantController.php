<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    /**
     * Get tenants for a user by CPF (before login).
     */
    public function getByCpf(Request $request): JsonResponse
    {
        $request->validate([
            'cpf' => ['required', 'string'],
        ]);

        $cpf = preg_replace('/[^0-9]/', '', $request->query('cpf') ?? $request->input('cpf', ''));

        if (empty($cpf)) {
            return response()->json(['tenants' => []]);
        }

        $user = User::where('cpf', $cpf)->first();

        if (! $user) {
            return response()->json(['tenants' => []]);
        }

        $user->load('tenants');
        $tenants = $user->tenants->map(fn ($tenant) => [
            'id' => $tenant->id,
            'name' => $tenant->nome,
        ])->toArray();

        return response()->json([
            'tenants' => $tenants,
            'is_admin_geral' => $user->hasRole('Administrador Geral'),
        ]);
    }
}
