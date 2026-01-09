<?php

namespace App\Http\Requests\School;

use App\Models\Teacher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = auth()->user()?->tenants()->first()?->id;
        $teacherId = $this->route('teacher')?->id;

        return [
            // User fields
            'nome_completo' => ['required', 'string', 'max:255'],
            'cpf' => ['nullable', 'string', 'regex:/^[0-9]{11}$|^[0-9]{3}\.[0-9]{3}\.[0-9]{3}-[0-9]{2}$/'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'string', 'min:6'],

            // Teacher fields
            'matricula' => [
                'required',
                'string',
                'max:50',
                Rule::unique(Teacher::class, 'matricula')
                    ->where('tenant_id', $tenantId)
                    ->ignore($teacherId)
                    ->whereNull('deleted_at'),
            ],
            'disciplinas' => ['nullable', 'string'],
            'especializacao' => ['nullable', 'string', 'max:255'],
            'ativo' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nome_completo.required' => 'Informe o nome completo do professor.',
            'nome_completo.max' => 'O nome completo não pode ter mais de 255 caracteres.',
            'cpf.regex' => 'O CPF deve estar no formato 000.000.000-00 ou apenas números.',
            'email.email' => 'Informe um e-mail válido.',
            'matricula.required' => 'Informe a matrícula do professor.',
            'matricula.unique' => 'Já existe um professor com esta matrícula nesta escola.',
            'matricula.max' => 'A matrícula não pode ter mais de 50 caracteres.',
            'disciplinas.array' => 'As disciplinas devem ser uma lista.',
            'disciplinas.*.required' => 'Cada disciplina deve ser um ID válido.',
            'disciplinas.*.uuid' => 'Cada disciplina deve ser um ID válido.',
            'disciplinas.*.exists' => 'Uma ou mais disciplinas selecionadas são inválidas ou não pertencem a esta escola.',
            'especializacao.max' => 'A especialização não pode ter mais de 255 caracteres.',
            'password.min' => 'A senha deve ter no mínimo 6 caracteres.',
        ];
    }
}
