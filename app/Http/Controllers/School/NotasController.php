<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Http\Requests\School\StoreNotaRequest;
use App\Http\Requests\School\UpdateNotaRequest;
use App\Models\Nota;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotasController extends Controller
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
     * Display a listing of the notas.
     */
    public function index(Request $request): Response
    {
        $tenant = $this->getTenant();
        $filters = $request->only(['search', 'aluno_id', 'professor_id', 'turma_id', 'disciplina_id', 'trimestre', 'ano_letivo']);

        $notas = Nota::query()
            ->where('tenant_id', $tenant->id)
            ->with([
                'aluno:id,nome',
                'professor.usuario:id,nome_completo',
                'turma:id,nome',
                'disciplina:id,nome,sigla',
            ])
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $search = trim($search);
                $query->where(function ($q) use ($search) {
                    $q->where('disciplina', 'ilike', "%{$search}%")
                        ->orWhere('observacoes', 'ilike', "%{$search}%")
                        ->orWhereHas('aluno', function ($q) use ($search) {
                            $q->where('nome', 'ilike', "%{$search}%");
                        });
                });
            })
            ->when($filters['aluno_id'] ?? null, function ($query, string $alunoId) {
                $query->where('aluno_id', $alunoId);
            })
            ->when($filters['professor_id'] ?? null, function ($query, string $professorId) {
                $query->where('professor_id', $professorId);
            })
            ->when($filters['turma_id'] ?? null, function ($query, string $turmaId) {
                $query->where('turma_id', $turmaId);
            })
            ->when($filters['disciplina_id'] ?? null, function ($query, string $disciplinaId) {
                $query->where('disciplina_id', $disciplinaId);
            })
            ->when($filters['trimestre'] ?? null, function ($query, string $trimestre) {
                $query->where('trimestre', $trimestre);
            })
            ->when($filters['ano_letivo'] ?? null, function ($query, string $anoLetivo) {
                $query->where('ano_letivo', $anoLetivo);
            })
            ->orderBy('ano_letivo', 'desc')
            ->orderBy('trimestre')
            ->orderBy('disciplina')
            ->paginate(15)
            ->withQueryString()
            ->through(function (Nota $nota) {
                return [
                    'id' => $nota->id,
                    'aluno' => $nota->aluno
                        ? [
                            'id' => $nota->aluno->id,
                            'nome' => $nota->aluno->nome,
                        ]
                        : null,
                    'professor' => $nota->professor
                        ? [
                            'id' => $nota->professor->id,
                            'usuario' => $nota->professor->usuario
                                ? [
                                    'nome_completo' => $nota->professor->usuario->nome_completo,
                                ]
                                : null,
                        ]
                        : null,
                    'turma' => $nota->turma
                        ? [
                            'id' => $nota->turma->id,
                            'nome' => $nota->turma->nome,
                        ]
                        : null,
                    'disciplina' => $nota->disciplina,
                    'disciplina_id' => $nota->disciplina_id,
                    'trimestre' => $nota->trimestre,
                    'nota' => $nota->nota,
                    'frequencia' => $nota->frequencia,
                    'comportamento' => $nota->comportamento,
                    'observacoes' => $nota->observacoes,
                    'ano_letivo' => $nota->ano_letivo,
                ];
            });

        // Carregar dados para filtros
        $alunos = \App\Models\Student::query()
            ->where('tenant_id', $tenant->id)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome']);

        $professores = \App\Models\Teacher::query()
            ->where('tenant_id', $tenant->id)
            ->where('ativo', true)
            ->with('usuario:id,nome_completo')
            ->get()
            ->map(function ($professor) {
                return [
                    'id' => $professor->id,
                    'nome_completo' => $professor->usuario->nome_completo ?? 'Sem nome',
                ];
            });

        $turmas = \App\Models\Turma::query()
            ->where('tenant_id', $tenant->id)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome']);

        $disciplinas = \App\Models\Disciplina::query()
            ->where('tenant_id', $tenant->id)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome', 'sigla']);

        return Inertia::render('school/notas/Index', [
            'notas' => $notas,
            'filters' => $filters,
            'alunos' => $alunos,
            'professores' => $professores,
            'turmas' => $turmas,
            'disciplinas' => $disciplinas,
        ]);
    }

    /**
     * Show the form for creating a new nota.
     */
    public function create(): Response
    {
        $tenant = $this->getTenant();

        $alunos = \App\Models\Student::query()
            ->where('tenant_id', $tenant->id)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome']);

        $professores = \App\Models\Teacher::query()
            ->where('tenant_id', $tenant->id)
            ->where('ativo', true)
            ->with('usuario:id,nome_completo')
            ->get()
            ->map(function ($professor) {
                return [
                    'id' => $professor->id,
                    'nome_completo' => $professor->usuario->nome_completo ?? 'Sem nome',
                ];
            });

        $turmas = \App\Models\Turma::query()
            ->where('tenant_id', $tenant->id)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome', 'ano_letivo']);

        $disciplinas = \App\Models\Disciplina::query()
            ->where('tenant_id', $tenant->id)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome', 'sigla']);

        return Inertia::render('school/notas/Create', [
            'alunos' => $alunos,
            'professores' => $professores,
            'turmas' => $turmas,
            'disciplinas' => $disciplinas,
        ]);
    }

    /**
     * Store a newly created nota.
     */
    public function store(StoreNotaRequest $request): RedirectResponse
    {
        $tenant = $this->getTenant();
        $validated = $request->validated();

        Nota::create([
            ...$validated,
            'tenant_id' => $tenant->id,
        ]);

        return redirect()
            ->route('school.notas.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Nota criada',
                'message' => 'A nota foi cadastrada com sucesso.',
            ]);
    }

    /**
     * Show the form for editing the specified nota.
     */
    public function edit(Nota $nota): Response
    {
        $tenant = $this->getTenant();

        if ($nota->tenant_id !== $tenant->id) {
            abort(404);
        }

        $alunos = \App\Models\Student::query()
            ->where('tenant_id', $tenant->id)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome']);

        $professores = \App\Models\Teacher::query()
            ->where('tenant_id', $tenant->id)
            ->where('ativo', true)
            ->with('usuario:id,nome_completo')
            ->get()
            ->map(function ($professor) {
                return [
                    'id' => $professor->id,
                    'nome_completo' => $professor->usuario->nome_completo ?? 'Sem nome',
                ];
            });

        $turmas = \App\Models\Turma::query()
            ->where('tenant_id', $tenant->id)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome', 'ano_letivo']);

        $disciplinas = \App\Models\Disciplina::query()
            ->where('tenant_id', $tenant->id)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome', 'sigla']);

        return Inertia::render('school/notas/Edit', [
            'nota' => [
                'id' => $nota->id,
                'aluno_id' => $nota->aluno_id,
                'professor_id' => $nota->professor_id,
                'turma_id' => $nota->turma_id,
                'disciplina' => $nota->disciplina,
                'disciplina_id' => $nota->disciplina_id,
                'trimestre' => $nota->trimestre,
                'nota' => $nota->nota,
                'frequencia' => $nota->frequencia,
                'comportamento' => $nota->comportamento,
                'observacoes' => $nota->observacoes,
                'ano_letivo' => $nota->ano_letivo,
            ],
            'alunos' => $alunos,
            'professores' => $professores,
            'turmas' => $turmas,
            'disciplinas' => $disciplinas,
        ]);
    }

    /**
     * Update the specified nota.
     */
    public function update(UpdateNotaRequest $request, Nota $nota): RedirectResponse
    {
        $tenant = $this->getTenant();

        if ($nota->tenant_id !== $tenant->id) {
            abort(404);
        }

        $validated = $request->validated();

        $nota->update($validated);

        return redirect()
            ->route('school.notas.edit', $nota)
            ->with('toast', [
                'type' => 'success',
                'title' => 'Nota atualizada',
                'message' => 'As alterações foram salvas com sucesso.',
            ]);
    }

    /**
     * Remove the specified nota.
     */
    public function destroy(Nota $nota): RedirectResponse
    {
        $tenant = $this->getTenant();

        if ($nota->tenant_id !== $tenant->id) {
            abort(404);
        }

        $nota->delete();

        return redirect()
            ->route('school.notas.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Nota excluída',
                'message' => 'A nota foi removida com sucesso.',
            ]);
    }
}
