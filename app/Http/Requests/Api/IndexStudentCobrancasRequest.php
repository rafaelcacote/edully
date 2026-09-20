<?php

namespace App\Http\Requests\Api;

use App\Enums\StatusCobranca;
use App\Enums\TipoCobranca;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexStudentCobrancasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'nullable',
                'string',
                Rule::in([
                    ...StatusCobranca::values(),
                    'atrasado',
                ]),
            ],
            'tipo' => ['nullable', 'string', Rule::in(TipoCobranca::values())],
            'ano' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => 'O status deve ser: pendente, pago, cancelado ou atrasado.',
            'tipo.in' => 'O tipo deve ser: mensalidade ou evento.',
            'ano.integer' => 'O ano informado é inválido.',
            'per_page.max' => 'O máximo de itens por página é 100.',
        ];
    }
}
