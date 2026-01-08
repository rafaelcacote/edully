<?php

namespace App\Actions\School;

use App\Models\Student;
use App\Models\Tenant;
use App\Models\Turma;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReenrollStudentAction
{
    /**
     * Rematricular um aluno em uma nova turma de um novo ano letivo.
     */
    public function execute(Student $student, Turma $novaTurma, Tenant $tenant): void
    {
        $driver = DB::connection('shared')->getDriverName();
        $pivotTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';

        DB::connection('shared')->transaction(function () use ($student, $novaTurma, $tenant, $pivotTable) {
            // Verificar se a turma pertence ao tenant
            if ($novaTurma->tenant_id !== $tenant->id) {
                throw new \Exception('Turma não pertence ao tenant');
            }

            // Verificar se o aluno pertence ao tenant
            if ($student->tenant_id !== $tenant->id) {
                throw new \Exception('Aluno não pertence ao tenant');
            }

            // Desativar todas as matrículas ativas do aluno
            DB::connection('shared')
                ->table($pivotTable)
                ->where('tenant_id', $tenant->id)
                ->where('aluno_id', $student->id)
                ->where('status', 'ativo')
                ->update(['status' => 'inativo']);

            // Verificar se já existe matrícula para esta turma
            $matriculaExistente = DB::connection('shared')
                ->table($pivotTable)
                ->where('tenant_id', $tenant->id)
                ->where('aluno_id', $student->id)
                ->where('turma_id', $novaTurma->id)
                ->first();

            if ($matriculaExistente) {
                // Reativar matrícula existente
                DB::connection('shared')
                    ->table($pivotTable)
                    ->where('id', $matriculaExistente->id)
                    ->update([
                        'status' => 'ativo',
                        'data_matricula' => now()->toDateString(),
                        'updated_at' => now(),
                    ]);
            } else {
                // Criar nova matrícula
                $matriculaId = Str::uuid();

                DB::connection('shared')->table($pivotTable)->insert([
                    'id' => $matriculaId,
                    'tenant_id' => $tenant->id,
                    'aluno_id' => $student->id,
                    'turma_id' => $novaTurma->id,
                    'data_matricula' => now()->toDateString(),
                    'status' => 'ativo',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }
}
