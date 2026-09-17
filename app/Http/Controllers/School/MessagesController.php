<?php

namespace App\Http\Controllers\School;

use App\Actions\Api\NotifyMessagePushRecipients;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\StoreMessageRequest;
use App\Http\Requests\School\UpdateMessageRequest;
use App\Models\Message;
use App\Models\Teacher;
use App\Models\Turma;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MessagesController extends Controller
{
    /**
     * Get the current user's tenant.
     */
    protected function getTenant()
    {
        $user = auth()->user();
        $tenant = $user->tenants()->first();

        if (! $tenant) {
            abort(404, 'Escola não encontrada');
        }

        return $tenant;
    }

    /**
     * Get the current teacher from the authenticated user (if exists).
     * Returns null if user is not a teacher (e.g., Administrador Escola).
     */
    protected function getCurrentTeacher()
    {
        $user = auth()->user();
        $tenant = $this->getTenant();

        return Teacher::query()
            ->where('tenant_id', $tenant->id)
            ->where('usuario_id', $user->id)
            ->where('ativo', true)
            ->first();
    }

    /**
     * Display a listing of the messages.
     *
     * Recados enviados para a turma inteira são agrupados em uma linha
     * (uma por envio), exibindo o nome da turma em vez de cada aluno.
     */
    public function index(Request $request): Response
    {
        $tenant = $this->getTenant();
        $user = auth()->user();
        $filters = $request->only(['search', 'aluno_id', 'turma_id']);

        // Get teacher and turmas using many-to-many relationship
        $teacher = $this->getCurrentTeacher();

        $baseQuery = Message::query()
            ->where('tenant_id', $tenant->id)
            ->when($teacher, function ($query) use ($user) {
                // Se for professor, mostrar apenas mensagens que ele enviou
                $query->where('remetente_id', $user->id);
            })
            // Se for Administrador Escola, mostrar todas as mensagens do tenant (sem filtro adicional)
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $search = trim($search);
                $query->where(function ($q) use ($search) {
                    $q->where('titulo', 'ilike', "%{$search}%")
                        ->orWhere('conteudo', 'ilike', "%{$search}%")
                        ->orWhereHas('aluno', function ($subQuery) use ($search) {
                            $subQuery->where('nome', 'ilike', "%{$search}%")
                                ->orWhere('nome_social', 'ilike', "%{$search}%");
                        })
                        ->orWhereHas('turma', function ($subQuery) use ($search) {
                            $subQuery->where('nome', 'ilike', "%{$search}%");
                        });
                });
            })
            ->when($filters['aluno_id'] ?? null, function ($query, string $alunoId) {
                $query->where('aluno_id', $alunoId);
            })
            ->when($filters['turma_id'] ?? null, function ($query, string $turmaId) {
                $query->where('turma_id', $turmaId);
            });

        // Com filtro por aluno, listamos as linhas individuais (incluindo fan-out de turma).
        // Sem esse filtro, agrupamos envios para turma inteira em uma única linha.
        if (empty($filters['aluno_id'])) {
            $representativeIds = $this->groupedMessageRepresentativeIds(clone $baseQuery);

            $messagesQuery = Message::query()
                ->whereIn('id', $representativeIds)
                ->with(['aluno:id,nome,nome_social', 'turma:id,nome']);
        } else {
            $messagesQuery = (clone $baseQuery)
                ->with(['aluno:id,nome,nome_social', 'turma:id,nome']);
        }

        $messages = $messagesQuery
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString()
            ->through(function (Message $message) use ($filters) {
                $isTurmaSend = filled($message->turma_id);
                $collapseToTurma = $isTurmaSend && empty($filters['aluno_id']);

                return [
                    'id' => $message->id,
                    'titulo' => $message->titulo,
                    'destinatario_tipo' => $collapseToTurma ? 'turma' : 'aluno',
                    'aluno' => (! $collapseToTurma && $message->aluno)
                        ? [
                            'id' => $message->aluno->id,
                            'nome' => $message->aluno->nome,
                            'nome_social' => $message->aluno->nome_social,
                        ]
                        : null,
                    'turma' => ($isTurmaSend && $message->turma)
                        ? [
                            'id' => $message->turma->id,
                            'nome' => $message->turma->nome,
                        ]
                        : null,
                    'tipo' => $message->tipo,
                    'prioridade' => $message->prioridade,
                    'lida' => $message->lida,
                    'created_at' => $message->created_at->format('d/m/Y H:i'),
                ];
            });

        // Get turmas (qualify columns to avoid ambiguity with professor_turma pivot)
        $turmasTable = (new Turma)->getTable();
        if ($teacher) {
            // Se for professor, usar as turmas dele
            $turmas = $teacher->turmas()
                ->where($turmasTable.'.ativo', true)
                ->orderBy($turmasTable.'.nome')
                ->get()
                ->map(function ($turma) {
                    return [
                        'id' => $turma->id,
                        'nome' => $turma->nome,
                    ];
                })
                ->toArray();

            $turmaIds = collect($turmas)->pluck('id')->toArray();
        } else {
            // Administrador Escola: buscar todas as turmas do tenant
            $turmas = Turma::query()
                ->where('tenant_id', $tenant->id)
                ->where('ativo', true)
                ->orderBy('nome')
                ->get()
                ->map(function ($turma) {
                    return [
                        'id' => $turma->id,
                        'nome' => $turma->nome,
                    ];
                })
                ->toArray();

            $turmaIds = collect($turmas)->pluck('id')->toArray();
        }

        $driver = DB::connection('shared')->getDriverName();
        $pivotTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';
        $alunosTable = $driver === 'sqlite' ? 'alunos' : 'escola.alunos';

        $alunos = [];
        if (! empty($turmaIds)) {
            $alunos = DB::connection('shared')
                ->table($pivotTable.' as matriculas')
                ->join($alunosTable.' as alunos', 'alunos.id', '=', 'matriculas.aluno_id')
                ->where('matriculas.tenant_id', $tenant->id)
                ->where('matriculas.status', 'ativo')
                ->whereIn('matriculas.turma_id', $turmaIds)
                ->whereNull('alunos.deleted_at')
                ->select([
                    'alunos.id',
                    'alunos.nome',
                    'alunos.nome_social',
                ])
                ->distinct()
                ->orderBy('alunos.nome')
                ->get()
                ->map(function ($aluno) {
                    return [
                        'id' => $aluno->id,
                        'nome' => $aluno->nome,
                    ];
                })
                ->toArray();
        }

        return Inertia::render('school/messages/Index', [
            'messages' => $messages,
            'alunos' => $alunos,
            'turmas' => $turmas,
            'filters' => $filters,
        ]);
    }

    /**
     * IDs representativos para a listagem: uma linha por envio à turma
     * e todas as linhas de recados individuais (sem turma_id).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Message>  $query
     * @return \Illuminate\Support\Collection<int, string>
     */
    protected function groupedMessageRepresentativeIds($query)
    {
        $table = (new Message)->getTable();
        $driver = DB::connection('shared')->getDriverName();

        if ($driver === 'sqlite') {
            $groupKey = "CASE WHEN {$table}.turma_id IS NULL THEN {$table}.id ELSE ({$table}.turma_id || '|' || {$table}.remetente_id || '|' || {$table}.titulo || '|' || {$table}.conteudo || '|' || strftime('%Y-%m-%d %H:%M', {$table}.created_at)) END";
            $minId = "MIN({$table}.id)";
        } else {
            // Postgres: UUID não tem MIN(); agregamos em text.
            $groupKey = "CASE WHEN {$table}.turma_id IS NULL THEN {$table}.id::text ELSE ({$table}.turma_id::text || '|' || {$table}.remetente_id::text || '|' || {$table}.titulo || '|' || {$table}.conteudo || '|' || to_char({$table}.created_at, 'YYYY-MM-DD HH24:MI')) END";
            $minId = "MIN({$table}.id::text)";
        }

        return $query
            ->selectRaw("{$minId} as id")
            ->groupByRaw($groupKey)
            ->pluck('id');
    }

    /**
     * Show the form for creating a new message.
     */
    public function create(): Response
    {
        $tenant = $this->getTenant();
        $teacher = $this->getCurrentTeacher();

        // Get turmas (qualify columns to avoid ambiguity with professor_turma pivot)
        $turmasTable = (new Turma)->getTable();
        if ($teacher) {
            // Se for professor, usar as turmas dele
            $turmas = $teacher->turmas()
                ->where($turmasTable.'.ativo', true)
                ->orderBy($turmasTable.'.nome')
                ->get()
                ->map(function ($turma) {
                    return [
                        'id' => $turma->id,
                        'nome' => $turma->nome,
                    ];
                });
        } else {
            // Administrador Escola: buscar todas as turmas do tenant
            $turmas = Turma::query()
                ->where('tenant_id', $tenant->id)
                ->where('ativo', true)
                ->orderBy('nome')
                ->get()
                ->map(function ($turma) {
                    return [
                        'id' => $turma->id,
                        'nome' => $turma->nome,
                    ];
                });
        }

        $turmaIds = $turmas->pluck('id')->toArray();

        // Get students from teacher's turmas
        $driver = DB::connection('shared')->getDriverName();
        $pivotTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';
        $alunosTable = $driver === 'sqlite' ? 'alunos' : 'escola.alunos';

        $alunos = [];
        if (! empty($turmaIds)) {
            $alunos = DB::connection('shared')
                ->table($pivotTable.' as matriculas')
                ->join($alunosTable.' as alunos', 'alunos.id', '=', 'matriculas.aluno_id')
                ->where('matriculas.tenant_id', $tenant->id)
                ->where('matriculas.status', 'ativo')
                ->whereIn('matriculas.turma_id', $turmaIds)
                ->whereNull('alunos.deleted_at')
                ->select([
                    'alunos.id',
                    'alunos.nome',
                    'alunos.nome_social',
                ])
                ->distinct()
                ->orderBy('alunos.nome')
                ->get()
                ->map(function ($aluno) {
                    return [
                        'id' => $aluno->id,
                        'nome' => $aluno->nome,
                    ];
                })
                ->toArray();
        }

        return Inertia::render('school/messages/Create', [
            'alunos' => $alunos,
            'turmas' => $turmas,
        ]);
    }

    /**
     * Store a newly created message.
     */
    public function store(StoreMessageRequest $request): RedirectResponse
    {
        $tenant = $this->getTenant();
        $user = auth()->user();
        $validated = $request->validated();
        $anexoUrl = $this->resolveMessageAnexoUrl($request);

        // Se turma_id foi enviado, criar mensagem para todos os alunos da turma
        if (isset($validated['turma_id'])) {
            $turma = Turma::where('id', $validated['turma_id'])
                ->where('tenant_id', $tenant->id)
                ->firstOrFail();

            $alunos = $turma->alunos()->get();

            if ($alunos->isEmpty()) {
                return redirect()
                    ->back()
                    ->withErrors(['turma_id' => 'Esta turma não possui alunos matriculados.']);
            }

            $notify = app(NotifyMessagePushRecipients::class);

            foreach ($alunos as $aluno) {
                $created = Message::create([
                    'tenant_id' => $tenant->id,
                    'remetente_id' => $user->id,
                    'aluno_id' => $aluno->id,
                    'turma_id' => $validated['turma_id'],
                    // Fan-out por turma: cada aluno fica com conversa própria (reply 1:1 no app).
                    'conversa_id' => (string) Str::uuid(),
                    'titulo' => $validated['titulo'],
                    'conteudo' => $validated['conteudo'],
                    'tipo' => $validated['tipo'] ?? 'outro',
                    'prioridade' => $validated['prioridade'] ?? 'normal',
                    'anexo_url' => $anexoUrl,
                    'lida' => false,
                ]);
                $notify->queue($created);
            }

            return redirect()
                ->route('school.messages.index')
                ->with('toast', [
                    'type' => 'success',
                    'title' => 'Recados enviados',
                    'message' => "Recado enviado para {$alunos->count()} aluno(s) da turma {$turma->nome}.",
                ]);
        }

        // Comportamento normal: mensagem para um aluno específico
        $message = Message::create([
            'tenant_id' => $tenant->id,
            'remetente_id' => $user->id,
            'aluno_id' => $validated['aluno_id'],
            'conversa_id' => (string) Str::uuid(),
            'titulo' => $validated['titulo'],
            'conteudo' => $validated['conteudo'],
            'tipo' => $validated['tipo'] ?? 'outro',
            'prioridade' => $validated['prioridade'] ?? 'normal',
            'anexo_url' => $anexoUrl,
            'lida' => false,
        ]);

        app(NotifyMessagePushRecipients::class)->queue($message);

        return redirect()
            ->route('school.messages.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Recado criado',
                'message' => 'O recado foi enviado com sucesso.',
            ]);
    }

    /**
     * Display the specified message.
     */
    public function show(Message $message): Response
    {
        $tenant = $this->getTenant();
        $user = auth()->user();
        $teacher = $this->getCurrentTeacher();

        // Verificar se a mensagem pertence ao tenant
        if ($message->tenant_id !== $tenant->id) {
            abort(404);
        }

        // Se for professor, verificar se a mensagem é dele
        if ($teacher && $message->remetente_id !== $user->id) {
            abort(404);
        }

        $message->load(['aluno:id,nome,nome_social', 'remetente:id,nome_completo']);

        return Inertia::render('school/messages/Show', [
            'message' => [
                'id' => $message->id,
                'titulo' => $message->titulo,
                'conteudo' => $message->conteudo,
                'tipo' => $message->tipo,
                'prioridade' => $message->prioridade,
                'anexo_url' => $message->anexo_url,
                'lida' => $message->lida,
                'lida_em' => $message->lida_em?->format('d/m/Y H:i'),
                'created_at' => $message->created_at->format('d/m/Y H:i'),
                'aluno' => $message->aluno
                    ? [
                        'id' => $message->aluno->id,
                        'nome' => $message->aluno->nome,
                        'nome_social' => $message->aluno->nome_social,
                    ]
                    : null,
                'remetente' => $message->remetente
                    ? [
                        'id' => $message->remetente->id,
                        'nome_completo' => $message->remetente->nome_completo,
                    ]
                    : null,
            ],
        ]);
    }

    /**
     * Show the form for editing the specified message.
     */
    public function edit(Message $message): Response
    {
        $tenant = $this->getTenant();
        $user = auth()->user();
        $teacher = $this->getCurrentTeacher();

        // Verificar se a mensagem pertence ao tenant
        if ($message->tenant_id !== $tenant->id) {
            abort(404);
        }

        // Se for professor, verificar se a mensagem é dele
        if ($teacher && $message->remetente_id !== $user->id) {
            abort(404);
        }

        // Get turmas (qualify columns to avoid ambiguity with professor_turma pivot)
        $turmasTable = (new Turma)->getTable();
        if ($teacher) {
            $turmaIds = $teacher->turmas()
                ->where($turmasTable.'.ativo', true)
                ->pluck($turmasTable.'.id')
                ->toArray();
        } else {
            // Administrador Escola: buscar todas as turmas do tenant
            $turmaIds = Turma::query()
                ->where('tenant_id', $tenant->id)
                ->where('ativo', true)
                ->pluck('id')
                ->toArray();
        }

        // Get students from teacher's turmas
        $driver = DB::connection('shared')->getDriverName();
        $pivotTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';
        $alunosTable = $driver === 'sqlite' ? 'alunos' : 'escola.alunos';

        $alunos = [];
        if (! empty($turmaIds)) {
            $alunos = DB::connection('shared')
                ->table($pivotTable.' as matriculas')
                ->join($alunosTable.' as alunos', 'alunos.id', '=', 'matriculas.aluno_id')
                ->where('matriculas.tenant_id', $tenant->id)
                ->where('matriculas.status', 'ativo')
                ->whereIn('matriculas.turma_id', $turmaIds)
                ->whereNull('alunos.deleted_at')
                ->select([
                    'alunos.id',
                    'alunos.nome',
                    'alunos.nome_social',
                ])
                ->distinct()
                ->orderBy('alunos.nome')
                ->get()
                ->map(function ($aluno) {
                    return [
                        'id' => $aluno->id,
                        'nome' => $aluno->nome,
                    ];
                })
                ->toArray();
        }

        return Inertia::render('school/messages/Edit', [
            'message' => [
                'id' => $message->id,
                'aluno_id' => $message->aluno_id,
                'titulo' => $message->titulo,
                'conteudo' => $message->conteudo,
                'tipo' => $message->tipo,
                'prioridade' => $message->prioridade,
                'anexo_url' => $message->anexo_url,
            ],
            'alunos' => $alunos,
        ]);
    }

    /**
     * Update the specified message.
     */
    public function update(UpdateMessageRequest $request, Message $message): RedirectResponse
    {
        $tenant = $this->getTenant();
        $user = auth()->user();
        $teacher = $this->getCurrentTeacher();

        // Verificar se a mensagem pertence ao tenant
        if ($message->tenant_id !== $tenant->id) {
            abort(404);
        }

        // Se for professor, verificar se a mensagem é dele
        if ($teacher && $message->remetente_id !== $user->id) {
            abort(404);
        }

        $validated = $request->validated();
        $anexoUrl = $this->resolveMessageAnexoUrl($request, $message->anexo_url);

        $message->update([
            'aluno_id' => $validated['aluno_id'],
            'titulo' => $validated['titulo'],
            'conteudo' => $validated['conteudo'],
            'tipo' => $validated['tipo'] ?? $message->tipo,
            'prioridade' => $validated['prioridade'] ?? $message->prioridade,
            'anexo_url' => $anexoUrl,
        ]);

        return redirect()
            ->route('school.messages.edit', $message)
            ->with('toast', [
                'type' => 'success',
                'title' => 'Recado atualizado',
                'message' => 'As alterações foram salvas com sucesso.',
            ]);
    }

    /**
     * Resolve anexo_url from uploaded file or keep the current value.
     */
    protected function resolveMessageAnexoUrl(Request $request, ?string $currentUrl = null): ?string
    {
        if ($request->hasFile('anexo')) {
            $this->deleteStoredMessageAnexo($currentUrl);

            return $this->storeMessageAnexo($request->file('anexo'));
        }

        if ($request->exists('anexo_url') && blank($request->input('anexo_url'))) {
            $this->deleteStoredMessageAnexo($currentUrl);

            return null;
        }

        $validatedUrl = $request->validated('anexo_url') ?? null;

        return filled($validatedUrl) ? $validatedUrl : $currentUrl;
    }

    protected function storeMessageAnexo(UploadedFile $anexo): string
    {
        $anexoPath = $anexo->store('mensagens/anexos', 'public');

        return asset('storage/'.$anexoPath);
    }

    protected function deleteStoredMessageAnexo(?string $anexoUrl): void
    {
        if (! $anexoUrl) {
            return;
        }

        $storageBaseUrl = asset('storage/');
        if (! str_starts_with($anexoUrl, $storageBaseUrl)) {
            return;
        }

        $relativePath = str_replace($storageBaseUrl, '', $anexoUrl);
        if ($relativePath !== '' && Storage::disk('public')->exists($relativePath)) {
            Storage::disk('public')->delete($relativePath);
        }
    }

    /**
     * Remove the specified message.
     */
    public function destroy(Message $message): RedirectResponse
    {
        $tenant = $this->getTenant();
        $user = auth()->user();
        $teacher = $this->getCurrentTeacher();

        // Verificar se a mensagem pertence ao tenant
        if ($message->tenant_id !== $tenant->id) {
            abort(404);
        }

        // Se for professor, verificar se a mensagem é dele
        if ($teacher && $message->remetente_id !== $user->id) {
            abort(404);
        }

        $message->delete();

        return redirect()
            ->route('school.messages.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Recado excluído',
                'message' => 'O recado foi removido com sucesso.',
            ]);
    }
}
