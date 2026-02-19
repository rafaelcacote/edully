<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Turma;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeacherController extends Controller
{
    /**
     * Get turmas (classes) for the authenticated teacher.
     */
    public function turmas(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isTeacher()) {
            return response()->json([
                'message' => 'Acesso negado. Apenas professores podem acessar esta funcionalidade.',
            ], 403);
        }

        $teacher = $user->teacher()->where('ativo', true)->first();

        if (! $teacher) {
            return response()->json([
                'message' => 'Professor não encontrado ou inativo.',
            ], 403);
        }

        $turmas = $teacher->turmas()
            ->where('ativo', true)
            ->orderBy('ano_letivo', 'desc')
            ->orderBy('serie')
            ->orderBy('turma_letra')
            ->get();

        return response()->json([
            'turmas' => $turmas->map(function ($turma) {
                return [
                    'id' => $turma->id,
                    'nome' => $turma->nome,
                    'serie' => $turma->serie,
                    'turma_letra' => $turma->turma_letra,
                    'ano_letivo' => $turma->ano_letivo,
                    'capacidade' => $turma->capacidade,
                    'created_at' => $turma->created_at?->toISOString(),
                    'updated_at' => $turma->updated_at?->toISOString(),
                ];
            }),
        ]);
    }

    /**
     * Get disciplinas (disciplines) for the authenticated teacher.
     */
    public function disciplinas(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isTeacher()) {
            return response()->json([
                'message' => 'Acesso negado. Apenas professores podem acessar esta funcionalidade.',
            ], 403);
        }

        $teacher = $user->teacher()->where('ativo', true)->first();

        if (! $teacher) {
            return response()->json([
                'message' => 'Professor não encontrado ou inativo.',
            ], 403);
        }

        $disciplinas = $teacher->disciplinas()
            ->where('ativo', true)
            ->orderBy('nome')
            ->get();

        return response()->json([
            'disciplinas' => $disciplinas->map(function ($disciplina) {
                return [
                    'id' => $disciplina->id,
                    'nome' => $disciplina->nome,
                    'sigla' => $disciplina->sigla,
                    'descricao' => $disciplina->descricao,
                    'carga_horaria_semanal' => $disciplina->carga_horaria_semanal,
                    'created_at' => $disciplina->created_at?->toISOString(),
                    'updated_at' => $disciplina->updated_at?->toISOString(),
                ];
            }),
        ]);
    }

    /**
     * Get alunos (students) for the authenticated teacher.
     */
    public function alunos(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isTeacher()) {
            return response()->json([
                'message' => 'Acesso negado. Apenas professores podem acessar esta funcionalidade.',
            ], 403);
        }

        $teacher = $user->teacher()->where('ativo', true)->first();

        if (! $teacher) {
            return response()->json([
                'message' => 'Professor não encontrado ou inativo.',
            ], 403);
        }

        // Buscar turmas do professor
        $turmas = $teacher->turmas()
            ->where('ativo', true)
            ->get();

        if ($turmas->isEmpty()) {
            return response()->json([
                'alunos' => [],
            ]);
        }

        $turmaIds = $turmas->pluck('id')->toArray();

        // Buscar alunos das turmas do professor através da tabela de matrículas
        $driver = DB::connection('shared')->getDriverName();
        $matriculasTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';

        $matriculas = DB::connection('shared')
            ->table($matriculasTable)
            ->whereIn('turma_id', $turmaIds)
            ->where('status', 'ativo')
            ->where('tenant_id', $teacher->tenant_id)
            ->get(['aluno_id', 'turma_id', 'data_matricula']);

        $studentIds = $matriculas->pluck('aluno_id')->unique()->toArray();

        if (empty($studentIds)) {
            return response()->json([
                'alunos' => [],
            ]);
        }

        // Buscar alunos com seus dados
        $students = Student::whereIn('id', $studentIds)
            ->where('ativo', true)
            ->where('tenant_id', $teacher->tenant_id)
            ->get([
                'id',
                'tenant_id',
                'nome',
                'nome_social',
                'foto_url',
                'data_nascimento',
                'ativo',
            ]);

        // Mapear turmas por ID para facilitar acesso
        $turmasMap = $turmas->keyBy('id');

        // Mapear alunos com suas turmas
        $alunosData = $students->map(function ($student) use ($matriculas, $turmasMap) {
            // Buscar turmas do aluno que são do professor
            $studentMatriculas = $matriculas->where('aluno_id', $student->id);
            $studentTurmas = $studentMatriculas->map(function ($matricula) use ($turmasMap) {
                $turma = $turmasMap->get($matricula->turma_id);
                if (! $turma) {
                    return null;
                }

                return [
                    'id' => $turma->id,
                    'nome' => $turma->nome,
                    'serie' => $turma->serie,
                    'turma_letra' => $turma->turma_letra,
                    'ano_letivo' => $turma->ano_letivo,
                    'data_matricula' => $matricula->data_matricula,
                ];
            })->filter()->values();

            return [
                'id' => $student->id,
                'nome' => $student->nome,
                'nome_social' => $student->nome_social,
                'foto_url' => $student->foto_url,
                'data_nascimento' => $student->data_nascimento?->format('Y-m-d'),
                'turmas' => $studentTurmas,
            ];
        })->values();

        return response()->json([
            'alunos' => $alunosData,
        ]);
    }

    /**
     * Get alunos (students) for a specific turma (class) of the authenticated teacher.
     */
    public function turmasAlunos(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        if (! $user->isTeacher()) {
            return response()->json([
                'message' => 'Acesso negado. Apenas professores podem acessar esta funcionalidade.',
            ], 403);
        }

        $teacher = $user->teacher()->where('ativo', true)->first();

        if (! $teacher) {
            return response()->json([
                'message' => 'Professor não encontrado ou inativo.',
            ], 403);
        }

        // Verificar se a turma existe e pertence ao professor
        $turma = $teacher->turmas()
            ->where('turmas.id', $id)
            ->where('turmas.ativo', true)
            ->first();

        if (! $turma) {
            return response()->json([
                'message' => 'Turma não encontrada ou você não tem permissão para acessá-la.',
            ], 404);
        }

        // Buscar alunos da turma através do relacionamento
        $alunos = $turma->alunos()
            ->where('alunos.ativo', true)
            ->orderBy('alunos.nome')
            ->select([
                'alunos.id',
                'alunos.tenant_id',
                'alunos.nome',
                'alunos.nome_social',
                'alunos.foto_url',
                'alunos.data_nascimento',
                'alunos.ativo',
            ])
            ->get();

        // Buscar informações de matrícula para cada aluno
        $driver = DB::connection('shared')->getDriverName();
        $matriculasTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';

        $matriculas = DB::connection('shared')
            ->table($matriculasTable)
            ->where('turma_id', $turma->id)
            ->where('status', 'ativo')
            ->where('tenant_id', $teacher->tenant_id)
            ->get(['aluno_id', 'data_matricula']);

        $matriculasMap = $matriculas->keyBy('aluno_id');

        $alunosData = $alunos->map(function ($aluno) use ($matriculasMap) {
            $matricula = $matriculasMap->get($aluno->id);

            return [
                'id' => $aluno->id,
                'nome' => $aluno->nome,
                'nome_social' => $aluno->nome_social,
                'foto_url' => $aluno->foto_url,
                'data_nascimento' => $aluno->data_nascimento?->format('Y-m-d'),
                'data_matricula' => $matricula?->data_matricula,
            ];
        })->values();

        return response()->json([
            'turma' => [
                'id' => $turma->id,
                'nome' => $turma->nome,
                'serie' => $turma->serie,
                'turma_letra' => $turma->turma_letra,
                'ano_letivo' => $turma->ano_letivo,
            ],
            'alunos' => $alunosData,
        ]);
    }
}
