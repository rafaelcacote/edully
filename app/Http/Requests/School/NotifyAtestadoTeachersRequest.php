<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;

class NotifyAtestadoTeachersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'professor_ids' => ['required', 'array', 'min:1'],
            'professor_ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'professor_ids.required' => 'Selecione ao menos um professor.',
            'professor_ids.min' => 'Selecione ao menos um professor.',
            'professor_ids.*.uuid' => 'Professor inválido.',
            'professor_ids.*.distinct' => 'Há professores duplicados na seleção.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $ids = $this->input('professor_ids', []);

        if (is_string($ids)) {
            $decoded = json_decode($ids, true);
            $ids = is_array($decoded) ? $decoded : [];
        }

        $this->merge([
            'professor_ids' => is_array($ids) ? array_values(array_filter($ids)) : [],
        ]);
    }
}
