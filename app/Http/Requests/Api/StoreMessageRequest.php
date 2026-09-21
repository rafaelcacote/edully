<?php

namespace App\Http\Requests\Api;

use App\Actions\Api\ListStudentTeachersAction;
use App\Enums\NivelPrioridade;
use App\Models\Message;
use App\Models\Responsavel;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Turma;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && ($user->isTeacher() || $user->isResponsavel());
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if ($this->isReply()) {
            return $this->replyRules();
        }

        if ($this->user()?->isResponsavel()) {
            return $this->responsavelRules();
        }

        return $this->teacherRules();
    }

    public function isReply(): bool
    {
        return filled($this->input('mensagem_pai_id')) || filled($this->input('conversa_id'));
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function replyRules(): array
    {
        return [
            'mensagem_pai_id' => [
                'nullable',
                'required_without:conversa_id',
                'uuid',
                Rule::exists(Message::class, 'id'),
            ],
            'conversa_id' => [
                'nullable',
                'required_without:mensagem_pai_id',
                'uuid',
            ],
            'titulo' => ['nullable', 'string', 'max:255'],
            'conteudo' => ['required', 'string'],
            'tipo' => ['nullable', 'string', Rule::in(['outro', 'informativo', 'atencao', 'aviso', 'lembrete'])],
            'prioridade' => ['nullable', 'string', Rule::in(NivelPrioridade::values())],
            'anexo_url' => ['nullable', 'url', 'max:2048'],
        ];
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function teacherRules(): array
    {
        $user = $this->user();
        $teacher = $user ? Teacher::query()
            ->where('usuario_id', $user->id)
            ->where('ativo', true)
            ->first() : null;

        $tenantId = $teacher?->tenant_id;

        $turmaIds = [];
        $alunoIds = [];
        if ($teacher && $tenantId) {
            $turmasTable = (new Turma)->getTable();

            $turmaIds = $teacher->turmas()
                ->where($turmasTable.'.tenant_id', $tenantId)
                ->where($turmasTable.'.ativo', true)
                ->pluck($turmasTable.'.id')
                ->toArray();

            if (! empty($turmaIds)) {
                $driver = DB::connection('shared')->getDriverName();
                $pivotTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';
                $alunosTable = $driver === 'sqlite' ? 'alunos' : 'escola.alunos';

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
                    if ($value && ! in_array($value, $alunoIds, true)) {
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
                    if ($value && ! in_array($value, $turmaIds, true)) {
                        $fail('Você não tem acesso a esta turma.');
                    }
                },
            ],
            ...$this->commonContentRules(requiredTitulo: true),
        ];
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function responsavelRules(): array
    {
        $user = $this->user();
        $responsavelIds = Responsavel::query()
            ->where('usuario_id', $user?->id)
            ->pluck('id')
            ->all();

        $driver = DB::connection('shared')->getDriverName();
        $pivotTable = $driver === 'sqlite' ? 'aluno_responsavel' : 'escola.aluno_responsavel';

        $alunoIds = [];
        if ($responsavelIds !== []) {
            $alunoIds = DB::connection('shared')
                ->table($pivotTable)
                ->whereIn('responsavel_id', $responsavelIds)
                ->pluck('aluno_id')
                ->unique()
                ->values()
                ->all();
        }

        return [
            'aluno_id' => [
                'required',
                'uuid',
                Rule::exists(Student::class, 'id')
                    ->where('ativo', true)
                    ->whereNull('deleted_at'),
                function ($attribute, $value, $fail) use ($alunoIds) {
                    if ($value && ! in_array($value, $alunoIds, true)) {
                        $fail('Você não tem acesso a este aluno.');
                    }
                },
            ],
            'professor_id' => [
                'required',
                'uuid',
                Rule::exists(Teacher::class, 'id')
                    ->where('ativo', true)
                    ->whereNull('deleted_at'),
                function ($attribute, $value, $fail) {
                    $alunoId = $this->input('aluno_id');
                    if (! $value || ! $alunoId) {
                        return;
                    }

                    $aluno = Student::query()
                        ->where('id', $alunoId)
                        ->where('ativo', true)
                        ->first();

                    if (! $aluno) {
                        return;
                    }

                    $teachers = app(ListStudentTeachersAction::class)->execute($aluno);
                    if (! $teachers->contains(fn (Teacher $teacher) => $teacher->id === $value)) {
                        $fail('Este professor não está vinculado às turmas deste aluno.');
                    }
                },
            ],
            ...$this->commonContentRules(requiredTitulo: true),
        ];
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function commonContentRules(bool $requiredTitulo = true): array
    {
        return [
            'titulo' => [$requiredTitulo ? 'required' : 'nullable', 'string', 'max:255'],
            'conteudo' => ['required', 'string'],
            'tipo' => ['nullable', 'string', Rule::in(['outro', 'informativo', 'atencao', 'aviso', 'lembrete'])],
            'prioridade' => ['nullable', 'string', Rule::in(NivelPrioridade::values())],
            'anexo_url' => ['nullable', 'url', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'aluno_id.required' => 'Selecione um aluno.',
            'aluno_id.required_without' => 'Selecione um aluno ou uma turma.',
            'aluno_id.exists' => 'Aluno não encontrado.',
            'turma_id.required_without' => 'Selecione um aluno ou uma turma.',
            'turma_id.exists' => 'Turma não encontrada.',
            'professor_id.required' => 'Selecione um professor.',
            'professor_id.exists' => 'Professor não encontrado.',
            'mensagem_pai_id.required_without' => 'Informe a mensagem ou a conversa para responder.',
            'conversa_id.required_without' => 'Informe a mensagem ou a conversa para responder.',
            'mensagem_pai_id.exists' => 'Mensagem não encontrada.',
            'titulo.required' => 'O título é obrigatório.',
            'titulo.max' => 'O título não pode ter mais de 255 caracteres.',
            'conteudo.required' => 'O conteúdo é obrigatório.',
            'tipo.in' => 'O tipo de recado deve ser: outro, informativo, atencao, aviso ou lembrete.',
            'prioridade.in' => 'A prioridade deve ser: baixa, normal, alta ou urgente.',
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
            'mensagem_pai_id' => $this->mensagem_pai_id === '' ? null : $this->mensagem_pai_id,
            'conversa_id' => $this->conversa_id === '' ? null : $this->conversa_id,
        ]);
    }
}
