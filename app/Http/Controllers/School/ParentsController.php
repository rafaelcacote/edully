<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Http\Requests\School\StoreParentRequest;
use App\Http\Requests\School\UpdateParentRequest;
use App\Models\Responsavel;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ParentsController extends Controller
{
    /**
     * Get the current user's tenant.
     */
    protected function getTenant()
    {
        $user = auth()->user();
        $tenant = $user->tenants()->first();

        if (! $tenant) {
            abort(404, 'Escola não encontrada');
        }

        return $tenant;
    }

    /**
     * Resolve the "Responsável Aluno" role on the connection used by User permissions.
     */
    protected function resolveResponsavelAlunoRole(User $user): Role
    {
        $rolesTable = config('permission.table_names.roles', 'roles');
        $connection = $user->getConnectionName();

        if (! Schema::connection($connection)->hasTable($rolesTable)) {
            $connection = config('database.default');
        }

        $role = Role::on($connection)->firstOrCreate(
            [
                'name' => 'Responsável Aluno',
                'guard_name' => 'web',
            ]
        );

        if ($role->wasRecentlyCreated) {
            app()[PermissionRegistrar::class]->forgetCachedPermissions();
        }

        return $role;
    }

    /**
     * Check if a CPF already exists and whether it is valid.
     */
    public function checkCpf(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'cpf' => ['required', 'string'],
        ]);

        $cpf = preg_replace('/[^0-9]/', '', $request->input('cpf'));

        if (strlen($cpf) !== 11) {
            return response()->json([
                'exists' => false,
                'valid' => false,
            ]);
        }

        $exists = User::query()->where('cpf', $cpf)->exists();

        return response()->json([
            'exists' => $exists,
            'valid' => $this->validateCpf($cpf),
        ]);
    }

    /**
     * Check if an email already exists.
     */
    public function checkEmail(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'ignore_user_id' => ['nullable', 'uuid'],
        ]);

        $email = strtolower(trim((string) $request->input('email')));

        $exists = User::query()
            ->where('email', $email)
            ->when($request->filled('ignore_user_id'), function ($query) use ($request) {
                $query->where('id', '!=', $request->input('ignore_user_id'));
            })
            ->exists();

        return response()->json([
            'exists' => $exists,
        ]);
    }

    /**
     * Validate CPF using Brazilian algorithm.
     */
    private function validateCpf(string $cpf): bool
    {
        $cpf = preg_replace('/[^0-9]/', '', $cpf);

        if (strlen($cpf) !== 11) {
            return false;
        }

        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += (int) $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ((int) $cpf[$c] !== $d) {
                return false;
            }
        }

        return true;
    }

    /**
     * Display a listing of the parents.
     */
    public function index(Request $request): Response
    {
        $tenant = $this->getTenant();
        $filters = $request->only(['search', 'active']);

        $parents = Responsavel::query()
            ->with([
                'user',
                'students' => function ($query) use ($tenant) {
                    $query->wherePivot('tenant_id', $tenant->id);
                },
            ])
            ->where('tenant_id', $tenant->id)
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $search = trim($search);
                $cpfSearch = preg_replace('/[^0-9]/', '', $search);
                $query->where(function ($q) use ($search, $cpfSearch) {
                    $q->whereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('nome_completo', 'ilike', "%{$search}%")
                            ->orWhere('email', 'ilike', "%{$search}%")
                            ->orWhere('telefone', 'ilike', "%{$search}%")
                            ->orWhere('cpf', 'ilike', "%{$cpfSearch}%");
                    })
                        ->orWhere('cpf', 'ilike', "%{$cpfSearch}%");
                });
            })
            ->when(isset($filters['active']) && $filters['active'] !== '' && $filters['active'] !== null, function ($query) use ($filters) {
                $active = filter_var($filters['active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($active !== null) {
                    $query->whereHas('user', function ($userQuery) use ($active) {
                        $userQuery->where('ativo', $active);
                    });
                }
            })
            ->get()
            ->sortBy(function ($parent) {
                return $parent->user?->nome_completo ?? '';
            })
            ->values();

        // Paginação manual
        $page = (int) $request->get('page', 1);
        $perPage = 10;
        $total = $parents->count();
        $items = $parents->slice(($page - 1) * $perPage, $perPage)->values();

        // Buscar turmas dos alunos diretamente da tabela pivot
        $allStudentIds = $items->flatMap(function ($parent) {
            return $parent->students->pluck('id');
        })->unique()->toArray();

        $driver = DB::connection('shared')->getDriverName();
        $pivotTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';

        $matriculas = ! empty($allStudentIds) ? DB::connection('shared')
            ->table($pivotTable)
            ->where('tenant_id', $tenant->id)
            ->where('status', 'ativo')
            ->whereIn('aluno_id', $allStudentIds)
            ->get(['aluno_id', 'turma_id']) : collect();

        $turmaIds = $matriculas->pluck('turma_id')->unique()->toArray();
        $turmasMap = ! empty($turmaIds) ? Turma::query()
            ->whereIn('id', $turmaIds)
            ->get(['id', 'nome', 'serie', 'turma_letra', 'ano_letivo'])
            ->keyBy('id') : collect();

        $matriculasMap = $matriculas->groupBy('aluno_id')->map(function ($items) {
            return $items->first()->turma_id;
        });

        // Transformar os dados para incluir campos do user e alunos
        $transformedItems = $items->map(function ($parent) use ($matriculasMap, $turmasMap) {
            return [
                'id' => $parent->id,
                'nome_completo' => $parent->user?->nome_completo ?? null,
                'cpf' => $parent->cpf ?? $parent->user?->cpf ?? null,
                'email' => $parent->user?->email ?? null,
                'telefone' => $parent->user?->telefone ?? null,
                'parentesco' => $parent->parentesco ?? null,
                'ativo' => $parent->user?->ativo ?? false,
                'students' => $parent->students->map(function ($student) use ($matriculasMap, $turmasMap) {
                    $turmaId = $matriculasMap->get($student->id);
                    $turma = $turmaId ? $turmasMap->get($turmaId) : null;

                    return [
                        'id' => $student->id,
                        'nome' => $student->nome ?? 'Sem nome',
                        'nome_social' => $student->nome_social,
                        'data_nascimento' => optional($student->data_nascimento)->toDateString(),
                        'turma' => $turma ? [
                            'id' => $turma->id,
                            'nome' => $turma->nome,
                            'serie' => $turma->serie,
                            'turma_letra' => $turma->turma_letra,
                            'ano_letivo' => $turma->ano_letivo,
                        ] : null,
                    ];
                })->values(),
            ];
        })->toArray();

        $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $transformedItems,
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        // Garantir que os links sejam gerados corretamente
        $paginated->withPath($request->url());

        return Inertia::render('school/parents/Index', [
            'parents' => $paginated,
            'filters' => $filters,
        ]);
    }

    /**
     * Show the form for creating a new parent.
     */
    public function create(): Response
    {
        return Inertia::render('school/parents/Create');
    }

    /**
     * Store a newly created parent.
     */
    public function store(StoreParentRequest $request): RedirectResponse
    {
        $tenant = $this->getTenant();
        $validated = $request->validated();

        DB::transaction(function () use ($tenant, $validated) {
            // Remove CPF formatting
            if (! empty($validated['cpf'])) {
                $validated['cpf'] = preg_replace('/[^0-9]/', '', $validated['cpf']);
            }

            // Determine password: use provided password, or CPF, or default
            $password = $validated['password'] ?? $validated['cpf'] ?? 'password';

            // Create the user first
            $user = User::create([
                'nome_completo' => $validated['nome_completo'],
                'cpf' => $validated['cpf'] ?? null,
                'email' => $validated['email'] ?? null,
                'telefone' => $validated['telefone'] ?? null,
                'password_hash' => Hash::make($password),
                'ativo' => $validated['ativo'] ?? true,
            ]);

            // Assign the "Responsável Aluno" role using the same DB connection as User.
            // On Postgres this hits escola.roles (shared search_path) and avoids FK errors
            // when a duplicate role exists only in another schema (e.g. laravel.roles).
            $role = $this->resolveResponsavelAlunoRole($user);
            $user->assignRole($role);

            // Link the user to the tenant
            $user->tenants()->syncWithoutDetaching([$tenant->id]);

            // Create the parent linked to the user
            Responsavel::create([
                'tenant_id' => $tenant->id,
                'usuario_id' => $user->id,
                'parentesco' => $validated['parentesco'] ?? null,
                'cpf' => $validated['cpf'] ?? null,
                'profissao' => $validated['profissao'] ?? null,
            ]);
        });

        return redirect()
            ->route('school.parents.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Responsável criado',
                'message' => 'O responsável foi cadastrado com sucesso.',
            ]);
    }

    /**
     * Display the specified parent.
     */
    public function show(Responsavel $parent): Response
    {
        $tenant = $this->getTenant();

        if ($parent->tenant_id !== $tenant->id) {
            abort(404);
        }

        $parent->load([
            'user:id,nome_completo,cpf,email,telefone,ativo',
            'students',
        ]);

        // Buscar turmas dos alunos diretamente da tabela pivot
        $studentIds = $parent->students->pluck('id')->toArray();
        $driver = DB::connection('shared')->getDriverName();
        $pivotTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';

        $matriculas = DB::connection('shared')
            ->table($pivotTable)
            ->where('tenant_id', $tenant->id)
            ->where('status', 'ativo')
            ->whereIn('aluno_id', $studentIds)
            ->get(['aluno_id', 'turma_id']);

        $turmaIds = $matriculas->pluck('turma_id')->unique()->toArray();
        $turmasMap = Turma::query()
            ->whereIn('id', $turmaIds)
            ->get(['id', 'nome', 'serie', 'turma_letra', 'ano_letivo'])
            ->keyBy('id');

        $matriculasMap = $matriculas->groupBy('aluno_id')->map(function ($items) {
            return $items->first()->turma_id;
        });

        $turmas = Turma::query()
            ->where('tenant_id', $tenant->id)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome', 'serie', 'turma_letra', 'ano_letivo']);

        return Inertia::render('school/parents/Show', [
            'parent' => [
                'id' => $parent->id,
                'nome_completo' => $parent->user?->nome_completo,
                'cpf' => $parent->cpf ?? $parent->user?->cpf ?? null,
                'email' => $parent->user?->email,
                'telefone' => $parent->user?->telefone,
                'parentesco' => $parent->parentesco,
                'profissao' => $parent->profissao,
                'ativo' => $parent->user?->ativo ?? false,
                'students' => $parent->students->map(function ($student) use ($matriculasMap, $turmasMap) {
                    $turmaId = $matriculasMap->get($student->id);
                    $turma = $turmaId ? $turmasMap->get($turmaId) : null;

                    return [
                        'id' => $student->id,
                        'nome' => $student->nome ?? 'Sem nome',
                        'nome_social' => $student->nome_social,
                        'foto_url' => $student->foto_url,
                        'data_nascimento' => optional($student->data_nascimento)->toDateString(),
                        'ativo' => (bool) $student->ativo,
                        'turma' => $turma ? [
                            'id' => $turma->id,
                            'nome' => $turma->nome,
                            'serie' => $turma->serie,
                            'turma_letra' => $turma->turma_letra,
                            'ano_letivo' => $turma->ano_letivo,
                        ] : null,
                    ];
                })->values(),
            ],
            'turmas' => $turmas,
        ]);
    }

    /**
     * Show the form for editing the specified parent.
     */
    public function edit(Responsavel $parent): Response
    {
        $tenant = $this->getTenant();

        if ($parent->tenant_id !== $tenant->id) {
            abort(404);
        }

        $parent->load('user:id,nome_completo,cpf,email,telefone,ativo');

        return Inertia::render('school/parents/Edit', [
            'parent' => [
                'id' => $parent->id,
                'usuario_id' => $parent->usuario_id,
                'nome_completo' => $parent->user?->nome_completo,
                'cpf' => $parent->cpf ?? $parent->user?->cpf ?? null,
                'email' => $parent->user?->email,
                'telefone' => $parent->user?->telefone,
                'parentesco' => $parent->parentesco,
                'profissao' => $parent->profissao,
                'ativo' => $parent->user?->ativo ?? false,
            ],
        ]);
    }

    /**
     * Update the specified parent.
     */
    public function update(UpdateParentRequest $request, Responsavel $parent): RedirectResponse
    {
        $tenant = $this->getTenant();

        if ($parent->tenant_id !== $tenant->id) {
            abort(404);
        }

        $validated = $request->validated();

        DB::transaction(function () use ($parent, $validated) {
            // Update the user (CPF não é atualizado no update, como solicitado)
            $parent->user->update([
                'nome_completo' => $validated['nome_completo'],
                'email' => $validated['email'] ?? null,
                'telefone' => $validated['telefone'] ?? null,
                'ativo' => $validated['ativo'] ?? $parent->user->ativo,
            ]);

            // Update password if provided
            if (! empty($validated['password'])) {
                $parent->user->update([
                    'password_hash' => Hash::make($validated['password']),
                ]);
            }

            // Update the parent
            $parent->update([
                'parentesco' => $validated['parentesco'] ?? null,
                'profissao' => $validated['profissao'] ?? null,
            ]);
        });

        return redirect()
            ->route('school.parents.edit', $parent)
            ->with('toast', [
                'type' => 'success',
                'title' => 'Responsável atualizado',
                'message' => 'As alterações foram salvas com sucesso.',
            ]);
    }
}
