<?php

namespace App\Http\Requests\Api;

use App\Models\Disciplina;
use App\Models\Turma;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UpdateExerciseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        // Apenas professores podem atualizar exercícios
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
                'sometimes',
                'required',
                'uuid',
                Rule::exists(Disciplina::class, 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('ativo', true)
                    ->whereNull('deleted_at'),
                function ($attribute, $value, $fail) use ($disciplinaIds) {
                    if (! empty($disciplinaIds) && ! in_array($value, $disciplinaIds)) {
                        $fail('Você não tem acesso a esta disciplina.');
                    }
                },
            ],
            'titulo' => ['sometimes', 'required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'data_entrega' => ['sometimes', 'required', 'date', 'after_or_equal:today'],
            'anexo_url' => ['nullable', 'url', 'max:2048'],
            'turma_id' => [
                'sometimes',
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
            'tipo_exercicio' => [
                'sometimes',
                'required',
                'string',
                Rule::in([
                    'exercicio_caderno',
                    'exercicio_livro',
                    'trabalho',
                ]),
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
            'titulo.required' => 'Informe o título do exercício.',
            'titulo.max' => 'O título não pode ter mais de 255 caracteres.',
            'data_entrega.required' => 'Informe a data de entrega.',
            'data_entrega.date' => 'A data de entrega deve ser uma data válida.',
            'data_entrega.after_or_equal' => 'A data de entrega deve ser hoje ou uma data futura.',
            'anexo_url.url' => 'A URL do anexo deve ser válida.',
            'anexo_url.max' => 'A URL do anexo não pode ter mais de 2048 caracteres.',
            'turma_id.required' => 'Selecione uma turma.',
            'turma_id.exists' => 'Turma não encontrada.',
            'tipo_exercicio.required' => 'Selecione o tipo de exercício.',
            'tipo_exercicio.in' => 'O tipo de exercício selecionado é inválido.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'descricao' => $this->descricao === '' ? null : $this->descricao,
            'anexo_url' => $this->anexo_url === '' ? null : $this->anexo_url,
        ]);
    }
}
