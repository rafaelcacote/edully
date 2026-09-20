<?php

namespace App\Http\Controllers\School;

use App\Actions\Api\ListStudentTeachersAction;
use App\Actions\Api\NotifyDocumentoPushRecipients;
use App\Actions\School\NotifyAtestadoTeachersAction;
use App\Enums\CategoriaDeclaracao;
use App\Enums\StatusDocumento;
use App\Enums\TipoDocumento;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\NotifyAtestadoTeachersRequest;
use App\Http\Requests\School\StoreDocumentoRequest;
use App\Http\Requests\School\UpdateDocumentoStatusRequest;
use App\Models\Documento;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Turma;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DocumentosController extends Controller
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
     * Display a listing of documents for the school.
     */
    public function index(Request $request): Response
    {
        $tenant = $this->getTenant();
        $filters = $request->only(['search', 'tipo', 'status']);
        $likeOperator = $this->likeOperator();

        $request->session()->put(
            'documentos_atencao_seen_at',
            now()->toDateTimeString()
        );

        $documentos = Documento::query()
            ->where('tenant_id', $tenant->id)
            ->with(['aluno:id,nome,nome_social'])
            ->when($filters['search'] ?? null, function ($query, string $search) use ($likeOperator) {
                $search = trim($search);
                $query->where(function ($q) use ($search, $likeOperator) {
                    $q->where('titulo', $likeOperator, "%{$search}%")
                        ->orWhere('descricao', $likeOperator, "%{$search}%")
                        ->orWhereHas('aluno', function ($subQuery) use ($search, $likeOperator) {
                            $subQuery->where('nome', $likeOperator, "%{$search}%")
                                ->orWhere('nome_social', $likeOperator, "%{$search}%");
                        });
                });
            })
            ->when($filters['tipo'] ?? null, function ($query, string $tipo) {
                $query->where('tipo', $tipo);
            })
            ->when($filters['status'] ?? null, function ($query, string $status) {
                $query->where('status', $status);
            })
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString()
            ->through(function (Documento $documento) {
                return [
                    'id' => $documento->id,
                    'titulo' => $documento->titulo,
                    'tipo' => $documento->tipo?->value ?? $documento->tipo,
                    'tipo_label' => $documento->tipo?->label() ?? (string) $documento->tipo,
                    'status' => $documento->status?->value ?? $documento->status,
                    'status_label' => $documento->status?->label() ?? (string) $documento->status,
                    'precisa_atencao' => $documento->needsSchoolAttention(),
                    'aluno' => $documento->aluno ? [
                        'id' => $documento->aluno->id,
                        'nome' => $documento->aluno->nome_social ?: $documento->aluno->nome,
                    ] : null,
                    'data_inicio' => $documento->data_inicio?->format('Y-m-d'),
                    'data_fim' => $documento->data_fim?->format('Y-m-d'),
                    'anexo_url' => $documento->anexo_url,
                    'created_at' => $documento->created_at?->format('d/m/Y H:i'),
                ];
            });

        return Inertia::render('school/documentos/Index', [
            'documentos' => $documentos,
            'filters' => $filters,
            'tipos' => collect(TipoDocumento::cases())->map(fn (TipoDocumento $tipo) => [
                'value' => $tipo->value,
                'label' => $tipo->label(),
            ]),
            'statuses' => collect(StatusDocumento::cases())->map(fn (StatusDocumento $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ]),
        ]);
    }

    /**
     * Show the form for sending a document from school to parent.
     */
    public function create(): Response
    {
        $tenant = $this->getTenant();

        $students = Student::query()
            ->where('tenant_id', $tenant->id)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome', 'nome_social']);

        $turmasPorAluno = $this->turmasAtivasPorAluno(
            $tenant->id,
            $students->pluck('id')->all()
        );

        $alunos = $students->map(function (Student $aluno) use ($turmasPorAluno) {
            $turmaNome = $turmasPorAluno[$aluno->id] ?? null;

            return [
                'id' => $aluno->id,
                'nome' => $aluno->nome_social ?: $aluno->nome,
                'turma' => $turmaNome,
            ];
        });

        return Inertia::render('school/documentos/Create', [
            'alunos' => $alunos,
            'categorias' => collect(CategoriaDeclaracao::cases())->map(fn (CategoriaDeclaracao $categoria) => [
                'value' => $categoria->value,
                'label' => $categoria->label(),
            ]),
        ]);
    }

    /**
     * Store a documento_escola sent by the school to a parent.
     */
    public function store(StoreDocumentoRequest $request): RedirectResponse
    {
        $tenant = $this->getTenant();
        $user = auth()->user();
        $validated = $request->validated();

        $aluno = Student::query()
            ->where('tenant_id', $tenant->id)
            ->where('id', $validated['aluno_id'])
            ->where('ativo', true)
            ->first();

        if (! $aluno) {
            return back()
                ->withErrors(['aluno_id' => 'Aluno não encontrado nesta escola.'])
                ->withInput();
        }

        $documento = Documento::create([
            'tenant_id' => $tenant->id,
            'aluno_id' => $aluno->id,
            'criado_por' => $user->id,
            'tipo' => TipoDocumento::DocumentoEscola,
            'status' => StatusDocumento::Disponivel,
            'titulo' => $validated['titulo'],
            'descricao' => $validated['descricao'] ?? null,
            'categoria_declaracao' => $validated['categoria_declaracao'] ?? null,
            'anexo_url' => $this->storeAnexo($request->file('anexo')),
        ]);

        app(NotifyDocumentoPushRecipients::class)->queue($documento);

        return redirect()
            ->route('school.documentos.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Documento enviado',
                'message' => 'O documento foi enviado ao responsável com sucesso.',
            ]);
    }

    /**
     * Display the specified document.
     */
    public function show(Documento $documento, ListStudentTeachersAction $listStudentTeachers): Response
    {
        $tenant = $this->getTenant();

        if ($documento->tenant_id !== $tenant->id) {
            abort(404);
        }

        $documento->load(['aluno:id,tenant_id,nome,nome_social', 'criadoPor:id,nome_completo']);

        $professores = [];
        if ($documento->tipo === TipoDocumento::Atestado && $documento->aluno) {
            $professores = $listStudentTeachers
                ->execute($documento->aluno)
                ->map(function (Teacher $professor) {
                    $disciplinas = $professor->disciplinas_aluno
                        ?? $professor->disciplinas
                        ?? collect();

                    return [
                        'id' => $professor->id,
                        'nome' => $professor->usuario?->nome_completo ?? 'Professor',
                        'disciplinas' => collect($disciplinas)
                            ->map(fn ($disciplina) => $disciplina->sigla ?: $disciplina->nome)
                            ->filter()
                            ->values()
                            ->all(),
                    ];
                })
                ->values()
                ->all();
        }

        return Inertia::render('school/documentos/Show', [
            'documento' => [
                'id' => $documento->id,
                'titulo' => $documento->titulo,
                'descricao' => $documento->descricao,
                'tipo' => $documento->tipo?->value ?? $documento->tipo,
                'tipo_label' => $documento->tipo?->label() ?? (string) $documento->tipo,
                'status' => $documento->status?->value ?? $documento->status,
                'status_label' => $documento->status?->label() ?? (string) $documento->status,
                'data_inicio' => $documento->data_inicio?->format('Y-m-d'),
                'data_fim' => $documento->data_fim?->format('Y-m-d'),
                'categoria_declaracao' => $documento->categoria_declaracao?->value ?? $documento->categoria_declaracao,
                'categoria_declaracao_label' => $documento->categoria_declaracao?->label(),
                'anexo_url' => $documento->anexo_url,
                'anexo_resposta_url' => $documento->anexo_resposta_url,
                'motivo_recusa' => $documento->motivo_recusa,
                'aluno' => $documento->aluno ? [
                    'id' => $documento->aluno->id,
                    'nome' => $documento->aluno->nome_social ?: $documento->aluno->nome,
                ] : null,
                'criado_por' => $documento->criadoPor ? [
                    'id' => $documento->criadoPor->id,
                    'nome_completo' => $documento->criadoPor->nome_completo,
                ] : null,
                'created_at' => $documento->created_at?->toIso8601String(),
                'updated_at' => $documento->updated_at?->toIso8601String(),
                'pode_analisar' => in_array($documento->tipo, [
                    TipoDocumento::Atestado,
                    TipoDocumento::PedidoDeclaracao,
                ], true),
                'pode_notificar_professores' => $documento->tipo === TipoDocumento::Atestado,
            ],
            'professores' => $professores,
            'statusOptions' => collect(StatusDocumento::schoolUpdatableValues())->map(fn (string $value) => [
                'value' => $value,
                'label' => StatusDocumento::from($value)->label(),
            ]),
        ]);
    }

    /**
     * Update status / response attachment for an atestado or pedido.
     */
    public function updateStatus(UpdateDocumentoStatusRequest $request, Documento $documento): RedirectResponse
    {
        $tenant = $this->getTenant();

        if ($documento->tenant_id !== $tenant->id) {
            abort(404);
        }

        if (! in_array($documento->tipo, [TipoDocumento::Atestado, TipoDocumento::PedidoDeclaracao], true)) {
            return back()->with('toast', [
                'type' => 'error',
                'title' => 'Ação indisponível',
                'message' => 'Documentos enviados pela escola não passam por análise.',
            ]);
        }

        $validated = $request->validated();
        $previousStatus = $documento->status?->value ?? (string) $documento->status;

        $documento->status = StatusDocumento::from($validated['status']);
        $documento->motivo_recusa = $validated['status'] === StatusDocumento::Recusado->value
            ? ($validated['motivo_recusa'] ?? null)
            : null;

        if (array_key_exists('descricao', $validated) && $validated['descricao'] !== null) {
            $documento->descricao = $validated['descricao'];
        }

        if ($request->hasFile('anexo_resposta')) {
            $this->deleteStoredAnexo($documento->anexo_resposta_url);
            $documento->anexo_resposta_url = $this->storeAnexo($request->file('anexo_resposta'), 'documentos/respostas');
        }

        $documento->save();

        $statusChanged = $previousStatus !== $documento->status->value;
        $enviouResposta = $request->hasFile('anexo_resposta');

        if ($statusChanged || $enviouResposta) {
            app(NotifyDocumentoPushRecipients::class)->queue($documento);
        }

        return redirect()
            ->route('school.documentos.show', $documento)
            ->with('toast', [
                'type' => 'success',
                'title' => 'Documento atualizado',
                'message' => 'O status foi atualizado e o responsável será notificado.',
            ]);
    }

    /**
     * Notify selected teachers of the student's class about a medical certificate.
     */
    public function notifyTeachers(
        NotifyAtestadoTeachersRequest $request,
        Documento $documento,
        NotifyAtestadoTeachersAction $action,
    ): RedirectResponse {
        $tenant = $this->getTenant();

        if ($documento->tenant_id !== $tenant->id) {
            abort(404);
        }

        $result = $action->execute(
            $documento,
            $request->validated('professor_ids'),
            auth()->user()
        );

        return redirect()
            ->route('school.documentos.show', $documento)
            ->with('toast', [
                'type' => 'success',
                'title' => 'Professores notificados',
                'message' => sprintf(
                    '%d recado(s) enviado(s). Os professores verão no app em Recados; o push chega se o app estiver com notificações ativas.',
                    $result['recados']
                ),
            ]);
    }

    /**
     * Soft-delete a document.
     */
    public function destroy(Documento $documento): RedirectResponse
    {
        $tenant = $this->getTenant();

        if ($documento->tenant_id !== $tenant->id) {
            abort(404);
        }

        $documento->delete();

        return redirect()
            ->route('school.documentos.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Documento excluído',
                'message' => 'O documento foi excluído com sucesso.',
            ]);
    }

    /**
     * Map aluno_id => turma nome for active enrollments.
     *
     * @param  list<string>  $alunoIds
     * @return array<string, string>
     */
    protected function turmasAtivasPorAluno(string $tenantId, array $alunoIds): array
    {
        if ($alunoIds === []) {
            return [];
        }

        $driver = DB::connection('shared')->getDriverName();
        $matriculasTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';

        $matriculas = DB::connection('shared')
            ->table($matriculasTable)
            ->where('tenant_id', $tenantId)
            ->where('status', 'ativo')
            ->whereIn('aluno_id', $alunoIds)
            ->orderByDesc('data_matricula')
            ->get(['aluno_id', 'turma_id']);

        $turmaIds = $matriculas->pluck('turma_id')->unique()->filter()->values()->all();
        $turmas = Turma::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $turmaIds)
            ->get(['id', 'nome', 'serie', 'turma_letra'])
            ->keyBy('id');

        $map = [];
        foreach ($matriculas as $matricula) {
            $alunoId = (string) $matricula->aluno_id;
            if (isset($map[$alunoId])) {
                continue;
            }

            $turma = $turmas->get($matricula->turma_id);
            if ($turma) {
                $map[$alunoId] = $this->formatTurmaLabel($turma);
            }
        }

        return $map;
    }

    protected function formatTurmaLabel(Turma $turma): string
    {
        $parts = array_filter([
            $turma->serie,
            $turma->turma_letra ? 'Turma '.$turma->turma_letra : null,
        ], fn ($part) => filled($part));

        if ($parts !== []) {
            return implode(' · ', $parts);
        }

        return $turma->nome;
    }

    protected function storeAnexo(UploadedFile $file, string $directory = 'documentos/anexos'): string
    {
        $path = $file->store($directory, 'public');

        return asset('storage/'.$path);
    }

    protected function deleteStoredAnexo(?string $url): void
    {
        if (! $url) {
            return;
        }

        $storageBaseUrl = asset('storage/');
        if (! str_starts_with($url, $storageBaseUrl)) {
            return;
        }

        $path = str_replace($storageBaseUrl, '', $url);
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    protected function likeOperator(): string
    {
        return (new Documento)->getConnection()->getDriverName() === 'pgsql'
            ? 'ilike'
            : 'like';
    }
}
