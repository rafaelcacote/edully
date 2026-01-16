<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Http\Requests\School\StoreMessageRequest;
use App\Http\Requests\School\UpdateMessageRequest;
use App\Models\Message;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
     * Get the current teacher from the authenticated user.
     */
    protected function getCurrentTeacher()
    {
        $user = auth()->user();
        $tenant = $this->getTenant();

        $teacher = Teacher::query()
            ->where('tenant_id', $tenant->id)
            ->where('usuario_id', $user->id)
            ->where('ativo', true)
            ->first();

        if (! $teacher) {
            abort(403, 'Acesso negado. Você precisa ser um professor para acessar esta área.');
        }

        return $teacher;
    }

    /**
     * Display a listing of the messages.
     */
    public function index(Request $request): Response
    {
        $tenant = $this->getTenant();
        $user = auth()->user();
        $filters = $request->only(['search', 'aluno_id']);

        $messages = Message::query()
            ->where('tenant_id', $tenant->id)
            ->where('remetente_id', $user->id)
            ->with(['aluno:id,nome,nome_social'])
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $search = trim($search);
                $query->where(function ($q) use ($search) {
                    $q->where('titulo', 'ilike', "%{$search}%")
                        ->orWhere('conteudo', 'ilike', "%{$search}%")
                        ->orWhereHas('aluno', function ($subQuery) use ($search) {
                            $subQuery->where('nome', 'ilike', "%{$search}%")
                                ->orWhere('nome_social', 'ilike', "%{$search}%");
                        });
                });
            })
            ->when($filters['aluno_id'] ?? null, function ($query, string $alunoId) {
                $query->where('aluno_id', $alunoId);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString()
            ->through(function (Message $message) {
                return [
                    'id' => $message->id,
                    'titulo' => $message->titulo,
                    'aluno' => $message->aluno
                        ? [
                            'id' => $message->aluno->id,
                            'nome' => $message->aluno->nome,
                            'nome_social' => $message->aluno->nome_social,
                        ]
                        : null,
                    'tipo' => $message->tipo,
                    'prioridade' => $message->prioridade,
                    'lida' => $message->lida,
                    'created_at' => $message->created_at->format('d/m/Y H:i'),
                ];
            });

        // Get students from teacher's turmas
        $teacher = $this->getCurrentTeacher();
        $driver = DB::connection('shared')->getDriverName();
        $pivotTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';
        $alunosTable = $driver === 'sqlite' ? 'alunos' : 'escola.alunos';
        $turmasTable = $driver === 'sqlite' ? 'turmas' : 'escola.turmas';

        $turmaIds = DB::connection('shared')
            ->table($turmasTable)
            ->where('tenant_id', $tenant->id)
            ->where('professor_id', $teacher->id)
            ->where('ativo', true)
            ->pluck('id')
            ->toArray();

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
                        'nome' => $aluno->nome_social ?? $aluno->nome,
                    ];
                })
                ->toArray();
        }

        return Inertia::render('school/messages/Index', [
            'messages' => $messages,
            'alunos' => $alunos,
            'filters' => $filters,
        ]);
    }

    /**
     * Show the form for creating a new message.
     */
    public function create(): Response
    {
        $tenant = $this->getTenant();
        $teacher = $this->getCurrentTeacher();

        // Get students from teacher's turmas
        $driver = DB::connection('shared')->getDriverName();
        $pivotTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';
        $alunosTable = $driver === 'sqlite' ? 'alunos' : 'escola.alunos';
        $turmasTable = $driver === 'sqlite' ? 'turmas' : 'escola.turmas';

        $turmaIds = DB::connection('shared')
            ->table($turmasTable)
            ->where('tenant_id', $tenant->id)
            ->where('professor_id', $teacher->id)
            ->where('ativo', true)
            ->pluck('id')
            ->toArray();

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
                        'nome' => $aluno->nome_social ?? $aluno->nome,
                    ];
                })
                ->toArray();
        }

        return Inertia::render('school/messages/Create', [
            'alunos' => $alunos,
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

        Message::create([
            ...$validated,
            'tenant_id' => $tenant->id,
            'remetente_id' => $user->id,
        ]);

        return redirect()
            ->route('school.messages.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Mensagem criada',
                'message' => 'A mensagem foi enviada com sucesso.',
            ]);
    }

    /**
     * Display the specified message.
     */
    public function show(Message $message): Response
    {
        $tenant = $this->getTenant();
        $user = auth()->user();

        if ($message->tenant_id !== $tenant->id || $message->remetente_id !== $user->id) {
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

        if ($message->tenant_id !== $tenant->id || $message->remetente_id !== $user->id) {
            abort(404);
        }

        $teacher = $this->getCurrentTeacher();

        // Get students from teacher's turmas
        $driver = DB::connection('shared')->getDriverName();
        $pivotTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';
        $alunosTable = $driver === 'sqlite' ? 'alunos' : 'escola.alunos';
        $turmasTable = $driver === 'sqlite' ? 'turmas' : 'escola.turmas';

        $turmaIds = DB::connection('shared')
            ->table($turmasTable)
            ->where('tenant_id', $tenant->id)
            ->where('professor_id', $teacher->id)
            ->where('ativo', true)
            ->pluck('id')
            ->toArray();

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
                        'nome' => $aluno->nome_social ?? $aluno->nome,
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

        if ($message->tenant_id !== $tenant->id || $message->remetente_id !== $user->id) {
            abort(404);
        }

        $validated = $request->validated();

        $message->update($validated);

        return redirect()
            ->route('school.messages.edit', $message)
            ->with('toast', [
                'type' => 'success',
                'title' => 'Mensagem atualizada',
                'message' => 'As alterações foram salvas com sucesso.',
            ]);
    }

    /**
     * Remove the specified message.
     */
    public function destroy(Message $message): RedirectResponse
    {
        $tenant = $this->getTenant();
        $user = auth()->user();

        if ($message->tenant_id !== $tenant->id || $message->remetente_id !== $user->id) {
            abort(404);
        }

        $message->delete();

        return redirect()
            ->route('school.messages.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Mensagem excluída',
                'message' => 'A mensagem foi removida com sucesso.',
            ]);
    }
}
