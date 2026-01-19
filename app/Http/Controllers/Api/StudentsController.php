<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Responsavel;
use App\Models\Student;
use App\Models\Turma;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentsController extends Controller
{
    /**
     * Get all students for the authenticated responsavel.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Verificar se o usuário é um responsável
        if (!$user->isResponsavel()) {
            return response()->json([
                'message' => 'Acesso negado. Apenas responsáveis podem acessar esta funcionalidade.',
            ], 403);
        }

        // Buscar todos os registros de responsável do usuário (pode ter múltiplos tenants)
        $responsaveis = Responsavel::where('usuario_id', $user->id)
            ->with(['tenant:id,nome,logo_url'])
            ->get();

        if ($responsaveis->isEmpty()) {
            return response()->json([
                'students' => [],
            ]);
        }

        // Buscar todos os alunos vinculados ao responsável
        $driver = DB::connection('shared')->getDriverName();
        $pivotTable = $driver === 'sqlite' ? 'aluno_responsavel' : 'escola.aluno_responsavel';
        $matriculasTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';

        $responsavelIds = $responsaveis->pluck('id')->toArray();

        // Buscar alunos através da tabela pivot
        $alunoResponsavel = DB::connection('shared')
            ->table($pivotTable)
            ->whereIn('responsavel_id', $responsavelIds)
            ->get(['aluno_id', 'tenant_id', 'principal']);

        $studentIds = $alunoResponsavel->pluck('aluno_id')->unique()->toArray();

        if (empty($studentIds)) {
            return response()->json([
                'students' => [],
            ]);
        }

        // Buscar alunos com seus dados
        $students = Student::whereIn('id', $studentIds)
            ->where('ativo', true)
            ->with(['tenant:id,nome,logo_url'])
            ->get([
                'id',
                'tenant_id',
                'nome',
                'nome_social',
                'foto_url',
                'data_nascimento',
                'ativo',
            ]);

        // Buscar turmas ativas dos alunos
        $matriculas = DB::connection('shared')
            ->table($matriculasTable)
            ->whereIn('aluno_id', $studentIds)
            ->where('status', 'ativo')
            ->get(['aluno_id', 'turma_id', 'tenant_id', 'data_matricula']);

        $turmaIds = $matriculas->pluck('turma_id')->unique()->toArray();
        $turmas = Turma::whereIn('id', $turmaIds)
            ->where('ativo', true)
            ->get([
                'id',
                'tenant_id',
                'nome',
                'serie',
                'turma_letra',
                'ano_letivo',
            ])
            ->keyBy('id');

        // Mapear alunos com suas turmas e escolas
        $studentsData = $students->map(function ($student) use ($alunoResponsavel, $matriculas, $turmas) {
            // Buscar tenant do aluno (já carregado via eager loading)
            $tenant = $student->tenant;

            // Buscar turmas do aluno
            $studentMatriculas = $matriculas->where('aluno_id', $student->id);
            $studentTurmas = $studentMatriculas->map(function ($matricula) use ($turmas) {
                $turma = $turmas->get($matricula->turma_id);
                if (!$turma) {
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

            // Verificar se é responsável principal
            $responsavelData = $alunoResponsavel->firstWhere('aluno_id', $student->id);
            $isPrincipal = $responsavelData ? (bool) $responsavelData->principal : false;

            return [
                'id' => $student->id,
                'nome' => $student->nome,
                'nome_social' => $student->nome_social,
                'foto_url' => $student->foto_url,
                'data_nascimento' => $student->data_nascimento?->format('Y-m-d'),
                'is_principal' => $isPrincipal,
                'school' => $tenant ? [
                    'id' => $tenant->id,
                    'nome' => $tenant->nome,
                    'logo_url' => $tenant->logo_url,
                ] : null,
                'turmas' => $studentTurmas,
            ];
        })->values();

        return response()->json([
            'students' => $studentsData,
        ]);
    }

    /**
     * Get a specific student for the authenticated responsavel.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        // Verificar se o usuário é um responsável
        if (!$user->isResponsavel()) {
            return response()->json([
                'message' => 'Acesso negado. Apenas responsáveis podem acessar esta funcionalidade.',
            ], 403);
        }

        // Buscar responsável do usuário
        $responsaveis = Responsavel::where('usuario_id', $user->id)->get();
        $responsavelIds = $responsaveis->pluck('id')->toArray();

        // Verificar se o aluno está vinculado ao responsável
        $driver = DB::connection('shared')->getDriverName();
        $pivotTable = $driver === 'sqlite' ? 'aluno_responsavel' : 'escola.aluno_responsavel';
        $matriculasTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';

        $isLinked = DB::connection('shared')
            ->table($pivotTable)
            ->whereIn('responsavel_id', $responsavelIds)
            ->where('aluno_id', $id)
            ->exists();

        if (!$isLinked) {
            return response()->json([
                'message' => 'Aluno não encontrado ou você não tem permissão para acessar este aluno.',
            ], 404);
        }

        // Buscar aluno
        $student = Student::where('id', $id)
            ->where('ativo', true)
            ->with(['tenant:id,nome,logo_url'])
            ->firstOrFail([
                'id',
                'tenant_id',
                'nome',
                'nome_social',
                'foto_url',
                'data_nascimento',
                'informacoes_medicas',
                'ativo',
            ]);

        // Buscar turmas ativas do aluno
        $matriculas = DB::connection('shared')
            ->table($matriculasTable)
            ->where('aluno_id', $student->id)
            ->where('status', 'ativo')
            ->get(['turma_id', 'data_matricula']);

        $turmaIds = $matriculas->pluck('turma_id')->unique()->toArray();
        $turmas = Turma::whereIn('id', $turmaIds)
            ->where('ativo', true)
            ->get([
                'id',
                'nome',
                'serie',
                'turma_letra',
                'ano_letivo',
            ]);

        $studentTurmas = $matriculas->map(function ($matricula) use ($turmas) {
            $turma = $turmas->firstWhere('id', $matricula->turma_id);
            if (!$turma) {
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

        // Verificar se é responsável principal
        $responsavelData = DB::connection('shared')
            ->table($pivotTable)
            ->whereIn('responsavel_id', $responsavelIds)
            ->where('aluno_id', $student->id)
            ->first();

        return response()->json([
            'student' => [
                'id' => $student->id,
                'nome' => $student->nome,
                'nome_social' => $student->nome_social,
                'foto_url' => $student->foto_url,
                'data_nascimento' => $student->data_nascimento?->format('Y-m-d'),
                'informacoes_medicas' => $student->informacoes_medicas,
                'is_principal' => $responsavelData ? (bool) $responsavelData->principal : false,
                'school' => $student->tenant ? [
                    'id' => $student->tenant->id,
                    'nome' => $student->tenant->nome,
                    'logo_url' => $student->tenant->logo_url,
                ] : null,
                'turmas' => $studentTurmas,
            ],
        ]);
    }
}
