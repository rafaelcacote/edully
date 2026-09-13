<?php

namespace App\Http\Controllers\Api;

use App\Actions\Api\ResolveLinkedStudentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexStudentNotasRequest;
use App\Http\Resources\Api\NotaResource;
use App\Models\Nota;
use Illuminate\Http\JsonResponse;

class NotasController extends Controller
{
    /**
     * List grades for a student linked to the authenticated responsavel.
     */
    public function index(
        IndexStudentNotasRequest $request,
        string $id,
        ResolveLinkedStudentAction $resolveLinkedStudent,
    ): JsonResponse {
        $aluno = $resolveLinkedStudent->execute($request->user(), $id);
        $filters = $request->validated();

        $query = Nota::query()
            ->where('tenant_id', $aluno->tenant_id)
            ->where('aluno_id', $aluno->id)
            ->with([
                'disciplinaRelation:id,nome,sigla',
                'turma:id,nome,serie,turma_letra,ano_letivo',
                'professor.usuario:id,nome_completo',
            ])
            ->orderByDesc('ano_letivo')
            ->orderBy('bimestre')
            ->orderBy('disciplina');

        if (! empty($filters['turma_id'])) {
            $query->where('turma_id', $filters['turma_id']);
        }

        if (! empty($filters['disciplina_id'])) {
            $query->where('disciplina_id', $filters['disciplina_id']);
        }

        if (! empty($filters['bimestre'])) {
            $query->where('bimestre', $filters['bimestre']);
        }

        if (! empty($filters['ano_letivo'])) {
            $query->where('ano_letivo', $filters['ano_letivo']);
        }

        $notas = $query->get();

        return response()->json([
            'notas' => NotaResource::collection($notas),
        ]);
    }
}
