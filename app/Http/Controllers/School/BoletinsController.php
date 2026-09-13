<?php

namespace App\Http\Controllers\School;

use App\Actions\School\BuildBoletimAction;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Turma;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BoletinsController extends Controller
{
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
     * Display the report card selection and preview.
     */
    public function index(Request $request, BuildBoletimAction $action): Response
    {
        $tenant = $this->getTenant();
        $filters = $request->only(['turma_id', 'aluno_id']);

        $turmas = Turma::query()
            ->where('tenant_id', $tenant->id)
            ->where('ativo', true)
            ->orderByDesc('ano_letivo')
            ->orderBy('nome')
            ->get(['id', 'nome', 'serie', 'turma_letra', 'ano_letivo']);

        $alunos = [];
        $boletim = null;

        if (! empty($filters['turma_id'])) {
            $turma = Turma::query()
                ->where('tenant_id', $tenant->id)
                ->where('id', $filters['turma_id'])
                ->first();

            if ($turma) {
                $driver = DB::connection('shared')->getDriverName();
                $matriculasTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';
                $alunosTable = $driver === 'sqlite' ? 'alunos' : 'escola.alunos';

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
                    ->map(fn ($aluno) => [
                        'id' => $aluno->id,
                        'nome' => $aluno->nome,
                    ])
                    ->values()
                    ->all();

                if (! empty($filters['aluno_id'])) {
                    $aluno = Student::query()
                        ->where('tenant_id', $tenant->id)
                        ->where('id', $filters['aluno_id'])
                        ->first();

                    if ($aluno) {
                        $boletim = $action->execute($tenant, $aluno, $turma);
                    }
                }
            }
        }

        return Inertia::render('school/boletins/Index', [
            'turmas' => $turmas,
            'alunos' => $alunos,
            'filters' => [
                'turma_id' => $filters['turma_id'] ?? null,
                'aluno_id' => $filters['aluno_id'] ?? null,
            ],
            'boletim' => $boletim,
        ]);
    }
}
