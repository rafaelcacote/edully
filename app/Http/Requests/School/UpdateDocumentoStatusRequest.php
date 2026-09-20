<?php

namespace App\Http\Requests\School;

use App\Enums\StatusDocumento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateDocumentoStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(StatusDocumento::schoolUpdatableValues())],
            'motivo_recusa' => ['nullable', 'string', 'max:5000'],
            'anexo_resposta' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'descricao' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Informe o novo status.',
            'status.in' => 'Status inválido para análise.',
            'motivo_recusa.max' => 'O motivo da recusa não pode ter mais de 5000 caracteres.',
            'anexo_resposta.file' => 'O anexo de resposta deve ser um arquivo.',
            'anexo_resposta.mimes' => 'O anexo de resposta deve ser PDF, JPG ou PNG.',
            'anexo_resposta.max' => 'O anexo de resposta não pode ter mais de 10 MB.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('status') === StatusDocumento::Recusado->value
                && blank($this->input('motivo_recusa'))
            ) {
                $validator->errors()->add('motivo_recusa', 'Informe o motivo da recusa.');
            }
        });
    }
}
