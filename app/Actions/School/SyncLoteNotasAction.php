<?php

namespace App\Actions\School;

use App\Models\Disciplina;
use App\Models\Nota;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class SyncLoteNotasAction
{
    /**
     * @param  array{
     *     turma_id: string,
     *     disciplina_id: string,
     *     professor_id: string,
     *     bimestre: int,
     *     ano_letivo: int,
     *     notas: list<array{aluno_id: string, nota: float|int|string|null}>
     * }  $payload
     * @return array{saved: int, cleared: int}
     */
    public function execute(Tenant $tenant, array $payload): array
    {
        $disciplina = Disciplina::query()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($payload['disciplina_id']);

        $saved = 0;
        $cleared = 0;

        DB::connection('shared')->transaction(function () use ($tenant, $payload, $disciplina, &$saved, &$cleared) {
            foreach ($payload['notas'] as $item) {
                $existing = Nota::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('aluno_id', $item['aluno_id'])
                    ->where('turma_id', $payload['turma_id'])
                    ->where('disciplina_id', $payload['disciplina_id'])
                    ->where('bimestre', $payload['bimestre'])
                    ->where('ano_letivo', $payload['ano_letivo'])
                    ->first();

                if ($item['nota'] === null || $item['nota'] === '') {
                    if ($existing) {
                        $existing->delete();
                        $cleared++;
                    }

                    continue;
                }

                $attributes = [
                    'professor_id' => $payload['professor_id'],
                    'disciplina' => $disciplina->nome,
                    'nota' => $item['nota'],
                ];

                if ($existing) {
                    $existing->update($attributes);
                } else {
                    Nota::create([
                        ...$attributes,
                        'tenant_id' => $tenant->id,
                        'aluno_id' => $item['aluno_id'],
                        'turma_id' => $payload['turma_id'],
                        'disciplina_id' => $payload['disciplina_id'],
                        'bimestre' => $payload['bimestre'],
                        'ano_letivo' => $payload['ano_letivo'],
                    ]);
                }

                $saved++;
            }
        });

        return [
            'saved' => $saved,
            'cleared' => $cleared,
        ];
    }
}
