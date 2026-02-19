<?php

namespace App\Http\Requests\School;

use App\Models\Teacher;
use App\Models\Turma;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = auth()->user()?->tenants()->first()?->id;
        $user = auth()->user();

        $teacher = Teacher::query()
            ->where('tenant_id', $tenantId)
            ->where('usuario_id', $user->id)
            ->where('ativo', true)
            ->first();

        // Se for professor, validar apenas disciplinas que ele leciona
        // Se for Administrador Escola, pode usar qualquer disciplina do tenant
        if ($teacher) {
            $disciplinaIds = $teacher->disciplinas()
                ->where('ativo', true)
                ->pluck('disciplina_id')
                ->toArray();

            $disciplinaRule = [
                'required',
                'uuid',
                Rule::in($disciplinaIds),
            ];
        } else {
            // Administrador Escola: pode usar qualquer disciplina do tenant
            $disciplinaRule = [
                'required',
                'uuid',
                Rule::exists(\App\Models\Disciplina::class, 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('ativo', true)
                    ->whereNull('deleted_at'),
            ];
        }

        return [
            'disciplina_id' => $disciplinaRule,
            'titulo' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'data_prova' => ['required', 'date', 'after_or_equal:today'],
            'horario' => ['nullable', 'date_format:H:i'],
            'sala' => ['nullable', 'string', 'max:50'],
            'duracao_minutos' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'turma_id' => [
                'required',
                'uuid',
                Rule::exists(Turma::class, 'id')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'disciplina_id.required' => 'Selecione uma disciplina.',
            'disciplina_id.in' => 'Disciplina inválida. Você só pode criar provas para disciplinas que leciona.',
            'disciplina_id.exists' => 'Disciplina não encontrada ou inativa.',
            'titulo.required' => 'Informe o título da prova.',
            'titulo.max' => 'O título não pode ter mais de 255 caracteres.',
            'data_prova.required' => 'Informe a data da prova.',
            'data_prova.date' => 'A data da prova deve ser uma data válida.',
            'data_prova.after_or_equal' => 'A data da prova deve ser hoje ou uma data futura.',
            'horario.date_format' => 'O horário deve estar no formato HH:mm.',
            'sala.max' => 'A sala não pode ter mais de 50 caracteres.',
            'duracao_minutos.integer' => 'A duração deve ser um número inteiro.',
            'duracao_minutos.min' => 'A duração deve ser de pelo menos 1 minuto.',
            'duracao_minutos.max' => 'A duração não pode ser maior que 1440 minutos (24 horas).',
            'turma_id.required' => 'Selecione uma turma.',
            'turma_id.exists' => 'Turma não encontrada.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'descricao' => $this->descricao === '' ? null : $this->descricao,
            'horario' => $this->horario === '' ? null : $this->horario,
            'sala' => $this->sala === '' ? null : $this->sala,
            'duracao_minutos' => $this->duracao_minutos === '' ? null : $this->duracao_minutos,
        ]);
    }
}
