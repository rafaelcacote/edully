<?php

namespace App\Http\Requests\Api;

use App\Models\Student;
use App\Models\Turma;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        // Apenas professores podem criar mensagens
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

        // Buscar turmas do professor
        $turmaIds = [];
        $alunoIds = [];
        if ($teacher && $tenantId) {
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
                'nullable',
                'required_without:turma_id',
                'uuid',
                Rule::exists(Student::class, 'id')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
                function ($attribute, $value, $fail) use ($alunoIds) {
                    if ($value && ! empty($alunoIds) && ! in_array($value, $alunoIds)) {
                        $fail('Você não tem acesso a este aluno.');
                    }
                },
            ],
            'turma_id' => [
                'nullable',
                'required_without:aluno_id',
                'uuid',
                Rule::exists(Turma::class, 'id')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
                function ($attribute, $value, $fail) use ($turmaIds) {
                    if ($value && ! empty($turmaIds) && ! in_array($value, $turmaIds)) {
                        $fail('Você não tem acesso a esta turma.');
                    }
                },
            ],
            'titulo' => ['required', 'string', 'max:255'],
            'conteudo' => ['required', 'string'],
            'tipo' => ['nullable', 'string', Rule::in(['outro', 'informativo', 'atencao', 'aviso', 'lembrete'])],
            'prioridade' => ['nullable', 'string', Rule::in(['normal', 'alta', 'media'])],
            'anexo_url' => ['nullable', 'url', 'max:2048'],
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
            'aluno_id.required_without' => 'Selecione um aluno ou uma turma.',
            'aluno_id.exists' => 'Aluno não encontrado.',
            'turma_id.required_without' => 'Selecione um aluno ou uma turma.',
            'turma_id.exists' => 'Turma não encontrada.',
            'titulo.required' => 'O título é obrigatório.',
            'titulo.max' => 'O título não pode ter mais de 255 caracteres.',
            'conteudo.required' => 'O conteúdo é obrigatório.',
            'tipo.in' => 'O tipo de mensagem deve ser: outro, informativo, atencao, aviso ou lembrete.',
            'prioridade.in' => 'A prioridade deve ser: normal, alta ou media.',
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
