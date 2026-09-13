<?php

namespace App\Actions\Api;

use App\Models\Responsavel;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ResolveLinkedStudentAction
{
    /**
     * Resolve an active student linked to the authenticated responsavel.
     *
     * @throws HttpException
     */
    public function execute(User $user, string $studentId): Student
    {
        if (! $user->isResponsavel()) {
            abort(403, 'Acesso negado. Apenas responsáveis podem acessar esta funcionalidade.');
        }

        $responsavelIds = Responsavel::query()
            ->where('usuario_id', $user->id)
            ->pluck('id')
            ->all();

        if ($responsavelIds === []) {
            abort(404, 'Aluno não encontrado ou você não tem permissão para acessar este aluno.');
        }

        $driver = DB::connection('shared')->getDriverName();
        $pivotTable = $driver === 'sqlite' ? 'aluno_responsavel' : 'escola.aluno_responsavel';

        $isLinked = DB::connection('shared')
            ->table($pivotTable)
            ->whereIn('responsavel_id', $responsavelIds)
            ->where('aluno_id', $studentId)
            ->exists();

        if (! $isLinked) {
            abort(404, 'Aluno não encontrado ou você não tem permissão para acessar este aluno.');
        }

        return Student::query()
            ->where('id', $studentId)
            ->where('ativo', true)
            ->firstOrFail();
    }
}
