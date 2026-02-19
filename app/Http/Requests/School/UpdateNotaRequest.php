<?php

namespace App\Http\Requests\School;

use App\Models\Disciplina;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Turma;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = auth()->user()?->tenants()->first()?->id;
        $notaId = $this->route('nota')?->id;

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
                    ->where('tenant_id', $tenantId),
            ],
            'turma_id' => [
                'nullable',
                'uuid',
                Rule::exists(Turma::class, 'id')
                    ->where('tenant_id', $tenantId),
            ],
            'disciplina' => ['required', 'string', 'max:100'],
            'disciplina_id' => [
                'nullable',
                'uuid',
                Rule::exists(Disciplina::class, 'id')
                    ->where('tenant_id', $tenantId),
            ],
            'trimestre' => ['required', 'integer', 'min:1', 'max:3'],
            'nota' => ['required', 'numeric', 'min:0', 'max:10', 'regex:/^\d+(\.\d{1})?$/'],
            'frequencia' => ['nullable', 'integer', 'min:0', 'max:100'],
            'comportamento' => ['nullable', 'string'],
            'observacoes' => ['nullable', 'string', 'max:65535'],
            'ano_letivo' => ['required', 'integer', 'min:2000', 'max:2100'],
        ];
    }

    public function messages(): array
    {
        return [
            'aluno_id.required' => 'Selecione um aluno.',
            'aluno_id.exists' => 'O aluno selecionado não foi encontrado.',
            'professor_id.required' => 'Selecione um professor.',
            'professor_id.exists' => 'O professor selecionado não foi encontrado.',
            'turma_id.exists' => 'A turma selecionada não foi encontrada.',
            'disciplina.required' => 'Informe a disciplina.',
            'disciplina_id.exists' => 'A disciplina selecionada não foi encontrada.',
            'trimestre.required' => 'Selecione o trimestre.',
            'trimestre.min' => 'O trimestre deve ser entre 1 e 3.',
            'trimestre.max' => 'O trimestre deve ser entre 1 e 3.',
            'nota.required' => 'Informe a nota.',
            'nota.min' => 'A nota deve ser entre 0 e 10.',
            'nota.max' => 'A nota deve ser entre 0 e 10.',
            'nota.regex' => 'A nota deve ter no máximo uma casa decimal.',
            'frequencia.min' => 'A frequência deve ser entre 0 e 100.',
            'frequencia.max' => 'A frequência deve ser entre 0 e 100.',
            'ano_letivo.required' => 'Informe o ano letivo.',
            'ano_letivo.min' => 'O ano letivo deve ser maior ou igual a 2000.',
            'ano_letivo.max' => 'O ano letivo deve ser menor ou igual a 2100.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'frequencia' => $this->frequencia ?? 0,
            'turma_id' => $this->turma_id === '' ? null : $this->turma_id,
            'disciplina_id' => $this->disciplina_id === '' ? null : $this->disciplina_id,
        ]);
    }
}
