<?php

namespace App\Actions\Api;

use App\Models\Documento;
use App\Models\Student;
use App\Services\ExpoPushService;
use Illuminate\Support\Facades\Log;

class NotifyDocumentoPushRecipients
{
    public function __construct(
        private readonly ExpoPushService $expoPushService,
    ) {}

    /**
     * Enfileira o push após a resposta HTTP (ou executa na hora em testes).
     */
    public function queue(Documento $documento): void
    {
        $documentoId = $documento->id;

        $send = function () use ($documentoId): void {
            $fresh = Documento::query()->find($documentoId);
            if (! $fresh) {
                return;
            }

            $this->execute($fresh);
        };

        if (app()->runningUnitTests()) {
            $send();

            return;
        }

        dispatch($send)->afterResponse();
    }

    public function execute(Documento $documento): void
    {
        try {
            $recipientUserIds = $this->resolveRecipientUserIds($documento);
            if ($recipientUserIds === []) {
                return;
            }

            $tipo = $documento->tipo?->value ?? (string) $documento->tipo;
            $status = $documento->status?->value ?? (string) $documento->status;
            $tituloDocumento = $documento->titulo ?: ($documento->tipo?->label() ?? 'Documento');

            [$title, $body] = $this->buildNotificationCopy($tipo, $status, $tituloDocumento);

            $this->expoPushService->sendToUsers(
                $recipientUserIds,
                $title,
                mb_substr($body, 0, 178),
                [
                    'type' => 'documento',
                    'documentoId' => $documento->id,
                    'alunoId' => $documento->aluno_id,
                    'tipo' => $tipo,
                    'status' => $status,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('Falha ao notificar push de documento: '.$e->getMessage(), [
                'documento_id' => $documento->id ?? null,
            ]);
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function buildNotificationCopy(string $tipo, string $status, string $tituloDocumento): array
    {
        if ($tipo === 'documento_escola') {
            return ['Novo documento', $tituloDocumento.' disponível na secretaria'];
        }

        $statusLabel = match ($status) {
            'em_analise' => 'em análise',
            'aprovado' => 'aprovado',
            'recusado' => 'recusado',
            'atendido' => 'atendido',
            'cancelado' => 'cancelado',
            default => $status,
        };

        return ['Documento atualizado', $tituloDocumento.' · Status: '.$statusLabel];
    }

    /**
     * @return list<string>
     */
    protected function resolveRecipientUserIds(Documento $documento): array
    {
        $aluno = Student::query()
            ->where('id', $documento->aluno_id)
            ->where('ativo', true)
            ->first();

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
