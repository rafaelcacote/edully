<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreMessageRequest;
use App\Http\Resources\Api\MessageResource;
use App\Models\Message;
use App\Models\Responsavel;
use App\Models\Turma;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessagesController extends Controller
{
    /**
     * Get messages for the authenticated user.
     * For responsaveis: returns messages for their students.
     * For teachers: returns messages they sent.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Message::query();

        // Se for responsável, buscar mensagens dos alunos vinculados
        if ($user->isResponsavel()) {
            $responsaveis = Responsavel::where('usuario_id', $user->id)->get();
            $responsavelIds = $responsaveis->pluck('id')->toArray();

            if (empty($responsavelIds)) {
                return response()->json([
                    'messages' => [],
                    'meta' => [
                        'current_page' => 1,
                        'last_page' => 1,
                        'per_page' => 15,
                        'total' => 0,
                    ],
                ]);
            }

            // Buscar alunos vinculados ao responsável
            $driver = DB::connection('shared')->getDriverName();
            $pivotTable = $driver === 'sqlite' ? 'aluno_responsavel' : 'escola.aluno_responsavel';

            $alunoIds = DB::connection('shared')
                ->table($pivotTable)
                ->whereIn('responsavel_id', $responsavelIds)
                ->pluck('aluno_id')
                ->unique()
                ->toArray();

            if (empty($alunoIds)) {
                return response()->json([
                    'messages' => [],
                    'meta' => [
                        'current_page' => 1,
                        'last_page' => 1,
                        'per_page' => 15,
                        'total' => 0,
                    ],
                ]);
            }

            $query->whereIn('aluno_id', $alunoIds);

            // Filtrar por aluno_id se fornecido
            if ($request->has('aluno_id')) {
                $alunoId = $request->input('aluno_id');
                if (in_array($alunoId, $alunoIds)) {
                    $query->where('aluno_id', $alunoId);
                } else {
                    return response()->json([
                        'message' => 'Aluno não encontrado ou você não tem permissão para acessar este aluno.',
                    ], 403);
                }
            }
        } elseif ($user->isTeacher()) {
            // Se for professor, buscar apenas mensagens que ele enviou
            $query->where('remetente_id', $user->id);
        } else {
            return response()->json([
                'message' => 'Acesso negado. Apenas responsáveis e professores podem acessar esta funcionalidade.',
            ], 403);
        }

        // Filtrar por lida/não lida
        if ($request->has('lida')) {
            $query->where('lida', filter_var($request->input('lida'), FILTER_VALIDATE_BOOLEAN));
        }

        // Ordenar por mais recente
        $messages = $query
            ->with(['aluno:id,nome,nome_social', 'remetente:id,nome_completo', 'turma:id,nome'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'messages' => MessageResource::collection($messages->items()),
            'meta' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
        ]);
    }

    /**
     * Get a specific message.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $message = Message::with(['aluno:id,nome,nome_social', 'remetente:id,nome_completo', 'turma:id,nome'])
            ->findOrFail($id);

        // Verificar permissão
        if ($user->isResponsavel()) {
            $responsaveis = Responsavel::where('usuario_id', $user->id)->get();
            $responsavelIds = $responsaveis->pluck('id')->toArray();

            $driver = DB::connection('shared')->getDriverName();
            $pivotTable = $driver === 'sqlite' ? 'aluno_responsavel' : 'escola.aluno_responsavel';

            $hasAccess = DB::connection('shared')
                ->table($pivotTable)
                ->whereIn('responsavel_id', $responsavelIds)
                ->where('aluno_id', $message->aluno_id)
                ->exists();

            if (! $hasAccess) {
                return response()->json([
                    'message' => 'Mensagem não encontrada ou você não tem permissão para acessá-la.',
                ], 403);
            }
        } elseif ($user->isTeacher()) {
            if ($message->remetente_id !== $user->id) {
                return response()->json([
                    'message' => 'Mensagem não encontrada ou você não tem permissão para acessá-la.',
                ], 403);
            }
        } else {
            return response()->json([
                'message' => 'Acesso negado. Apenas responsáveis e professores podem acessar esta funcionalidade.',
            ], 403);
        }

        return response()->json([
            'message' => new MessageResource($message),
        ]);
    }

    /**
     * Create a new message (only for teachers).
     */
    public function store(StoreMessageRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // Buscar tenant do professor
        $teacher = $user->teacher()->where('ativo', true)->first();
        if (! $teacher) {
            return response()->json([
                'message' => 'Acesso negado. Apenas professores podem criar mensagens.',
            ], 403);
        }

        $tenantId = $teacher->tenant_id;

        // Se turma_id foi enviado, criar mensagem para todos os alunos da turma
        if (isset($validated['turma_id'])) {
            $turma = Turma::where('id', $validated['turma_id'])
                ->where('tenant_id', $tenantId)
                ->firstOrFail();

            // Garantir que o tenant_id está definido no modelo para a relação funcionar
            $turma->setAttribute('tenant_id', $tenantId);
            $alunos = $turma->alunos()->get();

            if ($alunos->isEmpty()) {
                return response()->json([
                    'message' => 'Esta turma não possui alunos matriculados.',
                ], 422);
            }

            $messages = [];
            foreach ($alunos as $aluno) {
                $message = Message::create([
                    'tenant_id' => $tenantId,
                    'remetente_id' => $user->id,
                    'aluno_id' => $aluno->id,
                    'turma_id' => $validated['turma_id'],
                    'titulo' => $validated['titulo'],
                    'conteudo' => $validated['conteudo'],
                    'tipo' => $validated['tipo'] ?? 'outro',
                    'prioridade' => $validated['prioridade'] ?? 'normal',
                    'anexo_url' => $validated['anexo_url'] ?? null,
                    'lida' => false,
                ]);
                $messages[] = $message;
            }

            return response()->json([
                'message' => "Mensagem enviada para {$alunos->count()} aluno(s) da turma {$turma->nome}.",
                'messages' => MessageResource::collection($messages),
                'count' => count($messages),
            ], 201);
        }

        // Comportamento normal: mensagem para um aluno específico
        $message = Message::create([
            ...$validated,
            'tenant_id' => $tenantId,
            'remetente_id' => $user->id,
            'lida' => false,
        ]);

        $message->load(['aluno:id,nome,nome_social', 'remetente:id,nome_completo', 'turma:id,nome']);

        return response()->json([
            'message' => new MessageResource($message),
        ], 201);
    }

    /**
     * Mark a message as read (only for responsaveis).
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        if (! $user->isResponsavel()) {
            return response()->json([
                'message' => 'Acesso negado. Apenas responsáveis podem marcar mensagens como lidas.',
            ], 403);
        }

        $message = Message::findOrFail($id);

        // Verificar se o responsável tem acesso ao aluno
        $responsaveis = Responsavel::where('usuario_id', $user->id)->get();
        $responsavelIds = $responsaveis->pluck('id')->toArray();

        $driver = DB::connection('shared')->getDriverName();
        $pivotTable = $driver === 'sqlite' ? 'aluno_responsavel' : 'escola.aluno_responsavel';

        $hasAccess = DB::connection('shared')
            ->table($pivotTable)
            ->whereIn('responsavel_id', $responsavelIds)
            ->where('aluno_id', $message->aluno_id)
            ->exists();

        if (! $hasAccess) {
            return response()->json([
                'message' => 'Mensagem não encontrada ou você não tem permissão para acessá-la.',
            ], 403);
        }

        if (! $message->lida) {
            $message->update([
                'lida' => true,
                'lida_em' => now(),
            ]);
        }

        $message->load(['aluno:id,nome,nome_social', 'remetente:id,nome_completo', 'turma:id,nome']);

        return response()->json([
            'message' => new MessageResource($message),
        ]);
    }

    /**
     * Remove the specified message.
     * For teachers: can only delete messages they sent.
     * For responsaveis: can delete messages for their students.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $message = Message::findOrFail($id);

        // Verificar permissão
        if ($user->isTeacher()) {
            // Professores só podem deletar mensagens que enviaram
            if ($message->remetente_id !== $user->id) {
                return response()->json([
                    'message' => 'Mensagem não encontrada ou você não tem permissão para removê-la.',
                ], 403);
            }
        } elseif ($user->isResponsavel()) {
            // Responsáveis podem deletar mensagens dos alunos vinculados
            $responsaveis = Responsavel::where('usuario_id', $user->id)->get();
            $responsavelIds = $responsaveis->pluck('id')->toArray();

            $driver = DB::connection('shared')->getDriverName();
            $pivotTable = $driver === 'sqlite' ? 'aluno_responsavel' : 'escola.aluno_responsavel';

            $hasAccess = DB::connection('shared')
                ->table($pivotTable)
                ->whereIn('responsavel_id', $responsavelIds)
                ->where('aluno_id', $message->aluno_id)
                ->exists();

            if (! $hasAccess) {
                return response()->json([
                    'message' => 'Mensagem não encontrada ou você não tem permissão para removê-la.',
                ], 403);
            }
        } else {
            return response()->json([
                'message' => 'Acesso negado. Apenas professores e responsáveis podem remover mensagens.',
            ], 403);
        }

        $message->delete();

        return response()->json([
            'message' => 'Mensagem removida com sucesso.',
        ]);
    }
}
