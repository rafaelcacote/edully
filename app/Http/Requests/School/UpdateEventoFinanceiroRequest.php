<?php

namespace App\Http\Requests\School;

use App\Enums\PublicoEventoFinanceiro;
use App\Models\Student;
use App\Models\Turma;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEventoFinanceiroRequest extends FormRequest
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
            'titulo' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string', 'max:5000'],
            'valor' => ['required', 'numeric', 'min:0.01'],
            'vencimento' => ['required', 'date'],
            'publico' => ['required', 'string', Rule::in(PublicoEventoFinanceiro::values())],
            'turma_id' => [
                'nullable',
                'uuid',
                Rule::exists(Turma::class, 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('ativo', true),
            ],
            'aluno_ids' => ['nullable', 'array'],
            'aluno_ids.*' => [
                'uuid',
                'distinct',
                Rule::exists(Student::class, 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('ativo', true),
            ],
            'pix_copia_cola' => ['nullable', 'string', 'max:1000'],
            'pix_chave' => ['nullable', 'string', 'max:255'],
            'boleto' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'remover_boleto' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'titulo.required' => 'Informe o título do evento.',
            'valor.required' => 'Informe o valor.',
            'valor.min' => 'O valor deve ser maior que zero.',
            'vencimento.required' => 'Informe a data de vencimento.',
            'publico.required' => 'Selecione o público do evento.',
            'publico.in' => 'Público inválido.',
            'turma_id.exists' => 'Turma inválida para esta escola.',
            'aluno_ids.*.exists' => 'Um ou mais alunos são inválidos.',
            'boleto.mimes' => 'O boleto deve ser um arquivo PDF.',
            'boleto.max' => 'O boleto não pode ter mais de 10 MB.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $publico = $this->input('publico');

            if ($publico === PublicoEventoFinanceiro::Turma->value && ! $this->filled('turma_id')) {
                $validator->errors()->add('turma_id', 'Selecione a turma para este público.');
            }

            if ($publico === PublicoEventoFinanceiro::Alunos->value) {
                $alunoIds = $this->input('aluno_ids', []);
                if (! is_array($alunoIds) || count($alunoIds) === 0) {
                    $validator->errors()->add('aluno_ids', 'Selecione ao menos um aluno.');
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $alunoIds = $this->input('aluno_ids', []);
        if (is_string($alunoIds)) {
            $decoded = json_decode($alunoIds, true);
            $alunoIds = is_array($decoded) ? $decoded : [];
        }

        $this->merge([
            'descricao' => $this->filled('descricao') ? $this->input('descricao') : null,
            'turma_id' => $this->filled('turma_id') ? $this->input('turma_id') : null,
            'pix_copia_cola' => $this->filled('pix_copia_cola') ? $this->input('pix_copia_cola') : null,
            'pix_chave' => $this->filled('pix_chave') ? $this->input('pix_chave') : null,
            'remover_boleto' => filter_var($this->input('remover_boleto'), FILTER_VALIDATE_BOOLEAN),
            'aluno_ids' => is_array($alunoIds) ? array_values(array_filter($alunoIds)) : [],
        ]);
    }
}
