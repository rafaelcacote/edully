<?php

namespace App\Http\Controllers\School;

use App\Actions\School\NotificarCobrancaViaAvisoAction;
use App\Enums\StatusCobranca;
use App\Enums\TipoCobranca;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\MarcarCobrancaPagaRequest;
use App\Http\Requests\School\UpdateCobrancaRequest;
use App\Models\Cobranca;
use App\Models\Student;
use App\Models\Turma;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CobrancasController extends Controller
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
     * Display a listing of cobrancas.
     */
    public function index(Request $request): Response
    {
        $tenant = $this->getTenant();
        $filters = $request->only(['search', 'tipo', 'status', 'turma_id', 'aluno_id', 'referencia']);
        $likeOperator = $this->likeOperator();

        $cobrancas = Cobranca::query()
            ->where('tenant_id', $tenant->id)
            ->with(['aluno:id,nome,nome_social'])
            ->when($filters['search'] ?? null, function ($query, string $search) use ($likeOperator) {
                $search = trim($search);
                $query->where(function ($q) use ($search, $likeOperator) {
                    $q->where('titulo', $likeOperator, "%{$search}%")
                        ->orWhere('descricao', $likeOperator, "%{$search}%")
                        ->orWhere('referencia', $likeOperator, "%{$search}%")
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
                if ($status === 'atrasado') {
                    $query->where('status', StatusCobranca::Pendente)
                        ->whereDate('vencimento', '<', now()->toDateString());

                    return;
                }

                $query->where('status', $status);
            })
            ->when($filters['aluno_id'] ?? null, function ($query, string $alunoId) {
                $query->where('aluno_id', $alunoId);
            })
            ->when($filters['referencia'] ?? null, function ($query, string $referencia) {
                $query->where('referencia', $referencia);
            })
            ->when($filters['turma_id'] ?? null, function ($query, string $turmaId) use ($tenant) {
                $alunoIds = $this->alunoIdsDaTurma($tenant->id, $turmaId);
                $query->whereIn('aluno_id', $alunoIds);
            })
            ->orderByDesc('vencimento')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Cobranca $cobranca) => $this->transformCobranca($cobranca));

        $alunos = Student::query()
            ->where('tenant_id', $tenant->id)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome', 'nome_social'])
            ->map(fn (Student $aluno) => [
                'id' => $aluno->id,
                'nome' => $aluno->nome_social ?: $aluno->nome,
            ]);

        $turmas = Turma::query()
            ->where('tenant_id', $tenant->id)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome']);

        return Inertia::render('school/financeiro/Index', [
            'cobrancas' => $cobrancas,
            'filters' => $filters,
            'alunos' => $alunos,
            'turmas' => $turmas,
            'tipos' => collect(TipoCobranca::cases())->map(fn (TipoCobranca $tipo) => [
                'value' => $tipo->value,
                'label' => $tipo->label(),
            ]),
            'statuses' => [
                ['value' => StatusCobranca::Pendente->value, 'label' => StatusCobranca::Pendente->label()],
                ['value' => 'atrasado', 'label' => 'Atrasado'],
                ['value' => StatusCobranca::Pago->value, 'label' => StatusCobranca::Pago->label()],
                ['value' => StatusCobranca::Cancelado->value, 'label' => StatusCobranca::Cancelado->label()],
            ],
        ]);
    }

    /**
     * Display the specified cobranca.
     */
    public function show(Cobranca $cobranca): Response
    {
        $tenant = $this->getTenant();
        $this->ensureTenantOwns($cobranca, $tenant->id);

        $cobranca->load(['aluno:id,nome,nome_social']);

        return Inertia::render('school/financeiro/Show', [
            'cobranca' => $this->transformCobranca($cobranca, detailed: true),
        ]);
    }

    /**
     * Show the form for editing the specified cobranca.
     */
    public function edit(Cobranca $cobranca): Response
    {
        $tenant = $this->getTenant();
        $this->ensureTenantOwns($cobranca, $tenant->id);

        $cobranca->load(['aluno:id,nome,nome_social']);

        return Inertia::render('school/financeiro/Edit', [
            'cobranca' => $this->transformCobranca($cobranca, detailed: true),
        ]);
    }

    /**
     * Update the specified cobranca.
     */
    public function update(UpdateCobrancaRequest $request, Cobranca $cobranca): RedirectResponse
    {
        $tenant = $this->getTenant();
        $this->ensureTenantOwns($cobranca, $tenant->id);

        $validated = $request->validated();

        $cobranca->titulo = $validated['titulo'];
        $cobranca->descricao = $validated['descricao'] ?? null;
        $cobranca->valor = $validated['valor'];
        $cobranca->vencimento = $validated['vencimento'];
        $cobranca->pix_copia_cola = $validated['pix_copia_cola'] ?? null;
        $cobranca->pix_chave = $validated['pix_chave'] ?? null;

        if (! empty($validated['remover_boleto'])) {
            $this->deleteStoredFile($cobranca->boleto_url);
            $cobranca->boleto_url = null;
        }

        if ($request->hasFile('boleto')) {
            $this->deleteStoredFile($cobranca->boleto_url);
            $cobranca->boleto_url = $this->storeBoleto($request->file('boleto'));
        }

        $cobranca->save();

        return redirect()
            ->route('school.cobrancas.show', $cobranca)
            ->with('toast', [
                'type' => 'success',
                'title' => 'Cobrança atualizada',
                'message' => 'Os dados da cobrança foram salvos com sucesso.',
            ]);
    }

    /**
     * Mark cobranca as paid.
     */
    public function markPaid(MarcarCobrancaPagaRequest $request, Cobranca $cobranca): RedirectResponse
    {
        $tenant = $this->getTenant();
        $this->ensureTenantOwns($cobranca, $tenant->id);

        if ($cobranca->status === StatusCobranca::Cancelado) {
            return back()->with('toast', [
                'type' => 'error',
                'title' => 'Ação indisponível',
                'message' => 'Não é possível marcar como paga uma cobrança cancelada.',
            ]);
        }

        $validated = $request->validated();
        $pagoEm = isset($validated['pago_em']) ? new \DateTimeImmutable($validated['pago_em']) : null;

        $cobranca->markAsPaid($validated['pago_observacao'] ?? null, $pagoEm);

        return redirect()
            ->route('school.cobrancas.show', $cobranca)
            ->with('toast', [
                'type' => 'success',
                'title' => 'Pagamento confirmado',
                'message' => 'A cobrança foi marcada como paga.',
            ]);
    }

    /**
     * Cancel cobranca.
     */
    public function cancel(Request $request, Cobranca $cobranca): RedirectResponse
    {
        $tenant = $this->getTenant();
        $this->ensureTenantOwns($cobranca, $tenant->id);

        $observacao = $request->input('pago_observacao');

        $cobranca->markAsCancelled(is_string($observacao) && $observacao !== '' ? $observacao : null);

        return redirect()
            ->route('school.cobrancas.show', $cobranca)
            ->with('toast', [
                'type' => 'success',
                'title' => 'Cobrança cancelada',
                'message' => 'A cobrança foi cancelada.',
            ]);
    }

    /**
     * Notify parents via a published Aviso about this cobranca.
     */
    public function notify(Cobranca $cobranca, NotificarCobrancaViaAvisoAction $action): RedirectResponse
    {
        $tenant = $this->getTenant();
        $this->ensureTenantOwns($cobranca, $tenant->id);

        if ($cobranca->status === StatusCobranca::Cancelado) {
            return back()->with('toast', [
                'type' => 'error',
                'title' => 'Ação indisponível',
                'message' => 'Não é possível notificar sobre uma cobrança cancelada.',
            ]);
        }

        $action->execute($tenant, $cobranca, auth()->user());

        return redirect()
            ->route('school.cobrancas.show', $cobranca)
            ->with('toast', [
                'type' => 'success',
                'title' => 'Responsáveis notificados',
                'message' => 'Foi criado um comunicado publicado para os responsáveis.',
            ]);
    }

    /**
     * Soft-delete cobranca.
     */
    public function destroy(Cobranca $cobranca): RedirectResponse
    {
        $tenant = $this->getTenant();
        $this->ensureTenantOwns($cobranca, $tenant->id);

        $cobranca->delete();

        return redirect()
            ->route('school.cobrancas.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Cobrança excluída',
                'message' => 'A cobrança foi excluída com sucesso.',
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function transformCobranca(Cobranca $cobranca, bool $detailed = false): array
    {
        $data = [
            'id' => $cobranca->id,
            'titulo' => $cobranca->titulo,
            'tipo' => $cobranca->tipo?->value ?? (string) $cobranca->tipo,
            'tipo_label' => $cobranca->tipo?->label() ?? (string) $cobranca->tipo,
            'referencia' => $cobranca->referencia,
            'valor' => $cobranca->valor,
            'vencimento' => $cobranca->vencimento?->format('Y-m-d'),
            'vencimento_formatado' => $cobranca->vencimento?->format('d/m/Y'),
            'status' => $cobranca->status?->value ?? (string) $cobranca->status,
            'status_exibicao' => $cobranca->status_exibicao,
            'status_label' => $cobranca->statusLabel(),
            'esta_atrasada' => $cobranca->esta_atrasada,
            'aluno' => $cobranca->aluno ? [
                'id' => $cobranca->aluno->id,
                'nome' => $cobranca->aluno->nome_social ?: $cobranca->aluno->nome,
            ] : null,
            'boleto_url' => $cobranca->boleto_url,
            'tem_pix' => filled($cobranca->pix_copia_cola) || filled($cobranca->pix_chave),
        ];

        if ($detailed) {
            $data['descricao'] = $cobranca->descricao;
            $data['pix_copia_cola'] = $cobranca->pix_copia_cola;
            $data['pix_chave'] = $cobranca->pix_chave;
            $data['pix_qrcode_url'] = $cobranca->pix_qrcode_url;
            $data['pago_em'] = $cobranca->pago_em?->format('Y-m-d\TH:i');
            $data['pago_em_formatado'] = $cobranca->pago_em?->format('d/m/Y H:i');
            $data['pago_observacao'] = $cobranca->pago_observacao;
            $data['created_at'] = $cobranca->created_at?->toIso8601String();
            $data['updated_at'] = $cobranca->updated_at?->toIso8601String();
        }

        return $data;
    }

    /**
     * @return list<string>
     */
    protected function alunoIdsDaTurma(string $tenantId, string $turmaId): array
    {
        $driver = DB::connection('shared')->getDriverName();
        $matriculasTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';

        return DB::connection('shared')
            ->table($matriculasTable)
            ->where('tenant_id', $tenantId)
            ->where('turma_id', $turmaId)
            ->where('status', 'ativo')
            ->pluck('aluno_id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    protected function ensureTenantOwns(Cobranca $cobranca, string $tenantId): void
    {
        if ($cobranca->tenant_id !== $tenantId) {
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
        return (new Cobranca)->getConnection()->getDriverName() === 'pgsql'
            ? 'ilike'
            : 'like';
    }
}
