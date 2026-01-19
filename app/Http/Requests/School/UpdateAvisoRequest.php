<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAvisoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:255'],
            'conteudo' => ['required', 'string'],
            'prioridade' => ['nullable', 'string', 'in:normal,alta,media'],
            'publico_alvo' => ['nullable', 'string', 'in:todos,alunos,professores,responsaveis'],
            'anexo' => ['nullable', 'file', 'mimetypes:application/pdf', 'max:10240'], // 10MB máximo para PDFs
            'anexo_url' => ['nullable', 'string', 'url', 'max:2048'],
            'publicado' => ['nullable', 'boolean'],
            'publicado_em' => ['nullable', 'date'],
            'expira_em' => ['nullable', 'date', 'after:publicado_em'],
        ];
    }

    public function messages(): array
    {
        return [
            'titulo.required' => 'Informe o título do aviso.',
            'titulo.max' => 'O título não pode ter mais de 255 caracteres.',
            'conteudo.required' => 'Informe o conteúdo do aviso.',
            'prioridade.in' => 'A prioridade deve ser: normal, alta ou media.',
            'publico_alvo.in' => 'O público-alvo deve ser: todos, alunos, professores ou responsaveis.',
            'anexo.file' => 'O anexo deve ser um arquivo.',
            'anexo.mimes' => 'O anexo deve ser um arquivo PDF.',
            'anexo.max' => 'O anexo não pode ter mais de 10MB.',
            'anexo_url.url' => 'Informe uma URL válida para o anexo.',
            'anexo_url.max' => 'A URL do anexo não pode ter mais de 2048 caracteres.',
            'publicado_em.date' => 'A data de publicação deve ser uma data válida.',
            'expira_em.date' => 'A data de expiração deve ser uma data válida.',
            'expira_em.after' => 'A data de expiração deve ser posterior à data de publicação.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'publicado' => $this->publicado ?? false,
            'prioridade' => $this->prioridade ?? 'normal',
            'publico_alvo' => $this->publico_alvo ?? 'todos',
        ]);
    }
}
