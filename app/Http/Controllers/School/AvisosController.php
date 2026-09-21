<?php

namespace App\Http\Controllers\School;

use App\Actions\Api\NotifyAvisoPushRecipients;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\StoreAvisoRequest;
use App\Http\Requests\School\UpdateAvisoRequest;
use App\Models\Aviso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AvisosController extends Controller
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
     * Display a listing of the avisos.
     */
    public function index(Request $request): Response
    {
        $tenant = $this->getTenant();
        $filters = $request->only(['search', 'published', 'priority']);

        $avisos = Aviso::query()
            ->where('tenant_id', $tenant->id)
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $search = trim($search);
                $query->where(function ($q) use ($search) {
                    $q->where('titulo', 'ilike', "%{$search}%")
                        ->orWhere('conteudo', 'ilike', "%{$search}%");
                });
            })
            ->when(isset($filters['published']) && $filters['published'] !== '' && $filters['published'] !== null, function ($query) use ($filters) {
                $published = filter_var($filters['published'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($published !== null) {
                    $query->where('publicado', $published);
                }
            })
            ->when($filters['priority'] ?? null, function ($query, string $priority) {
                $query->where('prioridade', $priority);
            })
            ->orderBy('created_at', 'desc')
            ->select([
                'id',
                'titulo',
                'prioridade',
                'publico_alvo',
                'publicado',
                'publicado_em',
                'expira_em',
                'anexo_url',
                'created_at',
            ])
            ->paginate(10)
            ->withQueryString()
            ->through(function (Aviso $aviso) {
                return [
                    'id' => $aviso->id,
                    'titulo' => $aviso->titulo,
                    'prioridade' => $aviso->prioridade,
                    'publico_alvo' => $aviso->publico_alvo,
                    'publicado' => (bool) $aviso->publicado,
                    'publicado_em' => $aviso->publicado_em,
                    'expira_em' => $aviso->expira_em,
                    'anexo_url' => $aviso->anexo_url,
                    'created_at' => $aviso->created_at,
                    'expirado' => $aviso->isExpirado(),
                    'status' => $aviso->statusPublicacao(),
                ];
            });

        return Inertia::render('school/avisos/Index', [
            'avisos' => $avisos,
            'filters' => $filters,
        ]);
    }

    /**
     * Show the form for creating a new aviso.
     */
    public function create(): Response
    {
        return Inertia::render('school/avisos/Create');
    }

    /**
     * Store a newly created aviso.
     */
    public function store(StoreAvisoRequest $request): RedirectResponse
    {
        $tenant = $this->getTenant();
        $user = auth()->user();

        $validated = $request->validated();

        // Processar upload do anexo PDF
        $anexoUrl = null;
        if ($request->hasFile('anexo')) {
            $anexo = $request->file('anexo');
            $anexoPath = $anexo->store('avisos/anexos', 'public');
            $anexoUrl = asset('storage/'.$anexoPath);
        } elseif (isset($validated['anexo_url']) && ! empty($validated['anexo_url'])) {
            $anexoUrl = $validated['anexo_url'];
        }

        $aviso = new Aviso;
        $aviso->tenant_id = $tenant->id;
        $aviso->criado_por = $user->id;
        $aviso->titulo = $validated['titulo'];
        $aviso->conteudo = $validated['conteudo'];
        $aviso->prioridade = $validated['prioridade'] ?? 'normal';
        $aviso->publico_alvo = $validated['publico_alvo'] ?? 'todos';
        $aviso->anexo_url = $anexoUrl;
        $aviso->publicado = $validated['publicado'] ?? false;
        $aviso->publicado_em = $validated['publicado_em'] ?? null;
        $aviso->expira_em = $validated['expira_em'] ?? null;

        if ($aviso->publicado && ! $aviso->publicado_em) {
            $aviso->publicado_em = now();
        }

        $aviso->save();

        if ($aviso->publicado) {
            app(NotifyAvisoPushRecipients::class)->queue($aviso);
        }

        return redirect()
            ->route('school.avisos.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Comunicado criado',
                'message' => 'O comunicado foi cadastrado com sucesso.',
            ]);
    }

    /**
     * Display the specified aviso.
     */
    public function show(Aviso $aviso): Response
    {
        $tenant = $this->getTenant();

        if ($aviso->tenant_id !== $tenant->id) {
            abort(404);
        }

        $aviso->load('criadoPor');

        return Inertia::render('school/avisos/Show', [
            'aviso' => [
                ...$aviso->toArray(),
                'publicado' => (bool) $aviso->publicado,
                'expirado' => $aviso->isExpirado(),
                'status' => $aviso->statusPublicacao(),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified aviso.
     */
    public function edit(Aviso $aviso): Response
    {
        $tenant = $this->getTenant();

        if ($aviso->tenant_id !== $tenant->id) {
            abort(404);
        }

        return Inertia::render('school/avisos/Edit', [
            'aviso' => [
                'id' => $aviso->id,
                'titulo' => $aviso->titulo,
                'conteudo' => $aviso->conteudo,
                'prioridade' => $aviso->prioridade,
                'publico_alvo' => $aviso->publico_alvo,
                'anexo_url' => $aviso->anexo_url,
                'publicado' => (bool) $aviso->publicado,
                // Formato datetime-local (sem conversão UTC via ISO) para o formulário.
                'publicado_em' => $aviso->publicado_em?->format('Y-m-d\TH:i'),
                'expira_em' => $aviso->expira_em?->format('Y-m-d\TH:i'),
                'created_at' => $aviso->created_at,
                'updated_at' => $aviso->updated_at,
            ],
        ]);
    }

    /**
     * Update the specified aviso.
     */
    public function update(UpdateAvisoRequest $request, Aviso $aviso): RedirectResponse
    {
        $tenant = $this->getTenant();

        if ($aviso->tenant_id !== $tenant->id) {
            abort(404);
        }

        $validated = $request->validated();

        // Processar upload do anexo PDF
        $anexoUrl = $aviso->anexo_url;
        if ($request->hasFile('anexo')) {
            // Deletar anexo antigo se existir e for do storage local
            if ($aviso->anexo_url) {
                $storageBaseUrl = asset('storage/');
                if (str_starts_with($aviso->anexo_url, $storageBaseUrl)) {
                    $oldAnexoPath = str_replace($storageBaseUrl, '', $aviso->anexo_url);
                    if (Storage::disk('public')->exists($oldAnexoPath)) {
                        Storage::disk('public')->delete($oldAnexoPath);
                    }
                }
            }

            $anexo = $request->file('anexo');
            $anexoPath = $anexo->store('avisos/anexos', 'public');
            $anexoUrl = asset('storage/'.$anexoPath);
        } elseif (isset($validated['anexo_url']) && ! empty($validated['anexo_url'])) {
            // Se foi fornecida uma URL manualmente, usar ela
            $anexoUrl = $validated['anexo_url'];
        } elseif (isset($validated['anexo_url']) && empty($validated['anexo_url'])) {
            // Se o campo foi enviado vazio, remover o anexo
            if ($aviso->anexo_url) {
                $storageBaseUrl = asset('storage/');
                if (str_starts_with($aviso->anexo_url, $storageBaseUrl)) {
                    $oldAnexoPath = str_replace($storageBaseUrl, '', $aviso->anexo_url);
                    if (Storage::disk('public')->exists($oldAnexoPath)) {
                        Storage::disk('public')->delete($oldAnexoPath);
                    }
                }
            }
            $anexoUrl = null;
        }

        $wasPublished = (bool) $aviso->publicado;

        $aviso->titulo = $validated['titulo'];
        $aviso->conteudo = $validated['conteudo'];
        $aviso->prioridade = $validated['prioridade'] ?? 'normal';
        $aviso->publico_alvo = $validated['publico_alvo'] ?? 'todos';
        $aviso->anexo_url = $anexoUrl;
        $aviso->publicado = $validated['publicado'] ?? false;
        $aviso->publicado_em = $validated['publicado_em'] ?? null;
        $aviso->expira_em = $validated['expira_em'] ?? null;

        if ($aviso->publicado && ! $aviso->publicado_em) {
            $aviso->publicado_em = now();
        }

        $aviso->save();

        if ($aviso->publicado && ! $wasPublished) {
            app(NotifyAvisoPushRecipients::class)->queue($aviso);
        }

        return redirect()
            ->route('school.avisos.edit', $aviso)
            ->with('toast', [
                'type' => 'success',
                'title' => 'Comunicado atualizado',
                'message' => 'As alterações foram salvas com sucesso.',
            ]);
    }

    /**
     * Remove the specified aviso.
     */
    public function destroy(Aviso $aviso): RedirectResponse
    {
        $tenant = $this->getTenant();

        if ($aviso->tenant_id !== $tenant->id) {
            abort(404);
        }

        $aviso->delete();

        return redirect()
            ->route('school.avisos.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Comunicado excluído',
                'message' => 'O comunicado foi excluído com sucesso.',
            ]);
    }
}
