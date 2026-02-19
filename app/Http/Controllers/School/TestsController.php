<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Http\Requests\School\StoreTestRequest;
use App\Http\Requests\School\UpdateTestRequest;
use App\Models\Disciplina;
use App\Models\Teacher;
use App\Models\Test;
use App\Models\Turma;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TestsController extends Controller
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
     * Get the current teacher from the authenticated user (if exists).
     * Returns null if user is not a teacher (e.g., Administrador Escola).
     */
    protected function getCurrentTeacher()
    {
        $user = auth()->user();
        $tenant = $this->getTenant();

        return Teacher::query()
            ->where('tenant_id', $tenant->id)
            ->where('usuario_id', $user->id)
            ->where('ativo', true)
            ->first();
    }

    /**
     * Display a listing of the tests.
     */
    public function index(Request $request): Response
    {
        $tenant = $this->getTenant();
        $teacher = $this->getCurrentTeacher();
        $filters = $request->only(['search', 'turma_id', 'disciplina_id']);

        $tests = Test::query()
            ->where('tenant_id', $tenant->id)
            ->when($teacher, function ($query) use ($teacher) {
                // Se for professor, mostrar apenas provas que ele criou
                $query->where('professor_id', $teacher->id);
            })
            ->with(['turma:id,nome,serie,turma_letra,ano_letivo', 'disciplinaRelation:id,nome,sigla'])
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $search = trim($search);
                $query->where(function ($q) use ($search) {
                    $q->where('titulo', 'ilike', "%{$search}%")
                        ->orWhere('descricao', 'ilike', "%{$search}%")
                        ->orWhereHas('disciplinaRelation', function ($q) use ($search) {
                            $q->where('nome', 'ilike', "%{$search}%")
                                ->orWhere('sigla', 'ilike', "%{$search}%");
                        });
                });
            })
            ->when($filters['turma_id'] ?? null, function ($query, string $turmaId) {
                $query->where('turma_id', $turmaId);
            })
            ->when($filters['disciplina_id'] ?? null, function ($query, string $disciplinaId) {
                $query->where('disciplina_id', $disciplinaId);
            })
            ->orderBy('data_prova', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString()
            ->through(function (Test $test) {
                return [
                    'id' => $test->id,
                    'titulo' => $test->titulo,
                    'disciplina' => $test->disciplinaRelation
                        ? ($test->disciplinaRelation->nome.($test->disciplinaRelation->sigla ? ' ('.$test->disciplinaRelation->sigla.')' : ''))
                        : null,
                    'data_prova' => $test->data_prova->format('d/m/Y'),
                    'horario' => $test->horario,
                    'turma' => $test->turma
                        ? [
                            'id' => $test->turma->id,
                            'nome' => $test->turma->nome,
                            'serie' => $test->turma->serie,
                            'turma_letra' => $test->turma->turma_letra,
                            'ano_letivo' => $test->turma->ano_letivo,
                        ]
                        : null,
                ];
            });

        // Buscar turmas e disciplinas
        // Se for professor, usar as turmas/disciplinas dele; se for admin escola, buscar todas
        if ($teacher) {
            $turmas = $teacher->turmas()
                ->where('ativo', true)
                ->orderBy('nome')
                ->get()
                ->map(function ($turma) {
                    return [
                        'id' => $turma->id,
                        'nome' => $turma->nome,
                    ];
                });

            $disciplinas = $teacher->disciplinas()
                ->where('ativo', true)
                ->orderBy('nome')
                ->get()
                ->map(function ($disciplina) {
                    return [
                        'id' => $disciplina->id,
                        'nome' => $disciplina->nome.($disciplina->sigla ? ' ('.$disciplina->sigla.')' : ''),
                    ];
                });
        } else {
            // Administrador Escola: buscar todas as turmas e disciplinas do tenant
            $turmas = Turma::query()
                ->where('tenant_id', $tenant->id)
                ->where('ativo', true)
                ->orderBy('nome')
                ->get()
                ->map(function ($turma) {
                    return [
                        'id' => $turma->id,
                        'nome' => $turma->nome,
                    ];
                });

            $disciplinas = Disciplina::query()
                ->where('tenant_id', $tenant->id)
                ->where('ativo', true)
                ->orderBy('nome')
                ->get()
                ->map(function ($disciplina) {
                    return [
                        'id' => $disciplina->id,
                        'nome' => $disciplina->nome.($disciplina->sigla ? ' ('.$disciplina->sigla.')' : ''),
                    ];
                });
        }

        return Inertia::render('school/tests/Index', [
            'tests' => $tests,
            'turmas' => $turmas,
            'disciplinas' => $disciplinas,
            'filters' => $filters,
        ]);
    }

    /**
     * Show the form for creating a new test.
     */
    public function create(): Response
    {
        $tenant = $this->getTenant();
        $teacher = $this->getCurrentTeacher();

        // Buscar turmas e disciplinas
        if ($teacher) {
            $turmas = $teacher->turmas()
                ->where('ativo', true)
                ->orderBy('nome')
                ->get()
                ->map(function ($turma) {
                    return [
                        'id' => $turma->id,
                        'nome' => $turma->nome,
                        'serie' => $turma->serie,
                        'ano_letivo' => $turma->ano_letivo,
                    ];
                });

            $disciplinas = $teacher->disciplinas()
                ->where('ativo', true)
                ->orderBy('nome')
                ->get()
                ->map(function ($disciplina) {
                    return [
                        'id' => $disciplina->id,
                        'nome' => $disciplina->nome,
                        'sigla' => $disciplina->sigla,
                    ];
                });
        } else {
            // Administrador Escola: buscar todas as turmas e disciplinas do tenant
            $turmas = \App\Models\Turma::query()
                ->where('tenant_id', $tenant->id)
                ->where('ativo', true)
                ->orderBy('nome')
                ->get()
                ->map(function ($turma) {
                    return [
                        'id' => $turma->id,
                        'nome' => $turma->nome,
                        'serie' => $turma->serie,
                        'ano_letivo' => $turma->ano_letivo,
                    ];
                });

            $disciplinas = \App\Models\Disciplina::query()
                ->where('tenant_id', $tenant->id)
                ->where('ativo', true)
                ->orderBy('nome')
                ->get()
                ->map(function ($disciplina) {
                    return [
                        'id' => $disciplina->id,
                        'nome' => $disciplina->nome,
                        'sigla' => $disciplina->sigla,
                    ];
                });
        }

        return Inertia::render('school/tests/Create', [
            'turmas' => $turmas,
            'disciplinas' => $disciplinas,
        ]);
    }

    /**
     * Store a newly created test.
     */
    public function store(StoreTestRequest $request): RedirectResponse
    {
        $tenant = $this->getTenant();
        $teacher = $this->getCurrentTeacher();
        $validated = $request->validated();

        // Se for professor, usar o professor_id dele
        // Se for Administrador Escola, precisa fornecer o professor_id no request
        if ($teacher) {
            $professorId = $teacher->id;
        } else {
            // Administrador Escola deve selecionar um professor
            // Por enquanto, usar o primeiro professor ativo do tenant como fallback
            // TODO: Adicionar campo professor_id no formulário para Administrador Escola
            $professorId = $validated['professor_id'] ?? Teacher::query()
                ->where('tenant_id', $tenant->id)
                ->where('ativo', true)
                ->first()?->id;

            if (! $professorId) {
                return redirect()
                    ->back()
                    ->withErrors(['professor_id' => 'Nenhum professor ativo encontrado na escola.']);
            }
        }

        Test::create([
            ...$validated,
            'tenant_id' => $tenant->id,
            'professor_id' => $professorId,
        ]);

        return redirect()
            ->route('school.tests.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Prova criada',
                'message' => 'A prova foi cadastrada com sucesso.',
            ]);
    }

    /**
     * Display the specified test.
     */
    public function show(Test $test): Response
    {
        $tenant = $this->getTenant();
        $teacher = $this->getCurrentTeacher();

        // Verificar se a prova pertence ao tenant
        if ($test->tenant_id !== $tenant->id) {
            abort(404);
        }

        // Se for professor, verificar se a prova é dele
        if ($teacher && $test->professor_id !== $teacher->id) {
            abort(404);
        }

        $test->load([
            'turma:id,nome,serie,turma_letra,ano_letivo',
            'professor.usuario:id,nome_completo',
            'disciplinaRelation:id,nome,sigla',
        ]);

        return Inertia::render('school/tests/Show', [
            'test' => [
                'id' => $test->id,
                'disciplina' => $test->disciplinaRelation
                    ? ($test->disciplinaRelation->nome.($test->disciplinaRelation->sigla ? ' ('.$test->disciplinaRelation->sigla.')' : ''))
                    : null,
                'titulo' => $test->titulo,
                'descricao' => $test->descricao,
                'data_prova' => $test->data_prova->format('Y-m-d'),
                'data_prova_formatted' => $test->data_prova->format('d/m/Y'),
                'horario' => $test->horario,
                'horario_formatted' => $test->horario,
                'sala' => $test->sala,
                'duracao_minutos' => $test->duracao_minutos,
                'turma' => $test->turma
                    ? [
                        'id' => $test->turma->id,
                        'nome' => $test->turma->nome,
                        'serie' => $test->turma->serie,
                        'turma_letra' => $test->turma->turma_letra,
                        'ano_letivo' => $test->turma->ano_letivo,
                    ]
                    : null,
                'professor' => $test->professor
                    ? [
                        'id' => $test->professor->id,
                        'usuario' => $test->professor->usuario
                            ? [
                                'nome_completo' => $test->professor->usuario->nome_completo,
                            ]
                            : null,
                    ]
                    : null,
            ],
        ]);
    }

    /**
     * Show the form for editing the specified test.
     */
    public function edit(Test $test): Response
    {
        $tenant = $this->getTenant();
        $teacher = $this->getCurrentTeacher();

        // Verificar se a prova pertence ao tenant
        if ($test->tenant_id !== $tenant->id) {
            abort(404);
        }

        // Se for professor, verificar se a prova é dele
        if ($teacher && $test->professor_id !== $teacher->id) {
            abort(404);
        }

        // Buscar turmas e disciplinas
        if ($teacher) {
            $turmas = $teacher->turmas()
                ->where('ativo', true)
                ->orderBy('nome')
                ->get()
                ->map(function ($turma) {
                    return [
                        'id' => $turma->id,
                        'nome' => $turma->nome,
                        'serie' => $turma->serie,
                        'ano_letivo' => $turma->ano_letivo,
                    ];
                });

            $disciplinas = $teacher->disciplinas()
                ->where('ativo', true)
                ->orderBy('nome')
                ->get()
                ->map(function ($disciplina) {
                    return [
                        'id' => $disciplina->id,
                        'nome' => $disciplina->nome,
                        'sigla' => $disciplina->sigla,
                    ];
                });
        } else {
            // Administrador Escola: buscar todas as turmas e disciplinas do tenant
            $turmas = \App\Models\Turma::query()
                ->where('tenant_id', $tenant->id)
                ->where('ativo', true)
                ->orderBy('nome')
                ->get()
                ->map(function ($turma) {
                    return [
                        'id' => $turma->id,
                        'nome' => $turma->nome,
                        'serie' => $turma->serie,
                        'ano_letivo' => $turma->ano_letivo,
                    ];
                });

            $disciplinas = \App\Models\Disciplina::query()
                ->where('tenant_id', $tenant->id)
                ->where('ativo', true)
                ->orderBy('nome')
                ->get()
                ->map(function ($disciplina) {
                    return [
                        'id' => $disciplina->id,
                        'nome' => $disciplina->nome,
                        'sigla' => $disciplina->sigla,
                    ];
                });
        }

        return Inertia::render('school/tests/Edit', [
            'test' => [
                'id' => $test->id,
                'disciplina_id' => $test->disciplina_id,
                'titulo' => $test->titulo,
                'descricao' => $test->descricao,
                'data_prova' => $test->data_prova->format('Y-m-d'),
                'horario' => $test->horario,
                'sala' => $test->sala,
                'duracao_minutos' => $test->duracao_minutos,
                'turma_id' => $test->turma_id,
            ],
            'turmas' => $turmas,
            'disciplinas' => $disciplinas,
        ]);
    }

    /**
     * Update the specified test.
     */
    public function update(UpdateTestRequest $request, Test $test): RedirectResponse
    {
        $tenant = $this->getTenant();
        $teacher = $this->getCurrentTeacher();

        // Verificar se a prova pertence ao tenant
        if ($test->tenant_id !== $tenant->id) {
            abort(404);
        }

        // Se for professor, verificar se a prova é dele
        if ($teacher && $test->professor_id !== $teacher->id) {
            abort(404);
        }

        $validated = $request->validated();

        $test->update($validated);

        return redirect()
            ->route('school.tests.edit', $test)
            ->with('toast', [
                'type' => 'success',
                'title' => 'Prova atualizada',
                'message' => 'As alterações foram salvas com sucesso.',
            ]);
    }

    /**
     * Remove the specified test.
     */
    public function destroy(Test $test): RedirectResponse
    {
        $tenant = $this->getTenant();
        $teacher = $this->getCurrentTeacher();

        // Verificar se a prova pertence ao tenant
        if ($test->tenant_id !== $tenant->id) {
            abort(404);
        }

        // Se for professor, verificar se a prova é dele
        if ($teacher && $test->professor_id !== $teacher->id) {
            abort(404);
        }

        $test->delete();

        return redirect()
            ->route('school.tests.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Prova excluída',
                'message' => 'A prova foi removida com sucesso.',
            ]);
    }
}
