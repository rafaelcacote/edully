<?php

namespace App\Actions\School;

use App\Models\Student;
use App\Models\Tenant;
use App\Models\Turma;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BuildBoletimAction
{
    /**
     * @return array{
     *     aluno: array{id: string, nome: string},
     *     turma: array{id: string, nome: string, serie: ?string, turma_letra: ?string, ano_letivo: int|string|null},
     *     disciplinas: list<array{
     *         id: string,
     *         nome: string,
     *         sigla: ?string,
     *         bimestres: array{1: ?float, 2: ?float, 3: ?float, 4: ?float},
     *         media: ?float
     *     }>,
     *     media_geral: ?float
     * }
     */
    public function execute(Tenant $tenant, Student $aluno, Turma $turma): array
    {
        if ($aluno->tenant_id !== $tenant->id || $turma->tenant_id !== $tenant->id) {
            abort(404);
        }

        $driver = DB::connection('shared')->getDriverName();
        $matriculasTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';
        $turmaDisciplinasTable = $driver === 'sqlite' ? 'turma_disciplinas' : 'escola.turma_disciplinas';
        $disciplinasTable = $driver === 'sqlite' ? 'disciplinas' : 'escola.disciplinas';
        $notasTable = $driver === 'sqlite' ? 'notas' : 'escola.notas';

        $matriculado = DB::connection('shared')
            ->table($matriculasTable)
            ->where('tenant_id', $tenant->id)
            ->where('aluno_id', $aluno->id)
            ->where('turma_id', $turma->id)
            ->where('status', 'ativo')
            ->exists();

        if (! $matriculado) {
            abort(404, 'Aluno não matriculado nesta turma');
        }

        $disciplinas = DB::connection('shared')
            ->table($turmaDisciplinasTable.' as td')
            ->join($disciplinasTable.' as d', 'd.id', '=', 'td.disciplina_id')
            ->where('td.tenant_id', $tenant->id)
            ->where('td.turma_id', $turma->id)
            ->where('d.ativo', true)
            ->whereNull('d.deleted_at')
            ->orderBy('d.nome')
            ->get([
                'd.id',
                'd.nome',
                'd.sigla',
            ]);

        $notas = DB::connection('shared')
            ->table($notasTable)
            ->where('tenant_id', $tenant->id)
            ->where('aluno_id', $aluno->id)
            ->where('turma_id', $turma->id)
            ->where('ano_letivo', $turma->ano_letivo)
            ->whereNull('deleted_at')
            ->get(['disciplina_id', 'bimestre', 'nota'])
            ->groupBy('disciplina_id');

        $linhas = $disciplinas->map(function ($disciplina) use ($notas) {
            /** @var Collection<int, object> $notasDisciplina */
            $notasDisciplina = $notas->get($disciplina->id, collect());

            $bimestres = [
                1 => null,
                2 => null,
                3 => null,
                4 => null,
            ];

            foreach ($notasDisciplina as $nota) {
                $bimestre = (int) $nota->bimestre;
                if ($bimestre >= 1 && $bimestre <= 4) {
                    $bimestres[$bimestre] = round((float) $nota->nota, 1);
                }
            }

            $lancadas = collect($bimestres)->filter(fn ($valor) => $valor !== null);
            $media = $lancadas->isEmpty()
                ? null
                : round((float) $lancadas->avg(), 1);

            return [
                'id' => $disciplina->id,
                'nome' => $disciplina->nome,
                'sigla' => $disciplina->sigla,
                'bimestres' => $bimestres,
                'media' => $media,
            ];
        })->values()->all();

        $mediasDisciplinas = collect($linhas)
            ->pluck('media')
            ->filter(fn ($valor) => $valor !== null);

        $mediaGeral = $mediasDisciplinas->isEmpty()
            ? null
            : round((float) $mediasDisciplinas->avg(), 1);

        return [
            'aluno' => [
                'id' => $aluno->id,
                'nome' => $aluno->nome,
            ],
            'turma' => [
                'id' => $turma->id,
                'nome' => $turma->nome,
                'serie' => $turma->serie,
                'turma_letra' => $turma->turma_letra,
                'ano_letivo' => $turma->ano_letivo,
            ],
            'disciplinas' => $linhas,
            'media_geral' => $mediaGeral,
        ];
    }
}
