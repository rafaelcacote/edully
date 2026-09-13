<?php

namespace App\Http\Requests\School;

use App\Models\Disciplina;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Turma;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SyncLoteNotasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = auth()->user()?->tenants()->first()?->id;

        return [
            'turma_id' => [
                'required',
                'uuid',
                Rule::exists(Turma::class, 'id')
                    ->where('tenant_id', $tenantId),
            ],
            'disciplina_id' => [
                'required',
                'uuid',
                Rule::exists(Disciplina::class, 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('ativo', true),
            ],
            'professor_id' => [
                'required',
                'uuid',
                Rule::exists(Teacher::class, 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('ativo', true),
            ],
            'bimestre' => ['required', 'integer', 'min:1', 'max:4'],
            'ano_letivo' => ['required', 'integer', 'min:2000', 'max:2100'],
            'notas' => ['required', 'array', 'min:1'],
            'notas.*.aluno_id' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists(Student::class, 'id')
                    ->where('tenant_id', $tenantId),
            ],
            'notas.*.nota' => ['nullable', 'numeric', 'min:0', 'max:10', 'regex:/^\d+(\.\d{1})?$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'turma_id.required' => 'Selecione uma turma.',
            'disciplina_id.required' => 'Selecione uma disciplina.',
            'professor_id.required' => 'Selecione um professor.',
            'bimestre.required' => 'Selecione o bimestre.',
            'bimestre.min' => 'O bimestre deve ser entre 1 e 4.',
            'bimestre.max' => 'O bimestre deve ser entre 1 e 4.',
            'ano_letivo.required' => 'Informe o ano letivo.',
            'notas.required' => 'Informe as notas dos alunos.',
            'notas.*.aluno_id.required' => 'Aluno inválido na lista.',
            'notas.*.aluno_id.distinct' => 'Há alunos duplicados na lista.',
            'notas.*.nota.min' => 'A nota deve ser entre 0 e 10.',
            'notas.*.nota.max' => 'A nota deve ser entre 0 e 10.',
            'notas.*.nota.regex' => 'A nota deve ter no máximo uma casa decimal.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $tenantId = auth()->user()?->tenants()->first()?->id;
            $turmaId = $this->input('turma_id');
            $disciplinaId = $this->input('disciplina_id');
            $alunoIds = collect($this->input('notas', []))->pluck('aluno_id')->filter()->values();

            $driver = DB::connection('shared')->getDriverName();
            $matriculasTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';
            $turmaDisciplinasTable = $driver === 'sqlite' ? 'turma_disciplinas' : 'escola.turma_disciplinas';

            $disciplinaNaTurma = DB::connection('shared')
                ->table($turmaDisciplinasTable)
                ->where('tenant_id', $tenantId)
                ->where('turma_id', $turmaId)
                ->where('disciplina_id', $disciplinaId)
                ->exists();

            if (! $disciplinaNaTurma) {
                $validator->errors()->add('disciplina_id', 'A disciplina não faz parte da grade desta turma.');
            }

            $matriculados = DB::connection('shared')
                ->table($matriculasTable)
                ->where('tenant_id', $tenantId)
                ->where('turma_id', $turmaId)
                ->where('status', 'ativo')
                ->whereIn('aluno_id', $alunoIds)
                ->pluck('aluno_id')
                ->map(fn ($id) => (string) $id)
                ->all();

            foreach ($alunoIds as $index => $alunoId) {
                if (! in_array((string) $alunoId, $matriculados, true)) {
                    $validator->errors()->add(
                        "notas.{$index}.aluno_id",
                        'O aluno não está matriculado nesta turma.'
                    );
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $notas = $this->input('notas', []);

        if (! is_array($notas)) {
            return;
        }

        $normalized = collect($notas)
            ->map(function ($item) {
                if (! is_array($item)) {
                    return null;
                }

                $nota = $item['nota'] ?? null;
                if ($nota === '' || $nota === null) {
                    $nota = null;
                }

                return [
                    'aluno_id' => $item['aluno_id'] ?? null,
                    'nota' => $nota,
                ];
            })
            ->filter(fn ($item) => is_array($item) && ! empty($item['aluno_id']))
            ->values()
            ->all();

        $this->merge([
            'notas' => $normalized,
        ]);
    }
}
