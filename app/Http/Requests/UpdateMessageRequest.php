<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = auth()->user()?->tenants()->first()?->id;

        return [
            'destinatario_id' => [
                'nullable',
                'uuid',
                Rule::exists(User::class, 'id')
                    ->whereHas('tenants', function ($query) use ($tenantId) {
                        $query->where('tenants.id', $tenantId);
                    }),
            ],
            'titulo' => ['required', 'string', 'max:255'],
            'conteudo' => ['required', 'string'],
            'ativo' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'destinatario_id.exists' => 'Destinatário não encontrado.',
            'titulo.required' => 'Informe o título da mensagem.',
            'titulo.max' => 'O título não pode ter mais de 255 caracteres.',
            'conteudo.required' => 'Informe o conteúdo da mensagem.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'destinatario_id' => $this->destinatario_id === '' ? null : $this->destinatario_id,
        ]);
    }
}
