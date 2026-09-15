<?php

namespace App\Actions\Api;

use App\Models\Message;
use App\Models\Student;
use App\Services\ExpoPushService;
use Illuminate\Support\Facades\Log;

class NotifyMessagePushRecipients
{
    public function __construct(
        private readonly ExpoPushService $expoPushService,
    ) {}

    /**
     * Enfileira o push após a resposta HTTP (ou executa na hora em testes).
     */
    public function queue(Message $message): void
    {
        $messageId = $message->id;

        $send = function () use ($messageId): void {
            $fresh = Message::query()->find($messageId);
            if (! $fresh) {
                return;
            }

            $this->execute($fresh);
        };

        // Em testes, dispara na hora (afterResponse + terminate é frágil no Pest).
        if (app()->runningUnitTests()) {
            $send();

            return;
        }

        dispatch($send)->afterResponse();
    }

    public function execute(Message $message): void
    {
        try {
            $recipientUserIds = $this->resolveRecipientUserIds($message);
            if ($recipientUserIds === []) {
                return;
            }

            $senderName = $message->remetente?->nome_completo
                ?? $message->remetente()->value('nome_completo')
                ?? 'Eduly';

            $title = 'Nova mensagem';
            $body = trim(($message->titulo ?: 'Você recebeu uma nova mensagem').' · '.$senderName);

            $this->expoPushService->sendToUsers(
                $recipientUserIds,
                $title,
                mb_substr($body, 0, 178),
                [
                    'type' => 'message',
                    'messageId' => $message->id,
                    'conversaId' => $message->conversa_id ?: $message->id,
                    'alunoId' => $message->aluno_id,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('Falha ao notificar push de mensagem: '.$e->getMessage(), [
                'message_id' => $message->id ?? null,
            ]);
        }
    }

    /**
     * @return list<string>
     */
    protected function resolveRecipientUserIds(Message $message): array
    {
        $ids = [];

        if ($message->destinatario_id) {
            $ids[] = $message->destinatario_id;
        }

        // Professor → aluno (sem destinatario_id): notifica responsáveis vinculados
        if ($message->aluno_id && ! $message->destinatario_id) {
            $ids = array_merge($ids, $this->responsavelUserIdsForAluno($message->aluno_id));
        }

        return array_values(array_unique(array_filter(
            $ids,
            fn (?string $id) => filled($id) && $id !== $message->remetente_id
        )));
    }

    /**
     * @return list<string>
     */
    protected function responsavelUserIdsForAluno(string $alunoId): array
    {
        $aluno = Student::query()->where('id', $alunoId)->where('ativo', true)->first();
        if (! $aluno) {
            return [];
        }

        return $aluno->parents()
            ->whereNotNull('usuario_id')
            ->pluck('usuario_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
