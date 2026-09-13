<?php

namespace App\Http\Requests\School;

use App\Models\Disciplina;
use App\Models\Teacher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncTurmaDisciplinasRequest extends FormRequest
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
        $tenantId = auth()->user()?->tenants()->first()?->id;

        return [
            'disciplinas' => ['nullable', 'array'],
            'disciplinas.*.disciplina_id' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists(Disciplina::class, 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('ativo', true),
            ],
            'disciplinas.*.professor_id' => [
                'nullable',
                'uuid',
                Rule::exists(Teacher::class, 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('ativo', true),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'disciplinas.array' => 'As disciplinas devem ser enviadas em uma lista.',
            'disciplinas.*.disciplina_id.required' => 'Selecione a disciplina.',
            'disciplinas.*.disciplina_id.exists' => 'A disciplina selecionada não foi encontrada.',
            'disciplinas.*.disciplina_id.distinct' => 'Não é possível vincular a mesma disciplina mais de uma vez.',
            'disciplinas.*.professor_id.exists' => 'O professor selecionado não foi encontrado.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $disciplinas = $this->input('disciplinas', []);

        if (! is_array($disciplinas)) {
            return;
        }

        $normalized = collect($disciplinas)
            ->map(function ($item) {
                if (! is_array($item)) {
                    return null;
                }

                return [
                    'disciplina_id' => $item['disciplina_id'] ?? null,
                    'professor_id' => empty($item['professor_id'] ?? null) ? null : $item['professor_id'],
                ];
            })
            ->filter(fn ($item) => is_array($item) && ! empty($item['disciplina_id']))
            ->values()
            ->all();

        $this->merge([
            'disciplinas' => $normalized,
        ]);
    }
}
