<?php

namespace App\Actions\School;

use App\Models\Tenant;
use App\Models\Turma;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncTurmaDisciplinasAction
{
    /**
     * @param  list<array{disciplina_id: string, professor_id: ?string}>  $disciplinas
     */
    public function execute(Turma $turma, Tenant $tenant, array $disciplinas): void
    {
        $pivotTable = $turma->getConnection()->getDriverName() === 'sqlite'
            ? 'turma_disciplinas'
            : 'escola.turma_disciplinas';

        DB::connection('shared')->transaction(function () use ($pivotTable, $turma, $tenant, $disciplinas) {
            DB::connection('shared')
                ->table($pivotTable)
                ->where('tenant_id', $tenant->id)
                ->where('turma_id', $turma->id)
                ->delete();

            if ($disciplinas === []) {
                return;
            }

            $now = now();
            $rows = collect($disciplinas)
                ->map(fn (array $item) => [
                    'id' => Str::uuid()->toString(),
                    'tenant_id' => $tenant->id,
                    'turma_id' => $turma->id,
                    'disciplina_id' => $item['disciplina_id'],
                    'professor_id' => $item['professor_id'] ?? null,
                    'created_at' => $now,
                ])
                ->values()
                ->all();

            DB::connection('shared')
                ->table($pivotTable)
                ->insert($rows);
        });
    }
}
