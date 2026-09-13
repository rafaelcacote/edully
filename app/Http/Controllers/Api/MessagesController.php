<?php

namespace App\Http\Controllers\Api;

use App\Actions\Api\ListConversationsAction;
use App\Actions\Api\NotifyMessagePushRecipients;
use App\Actions\Api\ResolveMessageThreadAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreMessageRequest;
use App\Http\Resources\Api\ConversaResource;
use App\Http\Resources\Api\MessageResource;
use App\Models\Message;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MessagesController extends Controller
{
    /**
     * List conversations for the authenticated user.
     * Also returns `messages` as the last message of each conversation for Expo compatibility.
     */
    public function index(Request $request, ListConversationsAction $listConversations): JsonResponse
    {
        $user = $request->user();
        $alunoId = $request->input('aluno_id');
        $lida = $request->has('lida')
            ? filter_var($request->input('lida'), FILTER_VALIDATE_BOOLEAN)
            : null;

        $paginator = $listConversations->execute($user, $alunoId, $lida);

        $conversas = ConversaResource::collection($paginator->items());

        return response()->json([
            'conversas' => $conversas,
            // Compatibilidade: app antigo lê `messages` como lista plana.
            'messages' => $conversas,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Get ordered history for a conversation thread.
     */
    public function showConversa(
        Request $request,
        string $conversaId,
        ListConversationsAction $listConversations,
    ): JsonResponse {
        $user = $request->user();

        if (! $listConversations->userCanAccessConversa($user, $conversaId)) {
            return response()->json([
                'message' => 'Conversa não encontrada ou você não tem permissão para acessá-la.',
            ], 403);
        }

        $messages = Message::query()
            ->where(function ($query) use ($conversaId): void {
                $query->where('conversa_id', $conversaId)
                    ->orWhere('id', $conversaId);
            })
            ->with($this->messageRelations())
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        if ($messages->isEmpty()) {
            return response()->json([
                'message' => 'Conversa não encontrada.',
            ], 404);
        }

        $resolvedConversaId = $messages->firstWhere('conversa_id', '!=', null)?->conversa_id
            ?? $messages->first()->conversa_id
            ?? $conversaId;

        return response()->json([
            'conversa_id' => $resolvedConversaId,
            'messages' => MessageResource::collection($messages),
        ]);
    }

    /**
     * Get a specific message.
     */
    public function show(Request $request, string $id, ListConversationsAction $listConversations): JsonResponse
    {
        $user = $request->user();
        $message = Message::with($this->messageRelations())->findOrFail($id);

        if (! $listConversations->userCanAccessMessage($user, $message)) {
            return response()->json([
                'message' => 'Recado não encontrado ou você não tem permissão para acessá-lo.',
            ], 403);
        }

        return response()->json([
            'message' => new MessageResource($message),
        ]);
    }

    /**
     * Create a new message or reply into an existing conversation.
     */
    public function store(
        StoreMessageRequest $request,
        ResolveMessageThreadAction $resolveMessageThread,
    ): JsonResponse {
        $user = $request->user();
        $validated = $request->validated();

        if ($request->isReply()) {
            return $this->storeReply($user, $validated, $resolveMessageThread);
        }

        if ($user->isResponsavel()) {
            return $this->storeFromResponsavel($user, $validated);
        }

        return $this->storeFromTeacher($user, $validated);
    }

    /**
     * Mark a message as read.
     */
    public function markAsRead(Request $request, string $id, ListConversationsAction $listConversations): JsonResponse
    {
        $user = $request->user();
        $message = Message::findOrFail($id);

        if (! $this->userCanMarkAsRead($user, $message, $listConversations)) {
            return response()->json([
                'message' => 'Recado não encontrado ou você não tem permissão para marcá-lo como lido.',
            ], 403);
        }

        if (! $message->lida) {
            $message->update([
                'lida' => true,
                'lida_em' => now(),
            ]);
        }

        $message->load($this->messageRelations());

        return response()->json([
            'message' => new MessageResource($message),
        ]);
    }

    /**
     * Mark all unread messages in a conversation as read for the current user.
     */
    public function markConversaAsRead(
        Request $request,
        string $conversaId,
        ListConversationsAction $listConversations,
    ): JsonResponse {
        $user = $request->user();

        if (! $listConversations->userCanAccessConversa($user, $conversaId)) {
            return response()->json([
                'message' => 'Conversa não encontrada ou você não tem permissão para acessá-la.',
            ], 403);
        }

        $messages = Message::query()
            ->where(function ($query) use ($conversaId): void {
                $query->where('conversa_id', $conversaId)
                    ->orWhere('id', $conversaId);
            })
            ->get();

        $updated = 0;
        foreach ($messages as $message) {
            if ($listConversations->isUnreadForUser($user, $message)) {
                $message->update([
                    'lida' => true,
                    'lida_em' => now(),
                ]);
                $updated++;
            }
        }

        return response()->json([
            'message' => 'Conversa marcada como lida.',
            'updated' => $updated,
        ]);
    }

    /**
     * Remove the specified message.
     */
    public function destroy(Request $request, string $id, ListConversationsAction $listConversations): JsonResponse
    {
        $user = $request->user();
        $message = Message::findOrFail($id);

        if (! $listConversations->userCanAccessMessage($user, $message)) {
            return response()->json([
                'message' => 'Recado não encontrado ou você não tem permissão para removê-lo.',
            ], 403);
        }

        $message->delete();

        return response()->json([
            'message' => 'Recado removido com sucesso.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function storeReply(
        User $user,
        array $validated,
        ResolveMessageThreadAction $resolveMessageThread,
    ): JsonResponse {
        $thread = $resolveMessageThread->execute(
            $user,
            $validated['mensagem_pai_id'] ?? null,
            $validated['conversa_id'] ?? null,
        );

        if (! $thread['destinatario_id']) {
            return response()->json([
                'message' => 'Não foi possível determinar o destinatário da resposta.',
            ], 422);
        }

        $titulo = $validated['titulo'] ?? null;
        if (! $titulo) {
            $baseTitle = $thread['titulo'] ?? 'Mensagem';
            $titulo = str_starts_with(mb_strtolower($baseTitle), 're:')
                ? $baseTitle
                : 'Re: '.$baseTitle;
        }

        $message = Message::create([
            'tenant_id' => $thread['tenant_id'],
            'remetente_id' => $user->id,
            'destinatario_id' => $thread['destinatario_id'],
            'aluno_id' => $thread['aluno_id'],
            'conversa_id' => $thread['conversa_id'],
            'mensagem_pai_id' => $thread['mensagem_pai_id'],
            'titulo' => $titulo,
            'conteudo' => $validated['conteudo'],
            'tipo' => $validated['tipo'] ?? 'outro',
            'prioridade' => $validated['prioridade'] ?? 'normal',
            'anexo_url' => $validated['anexo_url'] ?? null,
            'lida' => false,
        ]);

        $this->queueMessagePush($message);

        $message->load($this->messageRelations());

        return response()->json([
            'message' => new MessageResource($message),
        ], 201);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function storeFromResponsavel(User $user, array $validated): JsonResponse
    {
        $aluno = Student::query()
            ->where('id', $validated['aluno_id'])
            ->where('ativo', true)
            ->firstOrFail();
        $teacher = Teacher::query()
            ->where('id', $validated['professor_id'])
            ->where('ativo', true)
            ->firstOrFail();

        if (! $teacher->usuario_id) {
            return response()->json([
                'message' => 'Este professor não possui usuário vinculado para receber mensagens.',
            ], 422);
        }

        $message = Message::create([
            'tenant_id' => $aluno->tenant_id,
            'remetente_id' => $user->id,
            'destinatario_id' => $teacher->usuario_id,
            'aluno_id' => $aluno->id,
            'conversa_id' => (string) Str::uuid(),
            'titulo' => $validated['titulo'],
            'conteudo' => $validated['conteudo'],
            'tipo' => $validated['tipo'] ?? 'outro',
            'prioridade' => $validated['prioridade'] ?? 'normal',
            'anexo_url' => $validated['anexo_url'] ?? null,
            'lida' => false,
        ]);

        $this->queueMessagePush($message);

        $message->load($this->messageRelations());

        return response()->json([
            'message' => new MessageResource($message),
        ], 201);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function storeFromTeacher(User $user, array $validated): JsonResponse
    {
        $teacher = $user->teacher()->where('ativo', true)->first();
        if (! $teacher) {
            return response()->json([
                'message' => 'Acesso negado. Apenas professores podem criar recados.',
            ], 403);
        }

        $tenantId = $teacher->tenant_id;

        if (isset($validated['turma_id'])) {
            $turma = Turma::where('id', $validated['turma_id'])
                ->where('tenant_id', $tenantId)
                ->firstOrFail();

            $turma->setAttribute('tenant_id', $tenantId);
            $alunos = $turma->alunos()->get();

            if ($alunos->isEmpty()) {
                return response()->json([
                    'message' => 'Esta turma não possui alunos matriculados.',
                ], 422);
            }

            $messages = [];
            foreach ($alunos as $aluno) {
                $created = Message::create([
                    'tenant_id' => $tenantId,
                    'remetente_id' => $user->id,
                    'aluno_id' => $aluno->id,
                    'turma_id' => $validated['turma_id'],
                    // Turma fan-out: cada aluno fica com conversa própria (reply 1:1 depois).
                    'conversa_id' => (string) Str::uuid(),
                    'titulo' => $validated['titulo'],
                    'conteudo' => $validated['conteudo'],
                    'tipo' => $validated['tipo'] ?? 'outro',
                    'prioridade' => $validated['prioridade'] ?? 'normal',
                    'anexo_url' => $validated['anexo_url'] ?? null,
                    'lida' => false,
                ]);
                $messages[] = $created;
                $this->queueMessagePush($created);
            }

            return response()->json([
                'message' => "Recado enviado para {$alunos->count()} aluno(s) da turma {$turma->nome}.",
                'messages' => MessageResource::collection($messages),
                'count' => count($messages),
            ], 201);
        }

        $message = Message::create([
            'tenant_id' => $tenantId,
            'remetente_id' => $user->id,
            'aluno_id' => $validated['aluno_id'],
            'conversa_id' => (string) Str::uuid(),
            'titulo' => $validated['titulo'],
            'conteudo' => $validated['conteudo'],
            'tipo' => $validated['tipo'] ?? 'outro',
            'prioridade' => $validated['prioridade'] ?? 'normal',
            'anexo_url' => $validated['anexo_url'] ?? null,
            'lida' => false,
        ]);

        $this->queueMessagePush($message);

        $message->load($this->messageRelations());

        return response()->json([
            'message' => new MessageResource($message),
        ], 201);
    }

    protected function queueMessagePush(Message $message): void
    {
        $messageId = $message->id;

        $send = function () use ($messageId): void {
            $fresh = Message::query()->find($messageId);
            if (! $fresh) {
                return;
            }

            app(NotifyMessagePushRecipients::class)->execute($fresh);
        };

        // Em testes, dispara na hora (afterResponse + terminate é frágil no Pest).
        if (app()->runningUnitTests()) {
            $send();

            return;
        }

        dispatch($send)->afterResponse();
    }

    /**
     * @return list<string>
     */
    protected function messageRelations(): array
    {
        return [
            'aluno:id,nome,nome_social',
            'remetente:id,nome_completo,avatar_url',
            'destinatario:id,nome_completo,avatar_url',
            'turma:id,nome',
        ];
    }

    protected function userCanMarkAsRead(User $user, Message $message, ListConversationsAction $listConversations): bool
    {
        if ($message->destinatario_id) {
            return $message->destinatario_id === $user->id;
        }

        if ($user->isResponsavel()) {
            return in_array($message->aluno_id, $listConversations->linkedAlunoIds($user), true);
        }

        return false;
    }
}
