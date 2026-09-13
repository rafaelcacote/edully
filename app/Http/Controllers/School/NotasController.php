<?php

namespace App\Http\Controllers\School;

use App\Actions\School\SyncLoteNotasAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\StoreNotaRequest;
use App\Http\Requests\School\SyncLoteNotasRequest;
use App\Http\Requests\School\UpdateNotaRequest;
use App\Models\Disciplina;
use App\Models\Nota;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Turma;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $filters = $request->only(['search', 'aluno_id', 'professor_id', 'turma_id', 'disciplina_id', 'bimestre', 'ano_letivo']);

        $notas = Nota::query()
            ->where('tenant_id', $tenant->id)
            ->with([
                'aluno:id,nome',
                'professor.usuario:id,nome_completo',
                'turma:id,nome',
                'disciplinaRelation:id,nome,sigla',
            ])
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $search = trim($search);
                $query->where(function ($q) use ($search) {
                    $q->where('disciplina', 'ilike', "%{$search}%")
                        ->orWhere('observacoes', 'ilike', "%{$search}%")
                        ->orWhereHas('aluno', function ($q) use ($search) {
                            $q->where('nome', 'ilike', "%{$search}%");
                        })
                        ->orWhereHas('disciplinaRelation', function ($q) use ($search) {
                            $q->where('nome', 'ilike', "%{$search}%")
                                ->orWhere('sigla', 'ilike', "%{$search}%");
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
            ->when($filters['bimestre'] ?? null, function ($query, string $bimestre) {
                $query->where('bimestre', $bimestre);
            })
            ->when($filters['ano_letivo'] ?? null, function ($query, string $anoLetivo) {
                $query->where('ano_letivo', $anoLetivo);
            })
            ->orderBy('ano_letivo', 'desc')
            ->orderBy('bimestre')
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
                    'disciplina' => $nota->disciplinaRelation?->nome
                        ?? $nota->getAttributes()['disciplina']
                        ?? null,
                    'disciplina_id' => $nota->disciplina_id,
                    'bimestre' => $nota->bimestre,
                    'nota' => $nota->nota,
                    'comportamento' => $nota->comportamento,
                    'observacoes' => $nota->observacoes,
                    'ano_letivo' => $nota->ano_letivo,
                ];
            });

        $alunos = Student::query()
            ->where('tenant_id', $tenant->id)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome']);

        $professores = Teacher::query()
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

        $turmas = Turma::query()
            ->where('tenant_id', $tenant->id)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome']);

        $disciplinas = Disciplina::query()
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

        return Inertia::render('school/notas/Create', $this->formPayload($tenant->id));
    }

    /**
     * Store a newly created nota.
     */
    public function store(StoreNotaRequest $request): RedirectResponse
    {
        $tenant = $this->getTenant();
        $validated = $request->validated();

        $disciplina = Disciplina::query()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($validated['disciplina_id']);

        Nota::create([
            ...$validated,
            'tenant_id' => $tenant->id,
            'disciplina' => $disciplina->nome,
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

        return Inertia::render('school/notas/Edit', [
            ...$this->formPayload($tenant->id),
            'nota' => [
                'id' => $nota->id,
                'aluno_id' => $nota->aluno_id,
                'professor_id' => $nota->professor_id,
                'turma_id' => $nota->turma_id,
                'disciplina_id' => $nota->disciplina_id,
                'bimestre' => $nota->bimestre,
                'nota' => $nota->nota,
                'comportamento' => $nota->comportamento,
                'observacoes' => $nota->observacoes,
                'ano_letivo' => $nota->ano_letivo,
            ],
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

        $disciplina = Disciplina::query()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($validated['disciplina_id']);

        $nota->update([
            ...$validated,
            'disciplina' => $disciplina->nome,
        ]);

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

    /**
     * Show the batch grade entry screen.
     */
    public function lote(Request $request): Response
    {
        $tenant = $this->getTenant();
        $filters = $request->only(['turma_id', 'disciplina_id', 'bimestre']);

        $payload = $this->formPayload($tenant->id);

        $alunos = [];
        $professorPadraoId = null;
        $anoLetivo = null;
        $turmaSelecionada = null;
        $disciplinaSelecionada = null;

        if (! empty($filters['turma_id'])) {
            $turma = Turma::query()
                ->where('tenant_id', $tenant->id)
                ->where('id', $filters['turma_id'])
                ->first();

            if ($turma) {
                $turmaSelecionada = [
                    'id' => $turma->id,
                    'nome' => $turma->nome,
                    'ano_letivo' => $turma->ano_letivo,
                ];
                $anoLetivo = $turma->ano_letivo;

                $driver = DB::connection('shared')->getDriverName();
                $matriculasTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';
                $alunosTable = $driver === 'sqlite' ? 'alunos' : 'escola.alunos';
                $turmaDisciplinasTable = $driver === 'sqlite' ? 'turma_disciplinas' : 'escola.turma_disciplinas';

                $notasExistentes = collect();

                if (! empty($filters['disciplina_id']) && ! empty($filters['bimestre'])) {
                    $notasExistentes = Nota::query()
                        ->where('tenant_id', $tenant->id)
                        ->where('turma_id', $turma->id)
                        ->where('disciplina_id', $filters['disciplina_id'])
                        ->where('bimestre', $filters['bimestre'])
                        ->where('ano_letivo', $turma->ano_letivo)
                        ->get(['aluno_id', 'nota', 'professor_id'])
                        ->keyBy('aluno_id');

                    $disciplina = Disciplina::query()
                        ->where('tenant_id', $tenant->id)
                        ->where('id', $filters['disciplina_id'])
                        ->first(['id', 'nome', 'sigla']);

                    if ($disciplina) {
                        $disciplinaSelecionada = [
                            'id' => $disciplina->id,
                            'nome' => $disciplina->nome,
                            'sigla' => $disciplina->sigla,
                        ];
                    }

                    $professorPadraoId = DB::connection('shared')
                        ->table($turmaDisciplinasTable)
                        ->where('tenant_id', $tenant->id)
                        ->where('turma_id', $turma->id)
                        ->where('disciplina_id', $filters['disciplina_id'])
                        ->value('professor_id');

                    if (! $professorPadraoId && $notasExistentes->isNotEmpty()) {
                        $professorPadraoId = $notasExistentes->first()?->professor_id;
                    }
                }

                $alunos = DB::connection('shared')
                    ->table($matriculasTable.' as matriculas')
                    ->join($alunosTable.' as alunos', 'alunos.id', '=', 'matriculas.aluno_id')
                    ->where('matriculas.tenant_id', $tenant->id)
                    ->where('matriculas.turma_id', $turma->id)
                    ->where('matriculas.status', 'ativo')
                    ->whereNull('alunos.deleted_at')
                    ->where('alunos.ativo', true)
                    ->orderBy('alunos.nome')
                    ->get([
                        'alunos.id',
                        'alunos.nome',
                    ])
                    ->map(function ($aluno) use ($notasExistentes) {
                        $notaExistente = $notasExistentes->get($aluno->id);

                        return [
                            'id' => $aluno->id,
                            'nome' => $aluno->nome,
                            'nota' => $notaExistente?->nota,
                        ];
                    })
                    ->values()
                    ->all();
            }
        }

        return Inertia::render('school/notas/Lote', [
            'professores' => $payload['professores'],
            'turmas' => $payload['turmas'],
            'grade' => $payload['grade'],
            'filters' => [
                'turma_id' => $filters['turma_id'] ?? null,
                'disciplina_id' => $filters['disciplina_id'] ?? null,
                'bimestre' => $filters['bimestre'] ?? null,
            ],
            'turma' => $turmaSelecionada,
            'disciplina' => $disciplinaSelecionada,
            'alunos' => $alunos,
            'professor_padrao_id' => $professorPadraoId,
            'ano_letivo' => $anoLetivo,
        ]);
    }

    /**
     * Sync batch grades for a class/subject/period.
     */
    public function syncLote(SyncLoteNotasRequest $request, SyncLoteNotasAction $action): RedirectResponse
    {
        $tenant = $this->getTenant();
        $validated = $request->validated();

        $result = $action->execute($tenant, $validated);

        return redirect()
            ->route('school.notas.lote', [
                'turma_id' => $validated['turma_id'],
                'disciplina_id' => $validated['disciplina_id'],
                'bimestre' => $validated['bimestre'],
            ])
            ->with('toast', [
                'type' => 'success',
                'title' => 'Notas salvas',
                'message' => sprintf(
                    '%d nota(s) salva(s)%s.',
                    $result['saved'],
                    $result['cleared'] > 0 ? " e {$result['cleared']} removida(s)" : ''
                ),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formPayload(string $tenantId): array
    {
        $professores = Teacher::query()
            ->where('tenant_id', $tenantId)
            ->where('ativo', true)
            ->with('usuario:id,nome_completo')
            ->get()
            ->map(function ($professor) {
                return [
                    'id' => $professor->id,
                    'nome_completo' => $professor->usuario->nome_completo ?? 'Sem nome',
                ];
            });

        $turmas = Turma::query()
            ->where('tenant_id', $tenantId)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome', 'ano_letivo']);

        $driver = DB::connection('shared')->getDriverName();
        $pivotTable = $driver === 'sqlite' ? 'turma_disciplinas' : 'escola.turma_disciplinas';
        $disciplinasTable = $driver === 'sqlite' ? 'disciplinas' : 'escola.disciplinas';

        $grade = DB::connection('shared')
            ->table($pivotTable.' as td')
            ->join($disciplinasTable.' as d', 'd.id', '=', 'td.disciplina_id')
            ->where('td.tenant_id', $tenantId)
            ->where('d.ativo', true)
            ->whereNull('d.deleted_at')
            ->orderBy('d.nome')
            ->get([
                'td.turma_id',
                'td.professor_id',
                'd.id as disciplina_id',
                'd.nome',
                'd.sigla',
            ])
            ->groupBy('turma_id')
            ->map(fn ($items) => $items->map(fn ($item) => [
                'id' => $item->disciplina_id,
                'nome' => $item->nome,
                'sigla' => $item->sigla,
                'professor_id' => $item->professor_id,
            ])->values())
            ->toArray();

        $alunos = Student::query()
            ->where('tenant_id', $tenantId)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome']);

        return [
            'alunos' => $alunos,
            'professores' => $professores,
            'turmas' => $turmas,
            'grade' => $grade,
        ];
    }
}
