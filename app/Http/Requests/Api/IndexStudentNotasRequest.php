<?php

namespace App\Http\Requests\Api;

use App\Support\Bimestre;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexStudentNotasRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $divisao = Bimestre::fromStudentId($this->route('id'));

        return [
            'turma_id' => ['nullable', 'uuid'],
            'disciplina_id' => ['nullable', 'uuid'],
            'bimestre' => ['nullable', 'integer', Rule::in(Bimestre::numeros($divisao))],
            'ano_letivo' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'turma_id.uuid' => 'A turma informada é inválida.',
            'disciplina_id.uuid' => 'A disciplina informada é inválida.',
            ...Bimestre::messages(Bimestre::fromStudentId($this->route('id'))),
            'ano_letivo.integer' => 'O ano letivo informado é inválido.',
        ];
    }
}
