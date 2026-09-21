<?php

namespace App\Actions\School;

use App\Actions\Api\ListStudentTeachersAction;
use App\Actions\Api\NotifyMessagePushRecipients;
use App\Enums\TipoDocumento;
use App\Models\Documento;
use App\Models\Message;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class NotifyAtestadoTeachersAction
{
    public function __construct(
        private readonly ListStudentTeachersAction $listStudentTeachers,
        private readonly NotifyMessagePushRecipients $notifyMessagePushRecipients,
    ) {}

    /**
     * @param  list<string>  $professorIds
     * @return array{notificados: int, recados: int}
     */
    public function execute(Documento $documento, array $professorIds, User $remetente): array
    {
        if ($documento->tipo !== TipoDocumento::Atestado) {
            throw ValidationException::withMessages([
                'documento' => 'Somente atestados podem notificar professores.',
            ]);
        }

        $aluno = Student::query()
            ->where('id', $documento->aluno_id)
            ->where('tenant_id', $documento->tenant_id)
            ->where('ativo', true)
            ->first();

        if (! $aluno) {
            throw ValidationException::withMessages([
                'aluno_id' => 'Aluno do atestado não encontrado.',
            ]);
        }

        $professoresDaTurma = $this->listStudentTeachers->execute($aluno);
        $allowedIds = $professoresDaTurma->pluck('id')->map(fn ($id) => (string) $id)->all();

        $selected = array_values(array_unique(array_filter($professorIds)));

        if ($selected === []) {
            throw ValidationException::withMessages([
                'professor_ids' => 'Selecione ao menos um professor.',
            ]);
        }

        foreach ($selected as $professorId) {
            if (! in_array((string) $professorId, $allowedIds, true)) {
                throw ValidationException::withMessages([
                    'professor_ids' => 'Um ou mais professores não pertencem à turma do aluno.',
                ]);
            }
        }

        $professores = Teacher::query()
            ->where('tenant_id', $documento->tenant_id)
            ->whereIn('id', $selected)
            ->where('ativo', true)
            ->whereNotNull('usuario_id')
            ->with('usuario:id,nome_completo')
            ->get();

        if ($professores->isEmpty()) {
            throw ValidationException::withMessages([
                'professor_ids' => 'Nenhum dos professores selecionados possui usuário ativo para receber notificação.',
            ]);
        }

        $alunoNome = $aluno->nome_social ?: $aluno->nome;
        $periodo = $this->formatPeriodo($documento);
        $titulo = 'Atestado médico: '.$alunoNome;
        $conteudo = $periodo
            ? "O aluno {$alunoNome} está de atestado médico no período {$periodo}."
            : "O aluno {$alunoNome} está de atestado médico.";
        $conteudo .= "\n\nVerifique o documento na secretaria da escola.";

        $createdMessages = [];

        DB::connection('shared')->transaction(function () use (
            $documento,
            $professores,
            $remetente,
            $aluno,
            $titulo,
            $conteudo,
            &$createdMessages
        ) {
            foreach ($professores as $professor) {
                // Destinatário explícito = somente o professor.
                // Não preencher turma_id: no sistema isso significa "recado para a turma inteira".
                $createdMessages[] = Message::create([
                    'tenant_id' => $documento->tenant_id,
                    'remetente_id' => $remetente->id,
                    'destinatario_id' => $professor->usuario_id,
                    'aluno_id' => $aluno->id,
                    'turma_id' => null,
                    'conversa_id' => (string) Str::uuid(),
                    'titulo' => mb_substr($titulo, 0, 255),
                    'conteudo' => $conteudo,
                    'tipo' => 'aviso',
                    'prioridade' => 'alta',
                    'anexo_url' => $documento->anexo_url,
                    'lida' => false,
                ]);
            }

            $idsAnteriores = collect($documento->professores_notificados_ids ?? [])
                ->map(fn ($id) => (string) $id)
                ->all();
            $idsNovos = $professores->pluck('id')->map(fn ($id) => (string) $id)->all();

            $documento->forceFill([
                'professores_notificados_em' => now(),
                'professores_notificados_por' => $remetente->id,
                'professores_notificados_ids' => array_values(array_unique([
                    ...$idsAnteriores,
                    ...$idsNovos,
                ])),
            ])->save();
        });

        foreach ($createdMessages as $message) {
            $this->notifyMessagePushRecipients->queue($message);
        }

        return [
            'notificados' => count($createdMessages),
            'recados' => count($createdMessages),
        ];
    }

    protected function formatPeriodo(Documento $documento): ?string
    {
        $inicio = $documento->data_inicio?->format('d/m/Y');
        $fim = $documento->data_fim?->format('d/m/Y');

        if ($inicio && $fim) {
            return "{$inicio} a {$fim}";
        }

        if ($inicio) {
            return "a partir de {$inicio}";
        }

        if ($fim) {
            return "até {$fim}";
        }

        return null;
    }
}
