<?php

namespace App\Http\Requests\School;

use App\Models\Turma;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = auth()->user()?->tenants()->first()?->id;

        return [
            'nome' => ['required', 'string', 'max:255'],
            'nome_social' => ['nullable', 'string', 'max:255'],
            'foto' => ['nullable', 'image', 'max:2048', 'mimes:jpeg,jpg,png,gif,webp'],
            'turma_id' => [
                'required',
                'string',
                Rule::exists(Turma::class, 'id')->where('tenant_id', $tenantId)->where('ativo', true),
            ],
            'data_nascimento' => ['nullable', 'date'],
            'ativo' => ['nullable', 'boolean'],
            'informacoes_medicas' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'nome.required' => 'Informe o nome do aluno.',
            'turma_id.required' => 'Selecione uma turma.',
            'turma_id.exists' => 'A turma selecionada não é válida.',
            'foto.image' => 'O arquivo deve ser uma imagem.',
            'foto.max' => 'A imagem não pode ter mais de 2MB.',
            'foto.mimes' => 'A imagem deve ser do tipo: jpeg, jpg, png, gif ou webp.',
        ];
    }
}
