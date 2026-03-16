<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\AvisoResource;
use App\Models\Aviso;
use App\Models\Responsavel;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AvisosController extends Controller
{
    /**
     * Get allowed tenant IDs for the authenticated user (responsavel or teacher).
     *
     * @return array<int, string>
     */
    private function getAllowedTenantIds(Request $request): array
    {
        $user = $request->user();

        if ($user->isResponsavel()) {
            $responsaveis = Responsavel::where('usuario_id', $user->id)->get();
            $responsavelIds = $responsaveis->pluck('id')->toArray();

            if (empty($responsavelIds)) {
                return [];
            }

            $driver = DB::connection('shared')->getDriverName();
            $pivotTable = $driver === 'sqlite' ? 'aluno_responsavel' : 'escola.aluno_responsavel';

            $alunoIds = DB::connection('shared')
                ->table($pivotTable)
                ->whereIn('responsavel_id', $responsavelIds)
                ->pluck('aluno_id')
                ->unique()
                ->toArray();

            if (empty($alunoIds)) {
                return [];
            }

            return Student::whereIn('id', $alunoIds)
                ->where('ativo', true)
                ->pluck('tenant_id')
                ->unique()
                ->values()
                ->toArray();
        }

        if ($user->isTeacher()) {
            $teacher = $user->teacher()->where('ativo', true)->first();

            return $teacher ? [$teacher->tenant_id] : [];
        }

        return [];
    }

    /**
     * List published avisos for the app (students' notices).
     * Responsaveis see avisos from tenants where their students are enrolled.
     * Teachers see avisos from their tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isResponsavel() && ! $user->isTeacher()) {
            return response()->json([
                'message' => 'Acesso negado. Apenas responsáveis e professores podem acessar os avisos.',
            ], 403);
        }

        $tenantIds = $this->getAllowedTenantIds($request);

        if (empty($tenantIds)) {
            return response()->json([
                'avisos' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 15,
                    'total' => 0,
                ],
            ]);
        }

        $avisos = Aviso::query()
            ->whereIn('tenant_id', $tenantIds)
            ->where('publicado', true)
            ->where(function ($query) {
                $query->whereNull('expira_em')
                    ->orWhere('expira_em', '>=', now());
            })
            ->with('tenant:id,nome')
            ->orderBy('publicado_em', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'avisos' => AvisoResource::collection($avisos->items()),
            'meta' => [
                'current_page' => $avisos->currentPage(),
                'last_page' => $avisos->lastPage(),
                'per_page' => $avisos->perPage(),
                'total' => $avisos->total(),
            ],
        ]);
    }

    /**
     * Show a single published aviso.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        if (! $user->isResponsavel() && ! $user->isTeacher()) {
            return response()->json([
                'message' => 'Acesso negado. Apenas responsáveis e professores podem acessar os avisos.',
            ], 403);
        }

        $tenantIds = $this->getAllowedTenantIds($request);

        if (empty($tenantIds)) {
            return response()->json([
                'message' => 'Aviso não encontrado.',
            ], 404);
        }

        $aviso = Aviso::query()
            ->where('id', $id)
            ->whereIn('tenant_id', $tenantIds)
            ->where('publicado', true)
            ->where(function ($query) {
                $query->whereNull('expira_em')
                    ->orWhere('expira_em', '>=', now());
            })
            ->with('tenant:id,nome')
            ->first();

        if (! $aviso) {
            return response()->json([
                'message' => 'Aviso não encontrado.',
            ], 404);
        }

        return response()->json([
            'aviso' => new AvisoResource($aviso),
        ]);
    }
}
