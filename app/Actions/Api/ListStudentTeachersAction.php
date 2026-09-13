<?php

namespace App\Actions\Api;

use App\Models\Disciplina;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Turma;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ListStudentTeachersAction
{
    /**
     * List active teachers linked to the student's active classes.
     *
     * Sources: professor_turma and turma_disciplinas.professor_id.
     * Also attaches disciplinas taught to the student (prefer turma_disciplinas).
     *
     * @return Collection<int, Teacher>
     */
    public function execute(Student $aluno): Collection
    {
        $turmasTable = (new Turma)->getTable();

        $turmas = $aluno->turmas()
            ->where("{$turmasTable}.ativo", true)
            ->whereNull("{$turmasTable}.deleted_at")
            ->get([
                "{$turmasTable}.id",
                "{$turmasTable}.nome",
                "{$turmasTable}.serie",
                "{$turmasTable}.turma_letra",
                "{$turmasTable}.ano_letivo",
                "{$turmasTable}.tenant_id",
            ]);

        if ($turmas->isEmpty()) {
            return new Collection;
        }

        $turmaIds = $turmas->pluck('id');
        $turmasById = $turmas->keyBy('id');

        $driver = DB::connection('shared')->getDriverName();
        $professorTurmaTable = $driver === 'sqlite' ? 'professor_turma' : 'escola.professor_turma';
        $turmaDisciplinasTable = $driver === 'sqlite' ? 'turma_disciplinas' : 'escola.turma_disciplinas';

        $linksFromProfessorTurma = DB::connection('shared')
            ->table($professorTurmaTable)
            ->where('tenant_id', $aluno->tenant_id)
            ->whereIn('turma_id', $turmaIds)
            ->get(['professor_id', 'turma_id']);

        $linksFromTurmaDisciplinas = DB::connection('shared')
            ->table($turmaDisciplinasTable)
            ->where('tenant_id', $aluno->tenant_id)
            ->whereIn('turma_id', $turmaIds)
            ->whereNotNull('professor_id')
            ->get(['professor_id', 'turma_id', 'disciplina_id']);

        $turmaIdsByTeacher = [];
        $disciplinaIdsByTeacher = [];

        foreach ($linksFromProfessorTurma as $link) {
            $turmaIdsByTeacher[$link->professor_id][$link->turma_id] = true;
        }

        foreach ($linksFromTurmaDisciplinas as $link) {
            $turmaIdsByTeacher[$link->professor_id][$link->turma_id] = true;
            if ($link->disciplina_id) {
                $disciplinaIdsByTeacher[$link->professor_id][$link->disciplina_id] = true;
            }
        }

        $teacherIds = array_keys($turmaIdsByTeacher);

        if ($teacherIds === []) {
            return new Collection;
        }

        $teachers = Teacher::query()
            ->where('tenant_id', $aluno->tenant_id)
            ->whereIn('id', $teacherIds)
            ->where('ativo', true)
            ->with([
                'usuario:id,nome_completo,avatar_url',
                'disciplinas:id,nome,sigla',
            ])
            ->get();

        $allDisciplinaIds = collect($disciplinaIdsByTeacher)
            ->flatMap(fn (array $ids) => array_keys($ids))
            ->unique()
            ->values()
            ->all();

        $disciplinasById = $allDisciplinaIds === []
            ? collect()
            : Disciplina::query()
                ->where('tenant_id', $aluno->tenant_id)
                ->whereIn('id', $allDisciplinaIds)
                ->where('ativo', true)
                ->whereNull('deleted_at')
                ->get(['id', 'nome', 'sigla'])
                ->keyBy('id');

        foreach ($teachers as $teacher) {
            $relatedTurmas = collect(array_keys($turmaIdsByTeacher[$teacher->id] ?? []))
                ->map(fn (string $turmaId) => $turmasById->get($turmaId))
                ->filter()
                ->values();

            $teacher->setRelation('turmas', new Collection($relatedTurmas->all()));

            $fromTurma = collect(array_keys($disciplinaIdsByTeacher[$teacher->id] ?? []))
                ->map(fn (string $disciplinaId) => $disciplinasById->get($disciplinaId))
                ->filter()
                ->values();

            // Prefer disciplinas da turma do aluno; se não houver, usa as do cadastro do professor.
            $disciplinas = $fromTurma->isNotEmpty()
                ? $fromTurma
                : $teacher->disciplinas->map(fn (Disciplina $disciplina) => (object) [
                    'id' => $disciplina->id,
                    'nome' => $disciplina->nome,
                    'sigla' => $disciplina->sigla,
                ])->values();

            $teacher->setRelation(
                'disciplinas_aluno',
                new Collection($disciplinas->unique('id')->values()->all())
            );
        }

        return $teachers
            ->sortBy(fn (Teacher $teacher) => mb_strtolower($teacher->usuario?->nome_completo ?? ''))
            ->values();
    }
}
