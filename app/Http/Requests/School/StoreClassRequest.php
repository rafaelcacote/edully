<?php

namespace App\Http\Requests\School;

use App\Models\Teacher;
use App\Models\Turma;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClassRequest extends FormRequest
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
                Rule::unique(Turma::class, 'nome')
                    ->where('tenant_id', $tenantId)
                    ->where('ano_letivo', $this->input('ano_letivo'))
                    ->whereNull('deleted_at'),
            ],
            'serie' => ['nullable', 'string', 'max:50'],
            'turma_letra' => ['nullable', 'string', 'max:10'],
            'capacidade' => ['nullable', 'integer', 'min:1'],
            'ano_letivo' => ['required', 'integer', 'min:2000', 'max:2100'],
            'professor_ids' => ['nullable', 'array'],
            'professor_ids.*' => ['uuid', Rule::exists(Teacher::class, 'id')],
            'ativo' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nome.required' => 'Informe o nome da turma.',
            'nome.unique' => 'Já existe uma turma com este nome e ano letivo nesta escola.',
            'ano_letivo.required' => 'Informe o ano letivo.',
            'ano_letivo.integer' => 'O ano letivo deve ser um número.',
            'ano_letivo.min' => 'O ano letivo deve ser maior ou igual a 2000.',
            'ano_letivo.max' => 'O ano letivo deve ser menor ou igual a 2100.',
            'capacidade.min' => 'A capacidade deve ser maior que zero.',
            'professor_ids.array' => 'Os professores devem ser enviados como uma lista.',
            'professor_ids.*.exists' => 'Um ou mais professores não foram encontrados.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'professor_ids' => $this->professor_ids ?? [],
            'turma_letra' => $this->turma_letra === '' ? null : $this->turma_letra,
        ]);
    }
}
