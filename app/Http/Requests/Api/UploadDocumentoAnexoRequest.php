<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UploadDocumentoAnexoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'anexo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'anexo.required' => 'Envie o arquivo do anexo.',
            'anexo.file' => 'O anexo deve ser um arquivo.',
            'anexo.mimes' => 'O anexo deve ser PDF, JPG ou PNG.',
            'anexo.max' => 'O anexo não pode ter mais de 10 MB.',
        ];
    }
}
