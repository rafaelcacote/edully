<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Http\Requests\School\StoreClassRequest;
use App\Http\Requests\School\UpdateClassRequest;
use App\Models\Turma;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ClassesController extends Controller
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
     * Display a listing of the classes.
     */
    public function index(Request $request): Response
    {
        $tenant = $this->getTenant();
        $filters = $request->only(['search', 'active']);

        $classes = Turma::query()
            ->where('tenant_id', $tenant->id)
            ->with(['professor.usuario:id,nome_completo', 'professores.usuario:id,nome_completo'])
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $search = trim($search);
                $query->where(function ($q) use ($search) {
                    $q->where('nome', 'ilike', "%{$search}%")
                        ->orWhere('serie', 'ilike', "%{$search}%")
                        ->orWhere('turma_letra', 'ilike', "%{$search}%")
                        ->orWhereRaw('CAST(ano_letivo AS TEXT) ILIKE ?', ["%{$search}%"]);
                });
            })
            ->when(isset($filters['active']) && $filters['active'] !== '' && $filters['active'] !== null, function ($query) use ($filters) {
                $active = filter_var($filters['active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($active !== null) {
                    $query->where('ativo', $active);
                }
            })
            ->orderBy('nome')
            ->paginate(10)
            ->withQueryString()
            ->through(function (Turma $class) {
                return [
                    'id' => $class->id,
                    'nome' => $class->nome,
                    'serie' => $class->serie,
                    'ano_letivo' => $class->ano_letivo,
                    'capacidade' => $class->capacidade,
                    'ativo' => (bool) $class->ativo,
                    'professor' => $class->professor
                        ? [
                            'id' => $class->professor->id,
                            'usuario' => $class->professor->usuario
                                ? [
                                    'nome_completo' => $class->professor->usuario->nome_completo,
                                ]
                                : null,
                        ]
                        : null,
                    'professores' => $class->professores->map(function ($professor) {
                        return [
                            'id' => $professor->id,
                            'usuario' => $professor->usuario
                                ? [
                                    'nome_completo' => $professor->usuario->nome_completo,
                                ]
                                : null,
                        ];
                    }),
                ];
            });

        return Inertia::render('school/classes/Index', [
            'classes' => $classes,
            'filters' => $filters,
        ]);
    }

    /**
     * Show the form for creating a new class.
     */
    public function create(): Response
    {
        $tenant = $this->getTenant();

        $teachers = \App\Models\Teacher::query()
            ->where('tenant_id', $tenant->id)
            ->where('ativo', true)
            ->with('usuario:id,nome_completo')
            ->get()
            ->map(function ($teacher) {
                return [
                    'id' => $teacher->id,
                    'nome_completo' => $teacher->usuario->nome_completo ?? 'Sem nome',
                ];
            });

        return Inertia::render('school/classes/Create', [
            'teachers' => $teachers,
        ]);
    }

    /**
     * Store a newly created class.
     */
    public function store(StoreClassRequest $request): RedirectResponse
    {
        $tenant = $this->getTenant();
        $validated = $request->validated();

        $professorIds = $validated['professor_ids'] ?? [];
        unset($validated['professor_ids']);

        $turma = Turma::create([
            ...$validated,
            'tenant_id' => $tenant->id,
            'ativo' => $validated['ativo'] ?? true,
        ]);

        // Sincronizar professores usando a relação many-to-many
        if (! empty($professorIds)) {
            $turma->professores()->sync(
                collect($professorIds)->mapWithKeys(function ($professorId) use ($tenant) {
                    return [$professorId => ['tenant_id' => $tenant->id]];
                })->toArray()
            );
        }

        return redirect()
            ->route('school.classes.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Turma criada',
                'message' => 'A turma foi cadastrada com sucesso.',
            ]);
    }

    /**
     * Display the specified class.
     */
    public function show(Turma $class): Response
    {
        $tenant = $this->getTenant();

        if ($class->tenant_id !== $tenant->id) {
            abort(404);
        }

        $class->load('professor.usuario:id,nome_completo');

        // Carregar professores separadamente para garantir que o tenant_id seja usado corretamente
        $professores = $class->professores()
            ->with('usuario:id,nome_completo')
            ->get();

        $professoresArray = $professores->map(function ($professor) {
            return [
                'id' => $professor->id,
                'usuario' => $professor->usuario
                    ? [
                        'nome_completo' => $professor->usuario->nome_completo,
                    ]
                    : null,
            ];
        })->values()->toArray();

        return Inertia::render('school/classes/Show', [
            'turma' => [
                'id' => $class->id,
                'nome' => $class->nome,
                'serie' => $class->serie,
                'turma_letra' => $class->turma_letra,
                'ano_letivo' => $class->ano_letivo,
                'capacidade' => $class->capacidade,
                'ativo' => (bool) $class->ativo,
                'professor' => $class->professor
                    ? [
                        'id' => $class->professor->id,
                        'usuario' => $class->professor->usuario
                            ? [
                                'nome_completo' => $class->professor->usuario->nome_completo,
                            ]
                            : null,
                    ]
                    : null,
                'professores' => $professoresArray,
            ],
        ]);
    }

    /**
     * Show the form for editing the specified class.
     */
    public function edit(Turma $class): Response
    {
        $tenant = $this->getTenant();

        if ($class->tenant_id !== $tenant->id) {
            abort(404);
        }

        $teachers = \App\Models\Teacher::query()
            ->where('tenant_id', $tenant->id)
            ->where('ativo', true)
            ->with('usuario:id,nome_completo')
            ->get()
            ->map(function ($teacher) {
                return [
                    'id' => $teacher->id,
                    'nome_completo' => $teacher->usuario->nome_completo ?? 'Sem nome',
                ];
            });

        // Carregar professores vinculados separadamente para garantir que o tenant_id seja usado corretamente
        $professoresVinculados = $class->professores()->get();

        return Inertia::render('school/classes/Edit', [
            'turma' => [
                'id' => $class->id,
                'nome' => $class->nome,
                'serie' => $class->serie,
                'turma_letra' => $class->turma_letra,
                'capacidade' => $class->capacidade,
                'ano_letivo' => $class->ano_letivo,
                'professor_id' => $class->professor_id,
                'professor_ids' => $professoresVinculados->pluck('id')->toArray(),
                'ativo' => (bool) $class->ativo,
            ],
            'teachers' => $teachers,
        ]);
    }

    /**
     * Update the specified class.
     */
    public function update(UpdateClassRequest $request, Turma $class): RedirectResponse
    {
        $tenant = $this->getTenant();

        if ($class->tenant_id !== $tenant->id) {
            abort(404);
        }

        $validated = $request->validated();

        $professorIds = $validated['professor_ids'] ?? [];
        unset($validated['professor_ids']);

        $class->update([
            ...$validated,
            'ativo' => $validated['ativo'] ?? $class->ativo,
        ]);

        // Sincronizar professores usando a relação many-to-many
        $class->professores()->sync(
            collect($professorIds)->mapWithKeys(function ($professorId) use ($tenant) {
                return [$professorId => ['tenant_id' => $tenant->id]];
            })->toArray()
        );

        return redirect()
            ->route('school.classes.edit', $class)
            ->with('toast', [
                'type' => 'success',
                'title' => 'Turma atualizada',
                'message' => 'As alterações foram salvas com sucesso.',
            ]);
    }

    /**
     * Toggle the status of the specified class.
     */
    public function toggleStatus(Request $request, Turma $class): RedirectResponse
    {
        $tenant = $this->getTenant();

        if ($class->tenant_id !== $tenant->id) {
            abort(404);
        }

        $request->validate([
            'ativo' => ['required', 'boolean'],
        ]);

        $class->update([
            'ativo' => $request->boolean('ativo'),
        ]);

        return redirect()
            ->back()
            ->with('toast', [
                'type' => 'success',
                'title' => 'Status atualizado',
                'message' => 'O status da turma foi atualizado com sucesso.',
            ]);
    }

    /**
     * Remove the specified class.
     */
    public function destroy(Turma $class): RedirectResponse
    {
        $tenant = $this->getTenant();

        if ($class->tenant_id !== $tenant->id) {
            abort(404);
        }

        $class->delete();

        return redirect()
            ->route('school.classes.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Turma excluída',
                'message' => 'A turma foi removida com sucesso.',
            ]);
    }

    /**
     * Display the students enrolled in the specified class.
     */
    public function students(Turma $class): Response
    {
        $tenant = $this->getTenant();

        if ($class->tenant_id !== $tenant->id) {
            abort(404);
        }

        $driver = DB::connection('shared')->getDriverName();
        $pivotTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';
        $alunosTable = $driver === 'sqlite' ? 'alunos' : 'escola.alunos';

        $students = DB::connection('shared')
            ->table($pivotTable.' as matriculas')
            ->join($alunosTable.' as alunos', 'alunos.id', '=', 'matriculas.aluno_id')
            ->where('matriculas.tenant_id', $tenant->id)
            ->where('matriculas.turma_id', $class->id)
            ->where('matriculas.status', 'ativo')
            ->whereNull('alunos.deleted_at')
            ->orderBy('alunos.nome')
            ->select([
                'alunos.id',
                'alunos.nome',
                'alunos.nome_social',
                'alunos.foto_url',
                'alunos.data_nascimento',
                'alunos.ativo',
                'matriculas.id as matricula_id',
                'matriculas.data_matricula',
            ])
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('school/classes/Students', [
            'turma' => [
                'id' => $class->id,
                'nome' => $class->nome,
                'serie' => $class->serie,
                'turma_letra' => $class->turma_letra,
                'ano_letivo' => $class->ano_letivo,
            ],
            'students' => $students,
        ]);
    }
}
