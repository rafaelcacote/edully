<?php

namespace App\Http\Requests\School;

use App\Models\Turma;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GerarMensalidadesLoteRequest extends FormRequest
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
                'nullable',
                'uuid',
                Rule::exists(Turma::class, 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('ativo', true),
            ],
            'ano' => ['required', 'integer', 'min:2000', 'max:2100'],
            'mes' => ['required', 'integer', 'min:1', 'max:12'],
            'valor' => ['required', 'numeric', 'min:0.01'],
            'vencimento' => ['required', 'date'],
            'descricao' => ['nullable', 'string', 'max:5000'],
            'pix_copia_cola' => ['nullable', 'string', 'max:1000'],
            'pix_chave' => ['nullable', 'string', 'max:255'],
            'boleto' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'turma_id.exists' => 'Turma inválida para esta escola.',
            'ano.required' => 'Informe o ano de referência.',
            'mes.required' => 'Informe o mês de referência.',
            'mes.min' => 'O mês deve ser entre 1 e 12.',
            'mes.max' => 'O mês deve ser entre 1 e 12.',
            'valor.required' => 'Informe o valor da mensalidade.',
            'valor.min' => 'O valor deve ser maior que zero.',
            'vencimento.required' => 'Informe a data de vencimento.',
            'vencimento.date' => 'A data de vencimento é inválida.',
            'boleto.mimes' => 'O boleto deve ser um arquivo PDF.',
            'boleto.max' => 'O boleto não pode ter mais de 10 MB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $turmaId = $this->input('turma_id');

        $this->merge([
            'turma_id' => $turmaId === '' || $turmaId === null ? null : $turmaId,
            'pix_copia_cola' => $this->filled('pix_copia_cola') ? $this->input('pix_copia_cola') : null,
            'pix_chave' => $this->filled('pix_chave') ? $this->input('pix_chave') : null,
            'descricao' => $this->filled('descricao') ? $this->input('descricao') : null,
        ]);
    }
}
