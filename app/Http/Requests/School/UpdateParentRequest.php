<?php

namespace App\Http\Requests\School;

use App\Enums\Parentesco;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateParentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('cpf')) {
            $this->merge([
                'cpf' => preg_replace('/[^0-9]/', '', (string) $this->input('cpf')),
            ]);
        }

        if ($this->filled('email')) {
            $this->merge([
                'email' => strtolower(trim((string) $this->input('email'))),
            ]);
        }
    }

    public function rules(): array
    {
        $allowedParentesco = Parentesco::values();
        $parent = $this->route('parent');
        $currentParentesco = $parent?->parentesco;
        $usuarioId = $parent?->usuario_id;

        if (is_string($currentParentesco) && $currentParentesco !== '' && ! in_array($currentParentesco, $allowedParentesco, true)) {
            $allowedParentesco[] = $currentParentesco;
        }

        return [
            // User fields
            'nome_completo' => ['required', 'string', 'max:255'],
            'cpf' => [
                'nullable',
                'string',
                'regex:/^[0-9]{11}$/',
                Rule::unique(User::class, 'cpf')->ignore($usuarioId),
            ],
            'email' => [
                'nullable',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class, 'email')->ignore($usuarioId),
            ],
            'telefone' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'string', 'min:6'],

            // Parent fields
            'parentesco' => ['nullable', 'string', Rule::in($allowedParentesco)],
            'profissao' => ['nullable', 'string', 'max:100'],
            'data_nascimento' => ['nullable', 'date'],
            'observacoes' => ['nullable', 'string', 'max:5000'],
            'ativo' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nome_completo.required' => 'Informe o nome completo do responsável.',
            'nome_completo.max' => 'O nome completo não pode ter mais de 255 caracteres.',
            'cpf.regex' => 'O CPF deve conter 11 dígitos.',
            'cpf.unique' => 'Este CPF já está cadastrado no sistema e não pode ser utilizado novamente.',
            'email.email' => 'Informe um e-mail válido.',
            'email.unique' => 'Este e-mail já está cadastrado no sistema e não pode ser utilizado novamente.',
            'parentesco.in' => 'Selecione um parentesco válido.',
            'profissao.max' => 'A profissão não pode ter mais de 100 caracteres.',
            'data_nascimento.date' => 'Informe uma data de nascimento válida.',
            'observacoes.max' => 'As observações não podem ter mais de 5000 caracteres.',
            'password.min' => 'A senha deve ter no mínimo 6 caracteres.',
        ];
    }
}
