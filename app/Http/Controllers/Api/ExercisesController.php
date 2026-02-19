<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreExerciseRequest;
use App\Http\Requests\Api\UpdateExerciseRequest;
use App\Http\Resources\Api\ExerciseResource;
use App\Models\Exercise;
use App\Models\Responsavel;
use App\Models\Turma;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExercisesController extends Controller
{
    /**
     * Get exercises for the authenticated user.
     * For teachers: returns exercises they created.
     * For responsaveis: returns exercises for their students' classes.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Exercise::query();

        if ($user->isTeacher()) {
            // Se for professor, buscar apenas exercícios que ele criou
            $teacher = $user->teacher()->where('ativo', true)->first();
            if (! $teacher) {
                return response()->json([
                    'message' => 'Acesso negado. Apenas professores podem acessar esta funcionalidade.',
                ], 403);
            }

            $query->where('tenant_id', $teacher->tenant_id)
                ->where('professor_id', $teacher->id);

            // Filtrar por turma se fornecido
            if ($request->has('turma_id')) {
                $turmaId = $request->input('turma_id');
                // Verificar se o professor tem acesso a esta turma usando a relação professor_turma
                $turma = $teacher->turmas()
                    ->where('id', $turmaId)
                    ->first();

                if (! $turma) {
                    return response()->json([
                        'message' => 'Turma não encontrada ou você não tem acesso a esta turma.',
                    ], 403);
                }

                $query->where('turma_id', $turmaId);
            }

            // Filtrar por disciplina se fornecido
            if ($request->has('disciplina_id')) {
                $disciplinaId = $request->input('disciplina_id');
                // Verificar se o professor tem acesso a esta disciplina
                $driver = DB::connection('shared')->getDriverName();
                $pivotTable = $driver === 'sqlite' ? 'professor_disciplinas' : 'escola.professor_disciplinas';

                $hasAccess = DB::connection('shared')
                    ->table($pivotTable)
                    ->where('tenant_id', $teacher->tenant_id)
                    ->where('professor_id', $teacher->id)
                    ->where('disciplina_id', $disciplinaId)
                    ->exists();

                if (! $hasAccess) {
                    return response()->json([
                        'message' => 'Disciplina não encontrada ou você não tem acesso a esta disciplina.',
                    ], 403);
                }

                $query->where('disciplina_id', $disciplinaId);
            }
        } elseif ($user->isResponsavel()) {
            // Se for responsável, buscar exercícios das turmas dos alunos vinculados
            $responsaveis = Responsavel::where('usuario_id', $user->id)->get();
            $responsavelIds = $responsaveis->pluck('id')->toArray();

            if (empty($responsavelIds)) {
                return response()->json([
                    'exercises' => [],
                    'meta' => [
                        'current_page' => 1,
                        'last_page' => 1,
                        'per_page' => 15,
                        'total' => 0,
                    ],
                ]);
            }

            // Buscar alunos vinculados ao responsável
            $driver = DB::connection('shared')->getDriverName();
            $pivotTable = $driver === 'sqlite' ? 'aluno_responsavel' : 'escola.aluno_responsavel';
            $matriculasTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';

            $alunoIds = DB::connection('shared')
                ->table($pivotTable)
                ->whereIn('responsavel_id', $responsavelIds)
                ->pluck('aluno_id')
                ->unique()
                ->toArray();

            if (empty($alunoIds)) {
                return response()->json([
                    'exercises' => [],
                    'meta' => [
                        'current_page' => 1,
                        'last_page' => 1,
                        'per_page' => 15,
                        'total' => 0,
                    ],
                ]);
            }

            // Buscar turmas dos alunos
            $turmaIds = DB::connection('shared')
                ->table($matriculasTable)
                ->whereIn('aluno_id', $alunoIds)
                ->where('status', 'ativo')
                ->pluck('turma_id')
                ->unique()
                ->toArray();

            if (empty($turmaIds)) {
                return response()->json([
                    'exercises' => [],
                    'meta' => [
                        'current_page' => 1,
                        'last_page' => 1,
                        'per_page' => 15,
                        'total' => 0,
                    ],
                ]);
            }

            $query->whereIn('turma_id', $turmaIds);

            // Filtrar por aluno se fornecido
            if ($request->has('aluno_id')) {
                $alunoId = $request->input('aluno_id');
                if (! in_array($alunoId, $alunoIds)) {
                    return response()->json([
                        'message' => 'Aluno não encontrado ou você não tem permissão para acessar este aluno.',
                    ], 403);
                }

                // Buscar turmas do aluno específico
                $alunoTurmaIds = DB::connection('shared')
                    ->table($matriculasTable)
                    ->where('aluno_id', $alunoId)
                    ->where('status', 'ativo')
                    ->pluck('turma_id')
                    ->unique()
                    ->toArray();

                $query->whereIn('turma_id', $alunoTurmaIds);
            }

            // Filtrar por turma se fornecido
            if ($request->has('turma_id')) {
                $turmaId = $request->input('turma_id');
                if (! in_array($turmaId, $turmaIds)) {
                    return response()->json([
                        'message' => 'Turma não encontrada ou você não tem permissão para acessar esta turma.',
                    ], 403);
                }

                $query->where('turma_id', $turmaId);
            }
        } else {
            return response()->json([
                'message' => 'Acesso negado. Apenas professores e responsáveis podem acessar esta funcionalidade.',
            ], 403);
        }

        // Ordenar por data de entrega (mais próximos primeiro) e depois por data de criação
        $exercises = $query
            ->with([
                'turma:id,nome,serie,turma_letra,ano_letivo',
                'professor:id,usuario_id',
                'professor.usuario:id,nome_completo',
                'disciplinaRelation:id,nome,sigla',
            ])
            ->orderBy('data_entrega', 'asc')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'exercises' => ExerciseResource::collection($exercises->items()),
            'meta' => [
                'current_page' => $exercises->currentPage(),
                'last_page' => $exercises->lastPage(),
                'per_page' => $exercises->perPage(),
                'total' => $exercises->total(),
            ],
        ]);
    }

    /**
     * Get a specific exercise.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $exercise = Exercise::with([
            'turma:id,nome,serie,turma_letra,ano_letivo',
            'professor:id,usuario_id',
            'professor.usuario:id,nome_completo',
            'disciplinaRelation:id,nome,sigla',
        ])->findOrFail($id);

        // Verificar permissão
        if ($user->isTeacher()) {
            $teacher = $user->teacher()->where('ativo', true)->first();
            if (! $teacher || $exercise->professor_id !== $teacher->id || $exercise->tenant_id !== $teacher->tenant_id) {
                return response()->json([
                    'message' => 'Exercício não encontrado ou você não tem permissão para acessá-lo.',
                ], 403);
            }
        } elseif ($user->isResponsavel()) {
            $responsaveis = Responsavel::where('usuario_id', $user->id)->get();
            $responsavelIds = $responsaveis->pluck('id')->toArray();

            $driver = DB::connection('shared')->getDriverName();
            $pivotTable = $driver === 'sqlite' ? 'aluno_responsavel' : 'escola.aluno_responsavel';
            $matriculasTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';

            // Buscar alunos vinculados ao responsável
            $alunoIds = DB::connection('shared')
                ->table($pivotTable)
                ->whereIn('responsavel_id', $responsavelIds)
                ->pluck('aluno_id')
                ->unique()
                ->toArray();

            // Verificar se algum aluno do responsável está na turma do exercício
            $hasAccess = DB::connection('shared')
                ->table($matriculasTable)
                ->whereIn('aluno_id', $alunoIds)
                ->where('turma_id', $exercise->turma_id)
                ->where('status', 'ativo')
                ->exists();

            if (! $hasAccess) {
                return response()->json([
                    'message' => 'Exercício não encontrado ou você não tem permissão para acessá-lo.',
                ], 403);
            }
        } else {
            return response()->json([
                'message' => 'Acesso negado. Apenas professores e responsáveis podem acessar esta funcionalidade.',
            ], 403);
        }

        return response()->json([
            'exercise' => new ExerciseResource($exercise),
        ]);
    }

    /**
     * Create a new exercise (only for teachers).
     */
    public function store(StoreExerciseRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // Buscar tenant do professor
        $teacher = $user->teacher()->where('ativo', true)->first();
        if (! $teacher) {
            return response()->json([
                'message' => 'Acesso negado. Apenas professores podem criar exercícios.',
            ], 403);
        }

        $tenantId = $teacher->tenant_id;

        // Buscar disciplina para pegar o nome caso não tenha disciplina_id
        $disciplinaNome = null;
        if (isset($validated['disciplina_id'])) {
            $disciplina = \App\Models\Disciplina::find($validated['disciplina_id']);
            $disciplinaNome = $disciplina?->nome;
        }

        $exercise = Exercise::create([
            ...$validated,
            'tenant_id' => $tenantId,
            'professor_id' => $teacher->id,
            'disciplina' => $disciplinaNome ?? $validated['disciplina'] ?? null,
        ]);

        $exercise->load([
            'turma:id,nome,serie,turma_letra,ano_letivo',
            'professor:id,usuario_id',
            'professor.usuario:id,nome_completo',
            'disciplinaRelation:id,nome,sigla',
        ]);

        return response()->json([
            'message' => 'Exercício criado com sucesso.',
            'exercise' => new ExerciseResource($exercise),
        ], 201);
    }

    /**
     * Update the specified exercise (only for teachers).
     */
    public function update(UpdateExerciseRequest $request, string $id): JsonResponse
    {
        $user = $request->user();
        $exercise = Exercise::findOrFail($id);

        // Verificar se o professor tem permissão para atualizar este exercício
        $teacher = $user->teacher()->where('ativo', true)->first();
        if (! $teacher || $exercise->professor_id !== $teacher->id || $exercise->tenant_id !== $teacher->tenant_id) {
            return response()->json([
                'message' => 'Exercício não encontrado ou você não tem permissão para atualizá-lo.',
            ], 403);
        }

        $validated = $request->validated();

        // Atualizar nome da disciplina se disciplina_id foi alterado
        if (isset($validated['disciplina_id'])) {
            $disciplina = \App\Models\Disciplina::find($validated['disciplina_id']);
            $validated['disciplina'] = $disciplina?->nome;
        }

        $exercise->update($validated);

        $exercise->load([
            'turma:id,nome,serie,turma_letra,ano_letivo',
            'professor:id,usuario_id',
            'professor.usuario:id,nome_completo',
            'disciplinaRelation:id,nome,sigla',
        ]);

        return response()->json([
            'message' => 'Exercício atualizado com sucesso.',
            'exercise' => new ExerciseResource($exercise),
        ]);
    }

    /**
     * Remove the specified exercise (only for teachers).
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $exercise = Exercise::findOrFail($id);

        // Verificar se o professor tem permissão para deletar este exercício
        $teacher = $user->teacher()->where('ativo', true)->first();
        if (! $teacher || $exercise->professor_id !== $teacher->id || $exercise->tenant_id !== $teacher->tenant_id) {
            return response()->json([
                'message' => 'Exercício não encontrado ou você não tem permissão para removê-lo.',
            ], 403);
        }

        $exercise->delete();

        return response()->json([
            'message' => 'Exercício removido com sucesso.',
        ]);
    }
}
