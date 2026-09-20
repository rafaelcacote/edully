<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCobrancaRequest extends FormRequest
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
        return [
            'titulo' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string', 'max:5000'],
            'valor' => ['required', 'numeric', 'min:0.01'],
            'vencimento' => ['required', 'date'],
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
            'titulo.required' => 'Informe o título da cobrança.',
            'valor.required' => 'Informe o valor.',
            'valor.min' => 'O valor deve ser maior que zero.',
            'vencimento.required' => 'Informe a data de vencimento.',
            'vencimento.date' => 'A data de vencimento é inválida.',
            'boleto.mimes' => 'O boleto deve ser um arquivo PDF.',
            'boleto.max' => 'O boleto não pode ter mais de 10 MB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'descricao' => $this->filled('descricao') ? $this->input('descricao') : null,
            'pix_copia_cola' => $this->filled('pix_copia_cola') ? $this->input('pix_copia_cola') : null,
            'pix_chave' => $this->filled('pix_chave') ? $this->input('pix_chave') : null,
            'remover_boleto' => filter_var($this->input('remover_boleto'), FILTER_VALIDATE_BOOLEAN),
        ]);
    }
}
