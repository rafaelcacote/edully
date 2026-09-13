<?php

namespace App\Actions\Api;

use App\Models\Message;
use App\Models\Responsavel;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListConversationsAction
{
    /**
     * List conversations for the authenticated mobile user.
     *
     * @return LengthAwarePaginator<int, object>
     */
    public function execute(User $user, ?string $alunoId = null, ?bool $lida = null): LengthAwarePaginator
    {
        $base = $this->accessibleMessagesQuery($user, $alunoId);

        if ($base === null) {
            return new Paginator([], 0, 15, 1, [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);
        }

        $allMessages = $base
            ->with([
                'aluno:id,nome,nome_social',
                'remetente:id,nome_completo,avatar_url',
                'destinatario:id,nome_completo,avatar_url',
                'turma:id,nome',
            ])
            ->orderByDesc('created_at')
            ->get();

        $grouped = $allMessages->groupBy(fn (Message $message) => $message->conversa_id ?: $message->id);

        $conversas = $grouped->map(function (Collection $messages) use ($user, $lida) {
            /** @var Message $ultima */
            $ultima = $messages
                ->sortByDesc(fn (Message $message) => sprintf(
                    '%s-%s',
                    $message->created_at?->format('Y-m-d H:i:s.u') ?? '',
                    $message->id
                ))
                ->first();
            $conversaId = $ultima->conversa_id ?: $ultima->id;

            $unreadCount = $messages
                ->filter(fn (Message $message) => $this->isUnreadForUser($user, $message))
                ->count();

            if ($lida === true && $unreadCount > 0) {
                return null;
            }

            if ($lida === false && $unreadCount === 0) {
                return null;
            }

            return (object) [
                'conversa_id' => $conversaId,
                'unread_count' => $unreadCount,
                'ultima_mensagem' => $ultima,
                'aluno' => $ultima->aluno,
                'participantes' => $this->extractParticipantes($messages),
                'messages_count' => $messages->count(),
                'created_at' => $messages->min('created_at'),
                'updated_at' => $ultima->created_at,
            ];
        })
            ->filter()
            ->sortByDesc(fn ($conversa) => $conversa->updated_at?->timestamp ?? 0)
            ->values();

        $page = max(1, (int) request()->input('page', 1));
        $perPage = 15;
        $total = $conversas->count();
        $items = $conversas->slice(($page - 1) * $perPage, $perPage)->values();

        return new Paginator($items, $total, $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }

    /**
     * @return Builder<Message>|null
     */
    public function accessibleMessagesQuery(User $user, ?string $alunoId = null): ?Builder
    {
        if ($user->isResponsavel()) {
            $alunoIds = $this->linkedAlunoIds($user);
            if ($alunoIds === []) {
                return null;
            }

            if ($alunoId !== null) {
                if (! in_array($alunoId, $alunoIds, true)) {
                    abort(403, 'Aluno não encontrado ou você não tem permissão para acessar este aluno.');
                }
                $alunoIds = [$alunoId];
            }

            return Message::query()->whereIn('aluno_id', $alunoIds);
        }

        if ($user->isTeacher()) {
            return Message::query()->where(function ($builder) use ($user): void {
                $builder->where('remetente_id', $user->id)
                    ->orWhere('destinatario_id', $user->id);
            });
        }

        abort(403, 'Acesso negado. Apenas responsáveis e professores podem acessar esta funcionalidade.');
    }

    /**
     * @return list<string>
     */
    public function linkedAlunoIds(User $user): array
    {
        $responsavelIds = Responsavel::query()
            ->where('usuario_id', $user->id)
            ->pluck('id')
            ->all();

        if ($responsavelIds === []) {
            return [];
        }

        $driver = DB::connection('shared')->getDriverName();
        $pivotTable = $driver === 'sqlite' ? 'aluno_responsavel' : 'escola.aluno_responsavel';

        return DB::connection('shared')
            ->table($pivotTable)
            ->whereIn('responsavel_id', $responsavelIds)
            ->pluck('aluno_id')
            ->unique()
            ->values()
            ->all();
    }

    public function userCanAccessConversa(User $user, string $conversaId): bool
    {
        $messages = Message::query()
            ->where(function ($query) use ($conversaId): void {
                $query->where('conversa_id', $conversaId)
                    ->orWhere('id', $conversaId);
            })
            ->get();

        if ($messages->isEmpty()) {
            return false;
        }

        return $messages->contains(fn (Message $message) => $this->userCanAccessMessage($user, $message));
    }

    public function userCanAccessMessage(User $user, Message $message): bool
    {
        if ($user->isResponsavel()) {
            return in_array($message->aluno_id, $this->linkedAlunoIds($user), true);
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

    public function isUnreadForUser(User $user, Message $message): bool
    {
        if ($message->lida) {
            return false;
        }

        if ($message->destinatario_id) {
            return $message->destinatario_id === $user->id;
        }

        if ($user->isResponsavel() && $message->remetente_id !== $user->id) {
            return true;
        }

        return false;
    }

    /**
     * @param  Collection<int, Message>  $messages
     * @return list<array{id: string, nome_completo: string|null, avatar_url: string|null, foto_url: string|null}>
     */
    protected function extractParticipantes(Collection $messages): array
    {
        $users = collect();

        foreach ($messages as $message) {
            if ($message->remetente) {
                $users->put($message->remetente->id, $message->remetente);
            }
            if ($message->destinatario) {
                $users->put($message->destinatario->id, $message->destinatario);
            }
        }

        return $users->map(fn ($participant) => [
            'id' => $participant->id,
            'nome_completo' => $participant->nome_completo,
            'avatar_url' => $participant->avatar_url,
            'foto_url' => $participant->avatar_url,
        ])->values()->all();
    }
}
