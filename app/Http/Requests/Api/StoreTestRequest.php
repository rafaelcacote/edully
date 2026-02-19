<?php

namespace App\Http\Requests\Api;

use App\Models\Disciplina;
use App\Models\Turma;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreTestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        // Apenas professores podem criar provas
        return $user && $user->isTeacher();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();
        $teacher = $user?->teacher()->where('ativo', true)->first();

        // Buscar tenant_id do professor
        $tenantId = $teacher?->tenant_id;

        // Buscar disciplinas do professor
        $disciplinaIds = [];
        $turmaIds = [];
        if ($teacher && $tenantId) {
            $driver = DB::connection('shared')->getDriverName();
            $pivotTable = $driver === 'sqlite' ? 'professor_disciplinas' : 'escola.professor_disciplinas';
            $turmasTable = $driver === 'sqlite' ? 'turmas' : 'escola.turmas';

            $disciplinaIds = DB::connection('shared')
                ->table($pivotTable)
                ->where('tenant_id', $tenantId)
                ->where('professor_id', $teacher->id)
                ->pluck('disciplina_id')
                ->toArray();

            $turmaIds = DB::connection('shared')
                ->table($turmasTable)
                ->where('tenant_id', $tenantId)
                ->where('professor_id', $teacher->id)
                ->where('ativo', true)
                ->whereNull('deleted_at')
                ->pluck('id')
                ->toArray();
        }

        return [
            'disciplina_id' => [
                'required',
                'uuid',
                Rule::exists(Disciplina::class, 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('ativo', true)
                    ->whereNull('deleted_at'),
                function ($attribute, $value, $fail) use ($disciplinaIds) {
                    if (empty($disciplinaIds)) {
                        $fail('Você não tem disciplinas vinculadas. Entre em contato com o administrador.');

                        return;
                    }

                    if (! in_array($value, $disciplinaIds)) {
                        $fail('Você não tem acesso a esta disciplina.');
                    }
                },
            ],
            'titulo' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'data_prova' => ['required', 'date', 'after_or_equal:today'],
            'horario' => ['nullable', 'string', 'max:10'],
            'sala' => ['nullable', 'string', 'max:50'],
            'duracao_minutos' => ['nullable', 'integer', 'min:1', 'max:600'],
            'turma_id' => [
                'required',
                'uuid',
                Rule::exists(Turma::class, 'id')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
                function ($attribute, $value, $fail) use ($turmaIds) {
                    if (! empty($turmaIds) && ! in_array($value, $turmaIds)) {
                        $fail('Você não tem acesso a esta turma.');
                    }
                },
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'disciplina_id.required' => 'Selecione uma disciplina.',
            'disciplina_id.exists' => 'Disciplina não encontrada ou inativa.',
            'titulo.required' => 'Informe o título da prova.',
            'titulo.max' => 'O título não pode ter mais de 255 caracteres.',
            'data_prova.required' => 'Informe a data da prova.',
            'data_prova.date' => 'A data da prova deve ser uma data válida.',
            'data_prova.after_or_equal' => 'A data da prova deve ser hoje ou uma data futura.',
            'horario.max' => 'O horário não pode ter mais de 10 caracteres.',
            'sala.max' => 'A sala não pode ter mais de 50 caracteres.',
            'duracao_minutos.integer' => 'A duração deve ser um número inteiro.',
            'duracao_minutos.min' => 'A duração deve ser de pelo menos 1 minuto.',
            'duracao_minutos.max' => 'A duração não pode ser superior a 600 minutos (10 horas).',
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
