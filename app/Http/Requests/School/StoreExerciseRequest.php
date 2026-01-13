<?php

namespace App\Http\Requests\School;

use App\Models\Disciplina;
use App\Models\Teacher;
use App\Models\Turma;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreExerciseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = auth()->user()?->tenants()->first()?->id;
        $user = auth()->user();
        $teacher = $user ? Teacher::query()
            ->where('tenant_id', $tenantId)
            ->where('usuario_id', $user->id)
            ->where('ativo', true)
            ->first() : null;

        $pivotTable = $teacher && $teacher->getConnection()->getDriverName() === 'sqlite'
            ? 'professor_disciplinas'
            : 'escola.professor_disciplinas';

        $teacherDisciplinaIds = $teacher
            ? DB::connection('shared')
                ->table($pivotTable)
                ->where('tenant_id', $tenantId)
                ->where('professor_id', $teacher->id)
                ->pluck('disciplina_id')
                ->toArray()
            : [];

        return [
            'disciplina_id' => [
                'required',
                'uuid',
                Rule::exists(Disciplina::class, 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('ativo', true)
                    ->whereNull('deleted_at'),
                function ($attribute, $value, $fail) use ($teacherDisciplinaIds) {
                    if (empty($teacherDisciplinaIds)) {
                        $fail('Você não tem disciplinas vinculadas. Entre em contato com o administrador.');

                        return;
                    }

                    if (! in_array($value, $teacherDisciplinaIds)) {
                        $fail('Você não tem acesso a esta disciplina.');
                    }
                },
            ],
            'titulo' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'data_entrega' => ['required', 'date', 'after_or_equal:today'],
            'anexo_url' => ['nullable', 'url', 'max:2048'],
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
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'anexo_url' => $this->anexo_url === '' ? null : $this->anexo_url,
            'descricao' => $this->descricao === '' ? null : $this->descricao,
        ]);
    }
}
