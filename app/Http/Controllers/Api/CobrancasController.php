<?php

namespace App\Http\Controllers\Api;

use App\Actions\Api\ResolveLinkedStudentAction;
use App\Enums\StatusCobranca;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexStudentCobrancasRequest;
use App\Http\Resources\Api\CobrancaResource;
use App\Models\Cobranca;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CobrancasController extends Controller
{
    /**
     * List cobrancas for a student linked to the authenticated responsavel.
     */
    public function index(
        IndexStudentCobrancasRequest $request,
        string $id,
        ResolveLinkedStudentAction $resolveLinkedStudent,
    ): JsonResponse {
        $aluno = $resolveLinkedStudent->execute($request->user(), $id);
        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 20);

        $query = Cobranca::query()
            ->where('tenant_id', $aluno->tenant_id)
            ->where('aluno_id', $aluno->id)
            ->orderByDesc('vencimento')
            ->orderByDesc('created_at');

        if (! empty($filters['tipo'])) {
            $query->where('tipo', $filters['tipo']);
        }

        if (! empty($filters['status'])) {
            if ($filters['status'] === 'atrasado') {
                $query->where('status', StatusCobranca::Pendente)
                    ->whereDate('vencimento', '<', now()->toDateString());
            } else {
                $query->where('status', $filters['status']);
            }
        }

        if (! empty($filters['ano'])) {
            $ano = (int) $filters['ano'];
            $query->where(function ($q) use ($ano) {
                $q->whereYear('vencimento', $ano)
                    ->orWhere('referencia', 'like', $ano.'-%');
            });
        }

        $cobrancas = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'cobrancas' => CobrancaResource::collection($cobrancas->items()),
            'meta' => [
                'current_page' => $cobrancas->currentPage(),
                'last_page' => $cobrancas->lastPage(),
                'per_page' => $cobrancas->perPage(),
                'total' => $cobrancas->total(),
            ],
        ]);
    }

    /**
     * Show a single cobranca for a linked student.
     */
    public function show(
        Request $request,
        string $id,
        string $cobrancaId,
        ResolveLinkedStudentAction $resolveLinkedStudent,
    ): JsonResponse {
        $aluno = $resolveLinkedStudent->execute($request->user(), $id);

        $cobranca = Cobranca::query()
            ->where('tenant_id', $aluno->tenant_id)
            ->where('aluno_id', $aluno->id)
            ->where('id', $cobrancaId)
            ->firstOrFail();

        return response()->json([
            'cobranca' => new CobrancaResource($cobranca),
        ]);
    }
}
