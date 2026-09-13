<?php

namespace App\Http\Requests\School;

use App\Models\Disciplina;
use App\Models\Nota;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Turma;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreNotaRequest extends FormRequest
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
            'aluno_id' => [
                'required',
                'uuid',
                Rule::exists(Student::class, 'id')
                    ->where('tenant_id', $tenantId),
            ],
            'professor_id' => [
                'required',
                'uuid',
                Rule::exists(Teacher::class, 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('ativo', true),
            ],
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
            'bimestre' => ['required', 'integer', 'min:1', 'max:4'],
            'nota' => ['required', 'numeric', 'min:0', 'max:10', 'regex:/^\d+(\.\d{1})?$/'],
            'comportamento' => ['nullable', 'string', 'max:50'],
            'observacoes' => ['nullable', 'string', 'max:65535'],
            'ano_letivo' => ['required', 'integer', 'min:2000', 'max:2100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'aluno_id.required' => 'Selecione um aluno.',
            'aluno_id.exists' => 'O aluno selecionado não foi encontrado.',
            'professor_id.required' => 'Selecione um professor.',
            'professor_id.exists' => 'O professor selecionado não foi encontrado.',
            'turma_id.required' => 'Selecione uma turma.',
            'turma_id.exists' => 'A turma selecionada não foi encontrada.',
            'disciplina_id.required' => 'Selecione uma disciplina.',
            'disciplina_id.exists' => 'A disciplina selecionada não foi encontrada.',
            'bimestre.required' => 'Selecione o bimestre.',
            'bimestre.min' => 'O bimestre deve ser entre 1 e 4.',
            'bimestre.max' => 'O bimestre deve ser entre 1 e 4.',
            'nota.required' => 'Informe a nota.',
            'nota.min' => 'A nota deve ser entre 0 e 10.',
            'nota.max' => 'A nota deve ser entre 0 e 10.',
            'nota.regex' => 'A nota deve ter no máximo uma casa decimal.',
            'ano_letivo.required' => 'Informe o ano letivo.',
            'ano_letivo.min' => 'O ano letivo deve ser maior ou igual a 2000.',
            'ano_letivo.max' => 'O ano letivo deve ser menor ou igual a 2100.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $tenantId = auth()->user()?->tenants()->first()?->id;
            $alunoId = $this->input('aluno_id');
            $turmaId = $this->input('turma_id');
            $disciplinaId = $this->input('disciplina_id');
            $bimestre = (int) $this->input('bimestre');
            $anoLetivo = (int) $this->input('ano_letivo');

            $driver = DB::connection('shared')->getDriverName();
            $matriculasTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';
            $turmaDisciplinasTable = $driver === 'sqlite' ? 'turma_disciplinas' : 'escola.turma_disciplinas';

            $matriculado = DB::connection('shared')
                ->table($matriculasTable)
                ->where('tenant_id', $tenantId)
                ->where('aluno_id', $alunoId)
                ->where('turma_id', $turmaId)
                ->where('status', 'ativo')
                ->exists();

            if (! $matriculado) {
                $validator->errors()->add('aluno_id', 'O aluno não está matriculado nesta turma.');
            }

            $disciplinaNaTurma = DB::connection('shared')
                ->table($turmaDisciplinasTable)
                ->where('tenant_id', $tenantId)
                ->where('turma_id', $turmaId)
                ->where('disciplina_id', $disciplinaId)
                ->exists();

            if (! $disciplinaNaTurma) {
                $validator->errors()->add('disciplina_id', 'A disciplina não faz parte da grade desta turma.');
            }

            $duplicada = Nota::query()
                ->where('tenant_id', $tenantId)
                ->where('aluno_id', $alunoId)
                ->where('turma_id', $turmaId)
                ->where('disciplina_id', $disciplinaId)
                ->where('bimestre', $bimestre)
                ->where('ano_letivo', $anoLetivo)
                ->exists();

            if ($duplicada) {
                $validator->errors()->add('bimestre', 'Já existe nota para este aluno nesta disciplina e bimestre.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'comportamento' => $this->comportamento === '' ? null : $this->comportamento,
        ]);
    }
}
