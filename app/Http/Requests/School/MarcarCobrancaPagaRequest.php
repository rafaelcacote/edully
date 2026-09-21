<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;

class MarcarCobrancaPagaRequest extends FormRequest
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
            'pago_observacao' => ['nullable', 'string', 'max:2000'],
            'pago_em' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pago_em.date' => 'A data de pagamento é inválida.',
            'pago_observacao.max' => 'A observação não pode ter mais de 2000 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'pago_observacao' => $this->filled('pago_observacao') ? $this->input('pago_observacao') : null,
        ]);
    }
}
