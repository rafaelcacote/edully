<?php

namespace App\Actions\Api;

use App\Models\Aviso;
use App\Models\Responsavel;
use App\Models\Teacher;
use App\Services\ExpoPushService;
use Illuminate\Support\Facades\Log;

class NotifyAvisoPushRecipients
{
    public function __construct(
        private readonly ExpoPushService $expoPushService,
    ) {}

    /**
     * Enfileira o push após a resposta HTTP (ou executa na hora em testes).
     */
    public function queue(Aviso $aviso): void
    {
        $avisoId = $aviso->id;

        $send = function () use ($avisoId): void {
            $fresh = Aviso::query()->find($avisoId);
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

    public function execute(Aviso $aviso): void
    {
        try {
            if (! $aviso->publicado || $aviso->isExpirado()) {
                return;
            }

            $recipientUserIds = $this->resolveRecipientUserIds($aviso);
            if ($recipientUserIds === []) {
                return;
            }

            $schoolName = $aviso->tenant?->nome
                ?? $aviso->tenant()->value('nome')
                ?? 'Escola';

            $title = 'Novo comunicado';
            $body = trim(($aviso->titulo ?: 'A escola publicou um comunicado').' · '.$schoolName);

            $this->expoPushService->sendToUsers(
                $recipientUserIds,
                $title,
                mb_substr($body, 0, 178),
                [
                    'type' => 'aviso',
                    'avisoId' => $aviso->id,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('Falha ao notificar push de comunicado: '.$e->getMessage(), [
                'aviso_id' => $aviso->id ?? null,
            ]);
        }
    }

    /**
     * @return list<string>
     */
    protected function resolveRecipientUserIds(Aviso $aviso): array
    {
        $publicoAlvo = $aviso->publico_alvo ?: 'todos';
        $ids = [];

        if (in_array($publicoAlvo, ['todos', 'professores'], true)) {
            $ids = array_merge($ids, $this->teacherUserIdsForTenant($aviso->tenant_id));
        }

        if (in_array($publicoAlvo, ['todos', 'responsaveis'], true)) {
            $ids = array_merge($ids, $this->responsavelUserIdsForTenant($aviso->tenant_id));
        }

        return array_values(array_unique(array_filter(
            $ids,
            fn (?string $id) => filled($id) && $id !== $aviso->criado_por
        )));
    }

    /**
     * @return list<string>
     */
    protected function teacherUserIdsForTenant(string $tenantId): array
    {
        return Teacher::query()
            ->where('tenant_id', $tenantId)
            ->where('ativo', true)
            ->whereNotNull('usuario_id')
            ->pluck('usuario_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    protected function responsavelUserIdsForTenant(string $tenantId): array
    {
        return Responsavel::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('usuario_id')
            ->pluck('usuario_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
