<?php

namespace App\Http\Controllers\Api;

use App\Actions\Api\ResolveLinkedStudentAction;
use App\Actions\School\BuildBoletimAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ShowStudentBoletimRequest;
use App\Models\Tenant;
use App\Models\Turma;
use Illuminate\Http\JsonResponse;

class BoletinsController extends Controller
{
    /**
     * Return the report card for a student linked to the authenticated responsavel.
     */
    public function show(
        ShowStudentBoletimRequest $request,
        string $id,
        ResolveLinkedStudentAction $resolveLinkedStudent,
        BuildBoletimAction $buildBoletim,
    ): JsonResponse {
        $aluno = $resolveLinkedStudent->execute($request->user(), $id);

        $turma = Turma::query()
            ->where('id', $request->validated('turma_id'))
            ->where('tenant_id', $aluno->tenant_id)
            ->where('ativo', true)
            ->first();

        if (! $turma) {
            return response()->json([
                'message' => 'Turma não encontrada.',
            ], 404);
        }

        $tenant = Tenant::query()->findOrFail($aluno->tenant_id);
        $boletim = $buildBoletim->execute($tenant, $aluno, $turma);

        return response()->json([
            'boletim' => $boletim,
        ]);
    }
}
