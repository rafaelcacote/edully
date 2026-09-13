<?php

namespace App\Actions\Api;

use App\Models\Message;
use App\Models\Responsavel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ResolveMessageThreadAction
{
    /**
     * Resolve conversation context for a reply (or bootstrap a new conversa_id).
     *
     * @return array{
     *     conversa_id: string,
     *     mensagem_pai_id: string|null,
     *     aluno_id: string,
     *     tenant_id: string,
     *     destinatario_id: string|null,
     *     titulo: string|null,
     *     anchor: Message|null
     * }
     */
    public function execute(User $user, ?string $mensagemPaiId, ?string $conversaId): array
    {
        $anchor = null;

        if ($mensagemPaiId) {
            $anchor = Message::query()->findOrFail($mensagemPaiId);
        } elseif ($conversaId) {
            $anchor = Message::query()
                ->where('conversa_id', $conversaId)
                ->orderBy('created_at')
                ->firstOrFail();
        }

        if (! $anchor) {
            return [
                'conversa_id' => (string) Str::uuid(),
                'mensagem_pai_id' => null,
                'aluno_id' => '',
                'tenant_id' => '',
                'destinatario_id' => null,
                'titulo' => null,
                'anchor' => null,
            ];
        }

        if (! $this->userCanAccessThreadMessage($user, $anchor)) {
            abort(403, 'Você não tem permissão para responder esta conversa.');
        }

        if ($anchor->turma_id && ! $anchor->conversa_id) {
            abort(422, 'Recados enviados para a turma inteira não formam conversa. Responda a um recado de aluno específico.');
        }

        $conversaIdResolved = $anchor->conversa_id;

        if (! $conversaIdResolved) {
            $conversaIdResolved = (string) Str::uuid();
            Message::query()
                ->where('id', $anchor->id)
                ->update(['conversa_id' => $conversaIdResolved]);
            $anchor->conversa_id = $conversaIdResolved;
        }

        return [
            'conversa_id' => $conversaIdResolved,
            'mensagem_pai_id' => $mensagemPaiId ?: $anchor->id,
            'aluno_id' => (string) $anchor->aluno_id,
            'tenant_id' => (string) $anchor->tenant_id,
            'destinatario_id' => $this->resolveReplyDestinatario($user, $anchor, $conversaIdResolved),
            'titulo' => $anchor->titulo,
            'anchor' => $anchor,
        ];
    }

    protected function userCanAccessThreadMessage(User $user, Message $message): bool
    {
        if ($user->isResponsavel()) {
            return $this->responsavelLinkedToAluno($user, $message->aluno_id);
        }

        if ($user->isTeacher()) {
            if ($message->remetente_id === $user->id || $message->destinatario_id === $user->id) {
                return true;
            }

            if ($message->conversa_id) {
                return Message::query()
                    ->where('conversa_id', $message->conversa_id)
                    ->where(function ($query) use ($user): void {
                        $query->where('remetente_id', $user->id)
                            ->orWhere('destinatario_id', $user->id);
                    })
                    ->exists();
            }
        }

        return false;
    }

    protected function resolveReplyDestinatario(User $user, Message $anchor, string $conversaId): ?string
    {
        if ($anchor->remetente_id && $anchor->remetente_id !== $user->id) {
            return $anchor->remetente_id;
        }

        if ($anchor->destinatario_id && $anchor->destinatario_id !== $user->id) {
            return $anchor->destinatario_id;
        }

        $candidates = Message::query()
            ->where('conversa_id', $conversaId)
            ->orderByDesc('created_at')
            ->get(['id', 'remetente_id', 'destinatario_id']);

        foreach ($candidates as $other) {
            if ($other->remetente_id && $other->remetente_id !== $user->id) {
                return $other->remetente_id;
            }

            if ($other->destinatario_id && $other->destinatario_id !== $user->id) {
                return $other->destinatario_id;
            }
        }

        return null;
    }

    protected function responsavelLinkedToAluno(User $user, ?string $alunoId): bool
    {
        if (! $alunoId) {
            return false;
        }

        $responsavelIds = Responsavel::query()
            ->where('usuario_id', $user->id)
            ->pluck('id')
            ->all();

        if ($responsavelIds === []) {
            return false;
        }

        $driver = DB::connection('shared')->getDriverName();
        $pivotTable = $driver === 'sqlite' ? 'aluno_responsavel' : 'escola.aluno_responsavel';

        return DB::connection('shared')
            ->table($pivotTable)
            ->whereIn('responsavel_id', $responsavelIds)
            ->where('aluno_id', $alunoId)
            ->exists();
    }
}
