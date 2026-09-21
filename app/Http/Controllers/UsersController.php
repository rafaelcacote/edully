<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UsersController extends Controller
{
    /**
     * Display a listing of the users.
     */
    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'role', 'active', 'tenant_id']);

        $users = User::query()
            ->with('roles:id,name', 'tenants:id,nome')
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $search = trim($search);
                // Remove formatação do CPF para busca
                $cpfSearch = preg_replace('/[^0-9]/', '', $search);
                $query->where(function ($q) use ($search, $cpfSearch) {
                    $q->where('nome_completo', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%")
                        ->orWhere('telefone', 'ilike', "%{$search}%")
                        ->orWhere('cpf', 'ilike', "%{$cpfSearch}%");
                });
            })
            ->when($filters['role'] ?? null, function ($query, string $role) {
                $query->whereHas('roles', function ($q) use ($role) {
                    $q->where('name', $role);
                });
            })
            ->when($filters['tenant_id'] ?? null, function ($query, string $tenantId) {
                $query->whereHas('tenants', function ($q) use ($tenantId) {
                    $q->whereKey($tenantId);
                });
            })
            ->when(isset($filters['active']) && $filters['active'] !== '' && $filters['active'] !== null, function ($query) use ($filters) {
                $active = filter_var($filters['active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($active !== null) {
                    $query->where('ativo', $active);
                }
            })
            ->orderBy('nome_completo')
            ->paginate(10)
            ->withQueryString();

        // Transformar os dados para incluir o primeiro role como role principal
        // e garantir que os campos da tabela estejam presentes
        $users->getCollection()->transform(function ($user) {
            $user->role = $user->roles->first()?->name ?? null;
            // Garantir que os campos da tabela estejam presentes
            $user->nome_completo = $user->attributes['nome_completo'] ?? $user->full_name ?? '';
            $user->telefone = $user->attributes['telefone'] ?? $user->phone ?? null;
            $user->ativo = $user->attributes['ativo'] ?? $user->is_active ?? true;
            // Adicionar o primeiro tenant (escola) vinculado ao usuário
            $user->tenant = $user->tenants->first();
            $user->tenant_nome = $user->tenants->first()?->nome ?? null;

            return $user;
        });

        return Inertia::render('users/Index', [
            'users' => $users,
            'filters' => $filters,
            'roles' => Role::query()->orderBy('name')->pluck('name')->toArray(),
            'tenants' => Tenant::query()->orderBy('nome')->get(['id', 'nome'])->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->nome,
            ])->toArray(),
        ]);
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(): Response
    {
        return Inertia::render('users/Create', [
            'roles' => Role::query()->orderBy('name')->pluck('name')->toArray(),
            'tenants' => Tenant::query()->orderBy('nome')->get(['id', 'nome'])->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->nome,
            ])->toArray(),
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'nome_completo' => ['required', 'string', 'max:255'],
            'cpf' => ['required', 'string', 'regex:/^[0-9]{11}$|^[0-9]{3}\.[0-9]{3}\.[0-9]{3}-[0-9]{2}$/', Rule::unique(User::class, 'cpf')],
            'role' => ['required', 'string', 'max:255', Rule::exists(Role::class, 'name')],
            'telefone' => ['nullable', 'string', 'max:20'],
            'tenant_id' => ['nullable', 'string', Rule::exists(Tenant::class, 'id')],
            'avatar_url' => ['nullable', 'string', 'max:2048'],
            'ativo' => ['nullable', 'boolean'],
        ]);

        // Remove formatação do CPF (pontos, traços, espaços)
        $cpf = preg_replace('/[^0-9]/', '', $validated['cpf']);

        $user = new User;
        $user->email = $validated['email'];
        $user->nome_completo = $validated['nome_completo'];
        $user->cpf = $cpf;
        $user->telefone = $validated['telefone'] ?? null;
        $user->avatar_url = $validated['avatar_url'] ?? null;
        $user->ativo = isset($validated['ativo'])
            ? filter_var($validated['ativo'], FILTER_VALIDATE_BOOLEAN)
            : true;
        // Senha padrão é o CPF do usuário
        $user->password = $cpf;
        $user->save();

        // Atribui o perfil do Spatie Permission
        $role = Role::where('name', $validated['role'])->first();
        if ($role) {
            $user->assignRole($role);
        }

        // Atribui o tenant através da tabela de relacionamento
        if (! empty($validated['tenant_id'])) {
            $user->tenants()->sync([$validated['tenant_id']]);
        }

        return redirect()
            ->route('users.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Usuário criado',
                'message' => 'O usuário foi cadastrado com sucesso.',
            ]);
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $user): Response
    {
        $user->load('roles:id,name', 'tenants:id,nome');

        // Adiciona o primeiro role como role principal para compatibilidade
        $user->role = $user->roles->first()?->name ?? null;

        // Garantir que os campos da tabela estejam presentes
        $user->nome_completo = $user->attributes['nome_completo'] ?? $user->full_name ?? '';
        $user->telefone = $user->attributes['telefone'] ?? $user->phone ?? null;
        $user->ativo = $user->attributes['ativo'] ?? $user->is_active ?? true;

        // Adiciona o primeiro tenant como tenant_id
        // Usar array_merge para garantir que seja incluído na serialização
        $userData = $user->toArray();
        $userData['tenant_id'] = $user->tenants->first()?->id ?? null;
        $userData['role'] = $user->role;

        return Inertia::render('users/Edit', [
            'user' => $userData,
            'roles' => Role::query()->orderBy('name')->pluck('name')->toArray(),
            'tenants' => Tenant::query()->orderBy('nome')->get(['id', 'nome'])->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->nome,
            ])->toArray(),
        ]);
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore($user->id, 'id')],
            'nome_completo' => ['required', 'string', 'max:255'],
            'cpf' => ['required', 'string', 'regex:/^[0-9]{11}$|^[0-9]{3}\.[0-9]{3}\.[0-9]{3}-[0-9]{2}$/', Rule::unique(User::class, 'cpf')->ignore($user->id, 'id')],
            'role' => ['required', 'string', 'max:255', Rule::exists(Role::class, 'name')],
            'telefone' => ['nullable', 'string', 'max:20'],
            'tenant_id' => ['nullable', 'string', Rule::exists(Tenant::class, 'id')],
            'avatar_url' => ['nullable', 'string', 'max:2048'],
            'ativo' => ['nullable', 'boolean'],
        ]);

        // Remove formatação do CPF (pontos, traços, espaços)
        $cpf = preg_replace('/[^0-9]/', '', $validated['cpf']);

        $user->email = $validated['email'];
        $user->nome_completo = $validated['nome_completo'];
        $user->cpf = $cpf;
        $user->telefone = $validated['telefone'] ?? null;
        $user->avatar_url = $validated['avatar_url'] ?? null;
        $user->ativo = isset($validated['ativo'])
            ? filter_var($validated['ativo'], FILTER_VALIDATE_BOOLEAN)
            : $user->ativo;

        $user->save();

        // Atualiza o perfil do Spatie Permission
        $role = Role::where('name', $validated['role'])->first();
        if ($role) {
            $user->syncRoles([$role]);
        }

        // Atualiza os tenants através da tabela de relacionamento
        if (isset($validated['tenant_id'])) {
            if (! empty($validated['tenant_id'])) {
                $user->tenants()->sync([$validated['tenant_id']]);
            } else {
                $user->tenants()->detach();
            }
        }

        return redirect()
            ->route('users.edit', $user)
            ->with('toast', [
                'type' => 'success',
                'title' => 'Usuário atualizado',
                'message' => 'As alterações foram salvas com sucesso.',
            ]);
    }

    /**
     * Change the user's password.
     */
    public function changePassword(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->password = $validated['password'];
        $user->save();

        return redirect()
            ->route('users.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Senha alterada',
                'message' => 'A senha do usuário foi alterada com sucesso.',
            ]);
    }

    /**
     * Remove the specified user.
     */
    public function destroy(User $user): RedirectResponse
    {
        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Usuário excluído',
                'message' => 'O usuário foi removido com sucesso.',
            ]);
    }
}
