<?php

namespace App\Actions\School;

use App\Models\Student;
use App\Models\Tenant;
use App\Models\Turma;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CreateStudentAction
{
    public function execute(array $validated, Tenant $tenant): Student
    {
        $driver = DB::connection('shared')->getDriverName();
        $pivotTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';

        // Processar upload da foto
        $fotoUrl = null;
        if (isset($validated['foto']) && $validated['foto'] instanceof UploadedFile) {
            $fotoPath = $validated['foto']->store('students/photos', 'public');
            $fotoUrl = asset('storage/'.$fotoPath);
        }

        $student = Student::create([
            'tenant_id' => $tenant->id,
            'nome' => $validated['nome'],
            'nome_social' => $validated['nome_social'] ?? null,
            'foto_url' => $fotoUrl,
            'data_nascimento' => $validated['data_nascimento'] ?? null,
            'informacoes_medicas' => $validated['informacoes_medicas'] ?? null,
            'ativo' => $validated['ativo'] ?? true,
        ]);

        // Criar vínculo com a turma na tabela pivot
        if (! empty($validated['turma_id'])) {
            $turma = Turma::findOrFail($validated['turma_id']);

            if ($turma->tenant_id !== $tenant->id) {
                throw new \Exception('Turma não pertence ao tenant');
            }

            $matriculaId = \Illuminate\Support\Str::uuid();

            // A matrícula é o próprio ID da tabela matriculas_turma
            DB::connection('shared')->table($pivotTable)->insert([
                'id' => $matriculaId,
                'tenant_id' => $tenant->id,
                'aluno_id' => $student->id,
                'turma_id' => $turma->id,
                'data_matricula' => now()->toDateString(),
                'status' => 'ativo', // Usando 'status' pois a tabela já existe com esse campo
                'created_at' => now(),
            ]);
        }

        return $student;
    }
}
