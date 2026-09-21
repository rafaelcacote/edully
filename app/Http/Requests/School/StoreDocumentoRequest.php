<?php

namespace App\Http\Requests\School;

use App\Enums\CategoriaDeclaracao;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'aluno_id' => ['required', 'uuid'],
            'titulo' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string', 'max:5000'],
            'categoria_declaracao' => ['nullable', 'string', Rule::in(CategoriaDeclaracao::values())],
            'anexo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'aluno_id.required' => 'Selecione o aluno.',
            'aluno_id.uuid' => 'O aluno informado é inválido.',
            'titulo.required' => 'Informe o título do documento.',
            'titulo.max' => 'O título não pode ter mais de 255 caracteres.',
            'categoria_declaracao.in' => 'Categoria de declaração inválida.',
            'anexo.required' => 'Anexe o arquivo do documento (PDF, JPG ou PNG).',
            'anexo.file' => 'O anexo deve ser um arquivo.',
            'anexo.mimes' => 'O anexo deve ser PDF, JPG ou PNG.',
            'anexo.max' => 'O anexo não pode ter mais de 10 MB.',
        ];
    }
}
