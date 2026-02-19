<?php

namespace App\Http\Requests\School;

use App\Models\Disciplina;
use App\Models\Teacher;
use App\Models\Turma;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UpdateExerciseRequest extends FormRequest
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
            'data_entrega' => ['required', 'date'],
            'anexo' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,txt,rtf,odt,ods', 'max:10240'],
            'turma_id' => [
                'required',
                'uuid',
                Rule::exists(Turma::class, 'id')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
            ],
            'tipo_exercicio' => [
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

    public function messages(): array
    {
        return [
            'disciplina_id.required' => 'Selecione uma disciplina.',
            'disciplina_id.exists' => 'Disciplina não encontrada ou inativa.',
            'titulo.required' => 'Informe o título do exercício.',
            'titulo.max' => 'O título não pode ter mais de 255 caracteres.',
            'data_entrega.required' => 'Informe a data de entrega.',
            'data_entrega.date' => 'A data de entrega deve ser uma data válida.',
            'anexo.file' => 'O anexo deve ser um arquivo.',
            'anexo.mimes' => 'O anexo deve ser um arquivo PDF, Word, Excel ou texto.',
            'anexo.max' => 'O anexo não pode ter mais de 10MB.',
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
        ]);
    }
}
