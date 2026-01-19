<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Handle a login request for mobile API.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $cpf = preg_replace('/[^0-9]/', '', $request->input('cpf'));
        $password = $request->input('password');

        $user = User::where('cpf', $cpf)->first();

        if (!$user || !Hash::check($password, $user->password_hash)) {
            throw ValidationException::withMessages([
                'cpf' => ['As credenciais fornecidas estão incorretas.'],
            ]);
        }

        // Verificar se o usuário está ativo
        if (!$user->ativo) {
            throw ValidationException::withMessages([
                'cpf' => ['Sua conta está desativada. Entre em contato com o administrador.'],
            ]);
        }

        // Verificar se o usuário é professor ou responsável e se está ativo
        $teacher = $user->teacher()->where('ativo', true)->first();
        $responsavel = $user->responsavel()->first();

        if (!$teacher && !$responsavel) {
            throw ValidationException::withMessages([
                'cpf' => ['Acesso negado. Apenas professores e responsáveis podem acessar o aplicativo móvel.'],
            ]);
        }

        // Se é professor, verificar se o registro de professor está ativo
        if ($teacher && !$teacher->ativo) {
            throw ValidationException::withMessages([
                'cpf' => ['Seu cadastro de professor está desativado. Entre em contato com o administrador.'],
            ]);
        }

        // Atualizar last_login_at
        $user->update(['last_login_at' => now()]);

        // Criar token de autenticação
        $token = $user->createToken('mobile-app')->plainTextToken;

        // Determinar o tipo de usuário
        $userType = $teacher ? 'teacher' : 'responsavel';

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'nome_completo' => $user->nome_completo,
                'email' => $user->email,
                'cpf' => $user->cpf,
                'telefone' => $user->telefone,
                'avatar_url' => $user->avatar_url,
                'type' => $userType,
            ],
        ]);
    }

    /**
     * Get the authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $teacher = $user->teacher()->where('ativo', true)->first();
        $responsavel = $user->responsavel()->first();

        $userType = $teacher ? 'teacher' : 'responsavel';

        // Buscar escolas (tenants) do usuário
        $schools = [];
        if ($responsavel) {
            // Para responsáveis, buscar todas as escolas onde tem alunos vinculados
            $responsaveis = \App\Models\Responsavel::where('usuario_id', $user->id)
                ->with(['tenant:id,nome,logo_url'])
                ->get();
            
            $schools = $responsaveis->map(function ($resp) {
                return $resp->tenant ? [
                    'id' => $resp->tenant->id,
                    'nome' => $resp->tenant->nome,
                    'logo_url' => $resp->tenant->logo_url,
                ] : null;
            })->filter()->unique('id')->values();
        } elseif ($teacher) {
            // Para professores, buscar a escola do professor
            $teacher->load(['tenant:id,nome,logo_url']);
            if ($teacher->tenant) {
                $schools = [[
                    'id' => $teacher->tenant->id,
                    'nome' => $teacher->tenant->nome,
                    'logo_url' => $teacher->tenant->logo_url,
                ]];
            }
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'nome_completo' => $user->nome_completo,
                'email' => $user->email,
                'cpf' => $user->cpf,
                'telefone' => $user->telefone,
                'avatar_url' => $user->avatar_url,
                'type' => $userType,
            ],
            'schools' => $schools,
        ]);
    }

    /**
     * Handle a logout request.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout realizado com sucesso.',
        ]);
    }
}
