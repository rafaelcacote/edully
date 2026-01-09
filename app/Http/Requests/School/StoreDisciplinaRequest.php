<?php

namespace App\Http\Requests\School;

use App\Models\Disciplina;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDisciplinaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = auth()->user()?->tenants()->first()?->id;

        return [
            'nome' => [
                'required',
                'string',
                'max:100',
                Rule::unique(Disciplina::class, 'nome')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
            ],
            'sigla' => ['nullable', 'string', 'max:20'],
            'descricao' => ['nullable', 'string'],
            'carga_horaria_semanal' => ['nullable', 'integer', 'min:1'],
            'ativo' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nome.required' => 'Informe o nome da disciplina.',
            'nome.unique' => 'Já existe uma disciplina com este nome nesta escola.',
            'nome.max' => 'O nome da disciplina não pode ter mais de 100 caracteres.',
            'sigla.max' => 'A sigla não pode ter mais de 20 caracteres.',
            'carga_horaria_semanal.min' => 'A carga horária semanal deve ser maior que zero.',
        ];
    }
}
