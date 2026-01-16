<?php

namespace App\Http\Requests\School;

use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreMessageRequest extends FormRequest
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

        // Get students from teacher's turmas
        $alunoIds = [];
        if ($teacher) {
            $driver = DB::connection('shared')->getDriverName();
            $pivotTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';
            $alunosTable = $driver === 'sqlite' ? 'alunos' : 'escola.alunos';
            $turmasTable = $driver === 'sqlite' ? 'turmas' : 'escola.turmas';

            $turmaIds = DB::connection('shared')
                ->table($turmasTable)
                ->where('tenant_id', $tenantId)
                ->where('professor_id', $teacher->id)
                ->where('ativo', true)
                ->pluck('id')
                ->toArray();

            if (! empty($turmaIds)) {
                $alunoIds = DB::connection('shared')
                    ->table($pivotTable.' as matriculas')
                    ->join($alunosTable.' as alunos', 'alunos.id', '=', 'matriculas.aluno_id')
                    ->where('matriculas.tenant_id', $tenantId)
                    ->where('matriculas.status', 'ativo')
                    ->whereIn('matriculas.turma_id', $turmaIds)
                    ->whereNull('alunos.deleted_at')
                    ->pluck('alunos.id')
                    ->toArray();
            }
        }

        return [
            'aluno_id' => [
                'required',
                'uuid',
                Rule::exists(Student::class, 'id')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
                function ($attribute, $value, $fail) use ($alunoIds) {
                    if (empty($alunoIds)) {
                        $fail('Você não tem alunos vinculados às suas turmas.');

                        return;
                    }

                    if (! in_array($value, $alunoIds)) {
                        $fail('Você não tem acesso a este aluno.');
                    }
                },
            ],
            'titulo' => ['required', 'string', 'max:255'],
            'conteudo' => ['required', 'string'],
            'tipo' => ['nullable', 'string'],
            'prioridade' => ['nullable', 'string'],
            'anexo_url' => ['nullable', 'url', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'aluno_id.required' => 'Selecione um aluno.',
            'aluno_id.exists' => 'Aluno não encontrado.',
            'titulo.required' => 'Informe o título da mensagem.',
            'titulo.max' => 'O título não pode ter mais de 255 caracteres.',
            'conteudo.required' => 'Informe o conteúdo da mensagem.',
            'anexo_url.url' => 'A URL do anexo deve ser válida.',
            'anexo_url.max' => 'A URL do anexo não pode ter mais de 2048 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'anexo_url' => $this->anexo_url === '' ? null : $this->anexo_url,
            'tipo' => $this->tipo === '' ? null : $this->tipo,
            'prioridade' => $this->prioridade === '' ? null : $this->prioridade,
        ]);
    }
}
