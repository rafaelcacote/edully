<?php

namespace App\Http\Controllers\School;

use App\Actions\School\NotificarEventoFinanceiroViaAvisoAction;
use App\Actions\School\PublicarEventoFinanceiroAction;
use App\Enums\PublicoEventoFinanceiro;
use App\Enums\StatusEventoFinanceiro;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\StoreEventoFinanceiroRequest;
use App\Http\Requests\School\UpdateEventoFinanceiroRequest;
use App\Models\EventoFinanceiro;
use App\Models\Student;
use App\Models\Turma;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class EventosFinanceirosController extends Controller
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

    public function index(Request $request): Response
    {
        $tenant = $this->getTenant();
        $filters = $request->only(['search', 'status']);
        $likeOperator = $this->likeOperator();

        $eventos = EventoFinanceiro::query()
            ->where('tenant_id', $tenant->id)
            ->with(['turma:id,nome'])
            ->withCount('cobrancas')
            ->when($filters['search'] ?? null, function ($query, string $search) use ($likeOperator) {
                $search = trim($search);
                $query->where(function ($q) use ($search, $likeOperator) {
                    $q->where('titulo', $likeOperator, "%{$search}%")
                        ->orWhere('descricao', $likeOperator, "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, function ($query, string $status) {
                $query->where('status', $status);
            })
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (EventoFinanceiro $evento) => $this->transformEvento($evento));

        return Inertia::render('school/financeiro/eventos/Index', [
            'eventos' => $eventos,
            'filters' => $filters,
            'statuses' => collect(StatusEventoFinanceiro::cases())->map(fn (StatusEventoFinanceiro $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ]),
        ]);
    }

    public function create(): Response
    {
        $tenant = $this->getTenant();

        return Inertia::render('school/financeiro/eventos/Create', $this->formPayload($tenant->id));
    }

    public function store(StoreEventoFinanceiroRequest $request, PublicarEventoFinanceiroAction $publicar): RedirectResponse
    {
        $tenant = $this->getTenant();
        $validated = $request->validated();

        $evento = EventoFinanceiro::create([
            'tenant_id' => $tenant->id,
            'titulo' => $validated['titulo'],
            'descricao' => $validated['descricao'] ?? null,
            'valor' => $validated['valor'],
            'vencimento' => $validated['vencimento'],
            'publico' => $validated['publico'],
            'turma_id' => $validated['publico'] === PublicoEventoFinanceiro::Turma->value
                ? ($validated['turma_id'] ?? null)
                : null,
            'status' => StatusEventoFinanceiro::Rascunho,
            'pix_copia_cola' => $validated['pix_copia_cola'] ?? null,
            'pix_chave' => $validated['pix_chave'] ?? null,
            'boleto_url' => $request->hasFile('boleto')
                ? $this->storeBoleto($request->file('boleto'))
                : null,
        ]);

        if ($evento->publico === PublicoEventoFinanceiro::Alunos) {
            $evento->syncAlunosSelecionados($validated['aluno_ids'] ?? []);
        } else {
            $evento->syncAlunosSelecionados([]);
        }

        if (! empty($validated['publicar_agora'])) {
            $result = $publicar->execute($tenant, $evento->fresh());

            return redirect()
                ->route('school.eventos-financeiros.show', $evento)
                ->with('toast', [
                    'type' => 'success',
                    'title' => 'Evento publicado',
                    'message' => sprintf(
                        'Evento publicado com %d cobrança(s) gerada(s).',
                        $result['created']
                    ),
                ]);
        }

        return redirect()
            ->route('school.eventos-financeiros.show', $evento)
            ->with('toast', [
                'type' => 'success',
                'title' => 'Evento criado',
                'message' => 'O evento foi salvo como rascunho.',
            ]);
    }

    public function show(EventoFinanceiro $eventoFinanceiro): Response
    {
        $tenant = $this->getTenant();
        $this->ensureTenantOwns($eventoFinanceiro, $tenant->id);

        $eventoFinanceiro->load(['turma:id,nome', 'alunos:id,nome,nome_social']);
        $eventoFinanceiro->loadCount('cobrancas');

        return Inertia::render('school/financeiro/eventos/Show', [
            'evento' => $this->transformEvento($eventoFinanceiro, detailed: true),
        ]);
    }

    public function edit(EventoFinanceiro $eventoFinanceiro): Response
    {
        $tenant = $this->getTenant();
        $this->ensureTenantOwns($eventoFinanceiro, $tenant->id);

        if (! $eventoFinanceiro->isRascunho()) {
            return redirect()
                ->route('school.eventos-financeiros.show', $eventoFinanceiro)
                ->with('toast', [
                    'type' => 'error',
                    'title' => 'Edição indisponível',
                    'message' => 'Somente eventos em rascunho podem ser editados.',
                ]);
        }

        $eventoFinanceiro->load(['alunos:id,nome,nome_social']);

        return Inertia::render('school/financeiro/eventos/Edit', [
            ...$this->formPayload($tenant->id),
            'evento' => $this->transformEvento($eventoFinanceiro, detailed: true),
        ]);
    }

    public function update(UpdateEventoFinanceiroRequest $request, EventoFinanceiro $eventoFinanceiro): RedirectResponse
    {
        $tenant = $this->getTenant();
        $this->ensureTenantOwns($eventoFinanceiro, $tenant->id);

        if (! $eventoFinanceiro->isRascunho()) {
            return back()->with('toast', [
                'type' => 'error',
                'title' => 'Edição indisponível',
                'message' => 'Somente eventos em rascunho podem ser editados.',
            ]);
        }

        $validated = $request->validated();

        $eventoFinanceiro->titulo = $validated['titulo'];
        $eventoFinanceiro->descricao = $validated['descricao'] ?? null;
        $eventoFinanceiro->valor = $validated['valor'];
        $eventoFinanceiro->vencimento = $validated['vencimento'];
        $eventoFinanceiro->publico = $validated['publico'];
        $eventoFinanceiro->turma_id = $validated['publico'] === PublicoEventoFinanceiro::Turma->value
            ? ($validated['turma_id'] ?? null)
            : null;
        $eventoFinanceiro->pix_copia_cola = $validated['pix_copia_cola'] ?? null;
        $eventoFinanceiro->pix_chave = $validated['pix_chave'] ?? null;

        if (! empty($validated['remover_boleto'])) {
            $this->deleteStoredFile($eventoFinanceiro->boleto_url);
            $eventoFinanceiro->boleto_url = null;
        }

        if ($request->hasFile('boleto')) {
            $this->deleteStoredFile($eventoFinanceiro->boleto_url);
            $eventoFinanceiro->boleto_url = $this->storeBoleto($request->file('boleto'));
        }

        $eventoFinanceiro->save();

        if ($eventoFinanceiro->publico === PublicoEventoFinanceiro::Alunos) {
            $eventoFinanceiro->syncAlunosSelecionados($validated['aluno_ids'] ?? []);
        } else {
            $eventoFinanceiro->syncAlunosSelecionados([]);
        }

        return redirect()
            ->route('school.eventos-financeiros.show', $eventoFinanceiro)
            ->with('toast', [
                'type' => 'success',
                'title' => 'Evento atualizado',
                'message' => 'Os dados do evento foram salvos.',
            ]);
    }

    public function publish(EventoFinanceiro $eventoFinanceiro, PublicarEventoFinanceiroAction $publicar): RedirectResponse
    {
        $tenant = $this->getTenant();
        $this->ensureTenantOwns($eventoFinanceiro, $tenant->id);

        $result = $publicar->execute($tenant, $eventoFinanceiro);

        return redirect()
            ->route('school.eventos-financeiros.show', $eventoFinanceiro)
            ->with('toast', [
                'type' => 'success',
                'title' => 'Evento publicado',
                'message' => sprintf(
                    '%d cobrança(s) gerada(s)%s.',
                    $result['created'],
                    $result['skipped'] > 0 ? sprintf(', %d já existiam', $result['skipped']) : ''
                ),
            ]);
    }

    public function encerrar(EventoFinanceiro $eventoFinanceiro): RedirectResponse
    {
        $tenant = $this->getTenant();
        $this->ensureTenantOwns($eventoFinanceiro, $tenant->id);

        if (! $eventoFinanceiro->isPublicado()) {
            return back()->with('toast', [
                'type' => 'error',
                'title' => 'Ação indisponível',
                'message' => 'Somente eventos publicados podem ser encerrados.',
            ]);
        }

        $eventoFinanceiro->status = StatusEventoFinanceiro::Encerrado;
        $eventoFinanceiro->save();

        return redirect()
            ->route('school.eventos-financeiros.show', $eventoFinanceiro)
            ->with('toast', [
                'type' => 'success',
                'title' => 'Evento encerrado',
                'message' => 'O evento foi marcado como encerrado.',
            ]);
    }

    public function notify(EventoFinanceiro $eventoFinanceiro, NotificarEventoFinanceiroViaAvisoAction $action): RedirectResponse
    {
        $tenant = $this->getTenant();
        $this->ensureTenantOwns($eventoFinanceiro, $tenant->id);

        if ($eventoFinanceiro->isRascunho()) {
            return back()->with('toast', [
                'type' => 'error',
                'title' => 'Ação indisponível',
                'message' => 'Publique o evento antes de notificar os responsáveis.',
            ]);
        }

        $action->execute($tenant, $eventoFinanceiro, auth()->user());

        return redirect()
            ->route('school.eventos-financeiros.show', $eventoFinanceiro)
            ->with('toast', [
                'type' => 'success',
                'title' => 'Responsáveis notificados',
                'message' => 'Foi criado um comunicado publicado para os responsáveis.',
            ]);
    }

    public function destroy(EventoFinanceiro $eventoFinanceiro): RedirectResponse
    {
        $tenant = $this->getTenant();
        $this->ensureTenantOwns($eventoFinanceiro, $tenant->id);

        if ($eventoFinanceiro->isPublicado() || $eventoFinanceiro->status === StatusEventoFinanceiro::Encerrado) {
            return back()->with('toast', [
                'type' => 'error',
                'title' => 'Exclusão indisponível',
                'message' => 'Eventos publicados ou encerrados não podem ser excluídos. Cancele as cobranças individualmente se necessário.',
            ]);
        }

        $this->deleteStoredFile($eventoFinanceiro->boleto_url);
        $eventoFinanceiro->syncAlunosSelecionados([]);
        $eventoFinanceiro->delete();

        return redirect()
            ->route('school.eventos-financeiros.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Evento excluído',
                'message' => 'O evento foi excluído com sucesso.',
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formPayload(string $tenantId): array
    {
        $turmas = Turma::query()
            ->where('tenant_id', $tenantId)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome']);

        $alunos = Student::query()
            ->where('tenant_id', $tenantId)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome', 'nome_social'])
            ->map(fn (Student $aluno) => [
                'id' => $aluno->id,
                'nome' => $aluno->nome_social ?: $aluno->nome,
            ]);

        return [
            'turmas' => $turmas,
            'alunos' => $alunos,
            'publicos' => collect(PublicoEventoFinanceiro::cases())->map(fn (PublicoEventoFinanceiro $publico) => [
                'value' => $publico->value,
                'label' => $publico->label(),
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function transformEvento(EventoFinanceiro $evento, bool $detailed = false): array
    {
        $data = [
            'id' => $evento->id,
            'titulo' => $evento->titulo,
            'valor' => $evento->valor,
            'vencimento' => $evento->vencimento?->format('Y-m-d'),
            'vencimento_formatado' => $evento->vencimento?->format('d/m/Y'),
            'publico' => $evento->publico?->value ?? (string) $evento->publico,
            'publico_label' => $evento->publico?->label() ?? (string) $evento->publico,
            'status' => $evento->status?->value ?? (string) $evento->status,
            'status_label' => $evento->status?->label() ?? (string) $evento->status,
            'turma' => $evento->turma ? [
                'id' => $evento->turma->id,
                'nome' => $evento->turma->nome,
            ] : null,
            'cobrancas_count' => $evento->cobrancas_count ?? $evento->cobrancas()->count(),
            'publicado_em' => $evento->publicado_em?->format('d/m/Y H:i'),
            'created_at' => $evento->created_at?->format('d/m/Y H:i'),
        ];

        if ($detailed) {
            $data['descricao'] = $evento->descricao;
            $data['turma_id'] = $evento->turma_id;
            $data['pix_copia_cola'] = $evento->pix_copia_cola;
            $data['pix_chave'] = $evento->pix_chave;
            $data['boleto_url'] = $evento->boleto_url;
            $data['aluno_ids'] = $evento->relationLoaded('alunos')
                ? $evento->alunos->pluck('id')->values()->all()
                : [];
            $data['alunos'] = $evento->relationLoaded('alunos')
                ? $evento->alunos->map(fn (Student $aluno) => [
                    'id' => $aluno->id,
                    'nome' => $aluno->nome_social ?: $aluno->nome,
                ])->values()->all()
                : [];
            $data['pode_editar'] = $evento->isRascunho();
            $data['pode_publicar'] = $evento->isRascunho();
            $data['pode_encerrar'] = $evento->isPublicado();
            $data['pode_excluir'] = $evento->isRascunho();
        }

        return $data;
    }

    protected function ensureTenantOwns(EventoFinanceiro $evento, string $tenantId): void
    {
        if ($evento->tenant_id !== $tenantId) {
            abort(404);
        }
    }

    protected function storeBoleto(UploadedFile $file): string
    {
        $path = $file->store('financeiro/boletos', 'public');

        return asset('storage/'.$path);
    }

    protected function deleteStoredFile(?string $url): void
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
        return (new EventoFinanceiro)->getConnection()->getDriverName() === 'pgsql'
            ? 'ilike'
            : 'like';
    }
}
