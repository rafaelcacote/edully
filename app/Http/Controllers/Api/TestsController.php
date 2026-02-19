<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTestRequest;
use App\Http\Requests\Api\UpdateTestRequest;
use App\Http\Resources\Api\TestResource;
use App\Models\Responsavel;
use App\Models\Test;
use App\Models\Turma;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TestsController extends Controller
{
    /**
     * Get tests for the authenticated user.
     * For teachers: returns tests they created.
     * For responsaveis: returns tests for their students' classes.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Test::query();

        if ($user->isTeacher()) {
            // Se for professor, buscar apenas provas que ele criou
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
            // Se for responsável, buscar provas das turmas dos alunos vinculados
            $responsaveis = Responsavel::where('usuario_id', $user->id)->get();
            $responsavelIds = $responsaveis->pluck('id')->toArray();

            if (empty($responsavelIds)) {
                return response()->json([
                    'tests' => [],
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
                    'tests' => [],
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
                    'tests' => [],
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

        // Ordenar por data da prova (mais próximos primeiro) e depois por data de criação
        $tests = $query
            ->with([
                'turma:id,nome,serie,turma_letra,ano_letivo',
                'professor:id,usuario_id',
                'professor.usuario:id,nome_completo',
                'disciplinaRelation:id,nome,sigla',
            ])
            ->orderBy('data_prova', 'asc')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'tests' => TestResource::collection($tests->items()),
            'meta' => [
                'current_page' => $tests->currentPage(),
                'last_page' => $tests->lastPage(),
                'per_page' => $tests->perPage(),
                'total' => $tests->total(),
            ],
        ]);
    }

    /**
     * Get a specific test.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $test = Test::with([
            'turma:id,nome,serie,turma_letra,ano_letivo',
            'professor:id,usuario_id',
            'professor.usuario:id,nome_completo',
            'disciplinaRelation:id,nome,sigla',
        ])->findOrFail($id);

        // Verificar permissão
        if ($user->isTeacher()) {
            $teacher = $user->teacher()->where('ativo', true)->first();
            if (! $teacher || $test->professor_id !== $teacher->id || $test->tenant_id !== $teacher->tenant_id) {
                return response()->json([
                    'message' => 'Prova não encontrada ou você não tem permissão para acessá-la.',
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

            // Verificar se algum aluno do responsável está na turma da prova
            $hasAccess = DB::connection('shared')
                ->table($matriculasTable)
                ->whereIn('aluno_id', $alunoIds)
                ->where('turma_id', $test->turma_id)
                ->where('status', 'ativo')
                ->exists();

            if (! $hasAccess) {
                return response()->json([
                    'message' => 'Prova não encontrada ou você não tem permissão para acessá-la.',
                ], 403);
            }
        } else {
            return response()->json([
                'message' => 'Acesso negado. Apenas professores e responsáveis podem acessar esta funcionalidade.',
            ], 403);
        }

        return response()->json([
            'test' => new TestResource($test),
        ]);
    }

    /**
     * Create a new test (only for teachers).
     */
    public function store(StoreTestRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // Buscar tenant do professor
        $teacher = $user->teacher()->where('ativo', true)->first();
        if (! $teacher) {
            return response()->json([
                'message' => 'Acesso negado. Apenas professores podem criar provas.',
            ], 403);
        }

        $tenantId = $teacher->tenant_id;

        $test = Test::create([
            ...$validated,
            'tenant_id' => $tenantId,
            'professor_id' => $teacher->id,
        ]);

        $test->load([
            'turma:id,nome,serie,turma_letra,ano_letivo',
            'professor:id,usuario_id',
            'professor.usuario:id,nome_completo',
            'disciplinaRelation:id,nome,sigla',
        ]);

        return response()->json([
            'message' => 'Prova criada com sucesso.',
            'test' => new TestResource($test),
        ], 201);
    }

    /**
     * Update the specified test (only for teachers).
     */
    public function update(UpdateTestRequest $request, string $id): JsonResponse
    {
        $user = $request->user();
        $test = Test::findOrFail($id);

        // Verificar se o professor tem permissão para atualizar esta prova
        $teacher = $user->teacher()->where('ativo', true)->first();
        if (! $teacher || $test->professor_id !== $teacher->id || $test->tenant_id !== $teacher->tenant_id) {
            return response()->json([
                'message' => 'Prova não encontrada ou você não tem permissão para atualizá-la.',
            ], 403);
        }

        $validated = $request->validated();

        $test->update($validated);

        $test->load([
            'turma:id,nome,serie,turma_letra,ano_letivo',
            'professor:id,usuario_id',
            'professor.usuario:id,nome_completo',
            'disciplinaRelation:id,nome,sigla',
        ]);

        return response()->json([
            'message' => 'Prova atualizada com sucesso.',
            'test' => new TestResource($test),
        ]);
    }

    /**
     * Remove the specified test (only for teachers).
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $test = Test::findOrFail($id);

        // Verificar se o professor tem permissão para deletar esta prova
        $teacher = $user->teacher()->where('ativo', true)->first();
        if (! $teacher || $test->professor_id !== $teacher->id || $test->tenant_id !== $teacher->tenant_id) {
            return response()->json([
                'message' => 'Prova não encontrada ou você não tem permissão para removê-la.',
            ], 403);
        }

        $test->delete();

        return response()->json([
            'message' => 'Prova removida com sucesso.',
        ]);
    }
}
