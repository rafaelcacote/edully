<?php

namespace App\Http\Requests\School;

use App\Enums\NivelPrioridade;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'prioridade' => ['nullable', 'string', Rule::in(NivelPrioridade::values())],
            'publico_alvo' => ['nullable', 'string', 'in:todos,professores,responsaveis'],
            'anexo' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'anexo_url' => ['nullable', 'string', 'url', 'max:2048'],
            'publicado' => ['nullable', 'boolean'],
            'publicado_em' => ['nullable', 'date'],
            'expira_em' => ['nullable', 'date', 'after:publicado_em'],
        ];
    }

    public function messages(): array
    {
        return [
            'titulo.required' => 'Informe o título do comunicado.',
            'titulo.max' => 'O título não pode ter mais de 255 caracteres.',
            'conteudo.required' => 'Informe o conteúdo do comunicado.',
            'prioridade.in' => 'A prioridade deve ser: baixa, normal, alta ou urgente.',
            'publico_alvo.in' => 'O público-alvo deve ser: todos, professores ou responsaveis.',
            'anexo.file' => 'O anexo deve ser um arquivo.',
            'anexo.mimes' => 'O anexo deve ser PDF, JPG, PNG ou WEBP.',
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
            'publicado_em' => $this->publicado_em === '' ? null : $this->publicado_em,
            'expira_em' => $this->expira_em === '' ? null : $this->expira_em,
        ]);
    }
}
