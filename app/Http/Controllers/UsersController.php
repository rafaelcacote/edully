<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UsersController extends Controller
{
    /**
     * Display a listing of the users.
     */
    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'role', 'active']);

        $users = User::query()
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $search = trim($search);
                $query->where(function ($q) use ($search) {
                    $q->where('full_name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%")
                        ->orWhere('phone', 'ilike', "%{$search}%");
                });
            })
            ->when($filters['role'] ?? null, fn ($query, string $role) => $query->where('role', $role))
            ->when(isset($filters['active']) && $filters['active'] !== '' && $filters['active'] !== null, function ($query) use ($filters) {
                $active = filter_var($filters['active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($active !== null) {
                    $query->where('is_active', $active);
                }
            })
            ->orderBy('full_name')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('users/Index', [
            'users' => $users,
            'filters' => $filters,
            'roles' => $this->roles(),
        ]);
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(): Response
    {
        return Inertia::render('users/Create', [
            'roles' => $this->roles(),
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'full_name' => ['required', 'string', 'max:255'],
            'cpf' => ['required', 'string', 'size:11', Rule::unique(User::class, 'cpf')],
            'role' => ['required', 'string', 'max:255', Rule::in($this->roles())],
            'phone' => ['nullable', 'string', 'max:20'],
            'avatar_url' => ['nullable', 'string', 'max:2048'],
            'is_active' => ['nullable', 'boolean'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = new User();
        $user->email = $validated['email'];
        $user->full_name = $validated['full_name'];
        $user->cpf = $validated['cpf'];
        $user->role = $validated['role'];
        $user->phone = $validated['phone'] ?? null;
        $user->avatar_url = $validated['avatar_url'] ?? null;
        $user->is_active = $validated['is_active'] ?? true;
        $user->password = $validated['password'];
        $user->save();

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
        return Inertia::render('users/Edit', [
            'user' => $user,
            'roles' => $this->roles(),
        ]);
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore($user->id, 'id')],
            'full_name' => ['required', 'string', 'max:255'],
            'cpf' => ['required', 'string', 'size:11', Rule::unique(User::class, 'cpf')->ignore($user->id, 'id')],
            'role' => ['required', 'string', 'max:255', Rule::in($this->roles())],
            'phone' => ['nullable', 'string', 'max:20'],
            'avatar_url' => ['nullable', 'string', 'max:2048'],
            'is_active' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $user->email = $validated['email'];
        $user->full_name = $validated['full_name'];
        $user->cpf = $validated['cpf'];
        $user->role = $validated['role'];
        $user->phone = $validated['phone'] ?? null;
        $user->avatar_url = $validated['avatar_url'] ?? null;
        $user->is_active = $validated['is_active'] ?? $user->is_active;

        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->save();

        return redirect()
            ->route('users.edit', $user)
            ->with('toast', [
                'type' => 'success',
                'title' => 'Usuário atualizado',
                'message' => 'As alterações foram salvas com sucesso.',
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

    /**
     * Allowed roles for escola.user_role.
     *
     * @return array<int, string>
     */
    private function roles(): array
    {
        // Ajuste aqui caso o enum do Postgres tenha outros valores.
        return ['parent', 'student', 'teacher', 'admin'];
    }
}

