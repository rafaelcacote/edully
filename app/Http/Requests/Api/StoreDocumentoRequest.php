<?php

namespace App\Http\Requests\Api;

use App\Enums\CategoriaDeclaracao;
use App\Enums\TipoDocumento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDocumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tipo = $this->input('tipo');

        return [
            'aluno_id' => ['required', 'uuid'],
            'tipo' => ['required', 'string', Rule::in(TipoDocumento::mobileCreatableValues())],
            'titulo' => ['nullable', 'string', 'max:255'],
            'descricao' => ['nullable', 'string', 'max:5000'],
            'data_inicio' => [
                Rule::requiredIf($tipo === TipoDocumento::Atestado->value),
                'nullable',
                'date',
                'date_format:Y-m-d',
            ],
            'data_fim' => [
                Rule::requiredIf($tipo === TipoDocumento::Atestado->value),
                'nullable',
                'date',
                'date_format:Y-m-d',
                'after_or_equal:data_inicio',
            ],
            'categoria_declaracao' => [
                Rule::requiredIf($tipo === TipoDocumento::PedidoDeclaracao->value),
                'nullable',
                'string',
                Rule::in(CategoriaDeclaracao::values()),
            ],
            'anexo' => [
                Rule::requiredIf($tipo === TipoDocumento::Atestado->value),
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:10240',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'aluno_id.required' => 'Informe o aluno.',
            'aluno_id.uuid' => 'O aluno informado é inválido.',
            'tipo.required' => 'Informe o tipo do documento.',
            'tipo.in' => 'O tipo deve ser atestado ou pedido_declaracao.',
            'data_inicio.required' => 'Informe a data de início da falta.',
            'data_inicio.date_format' => 'A data de início deve estar no formato YYYY-MM-DD.',
            'data_fim.required' => 'Informe a data de fim da falta.',
            'data_fim.date_format' => 'A data de fim deve estar no formato YYYY-MM-DD.',
            'data_fim.after_or_equal' => 'A data de fim deve ser igual ou posterior à data de início.',
            'categoria_declaracao.required' => 'Informe a categoria da declaração.',
            'categoria_declaracao.in' => 'Categoria de declaração inválida.',
            'anexo.required' => 'Anexe o atestado médico (PDF, JPG ou PNG).',
            'anexo.file' => 'O anexo deve ser um arquivo.',
            'anexo.mimes' => 'O anexo deve ser PDF, JPG ou PNG.',
            'anexo.max' => 'O anexo não pode ter mais de 10 MB.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('tipo') === TipoDocumento::Atestado->value && ! $this->hasFile('anexo')) {
                $validator->errors()->add('anexo', 'Anexe o atestado médico (PDF, JPG ou PNG).');
            }
        });
    }
}
