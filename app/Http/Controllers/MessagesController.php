<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMessageRequest;
use App\Http\Requests\UpdateMessageRequest;
use App\Models\Mensagem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
     * Display a listing of the messages.
     */
    public function index(Request $request): Response
    {
        $tenant = $this->getTenant();
        $user = auth()->user();
        $filters = $request->only(['search', 'read', 'active']);

        $messages = Mensagem::query()
            ->where('tenant_id', $tenant->id)
            ->where(function ($query) use ($user) {
                $query->where('remetente_id', $user->id)
                    ->orWhere('destinatario_id', $user->id);
            })
            ->with(['remetente:id,nome_completo', 'destinatario:id,nome_completo'])
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $search = trim($search);
                $query->where(function ($q) use ($search) {
                    $q->where('titulo', 'ilike', "%{$search}%")
                        ->orWhere('conteudo', 'ilike', "%{$search}%");
                });
            })
            ->when(isset($filters['read']) && $filters['read'] !== '' && $filters['read'] !== null, function ($query) use ($filters) {
                $read = filter_var($filters['read'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($read !== null) {
                    $query->where('lida', $read);
                }
            })
            ->when(isset($filters['active']) && $filters['active'] !== '' && $filters['active'] !== null, function ($query) use ($filters) {
                $active = filter_var($filters['active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($active !== null) {
                    $query->where('ativo', $active);
                }
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString()
            ->through(function (Mensagem $message) use ($user) {
                return [
                    'id' => $message->id,
                    'titulo' => $message->titulo,
                    'conteudo' => $message->conteudo,
                    'lida' => (bool) $message->lida,
                    'ativo' => (bool) $message->ativo,
                    'remetente' => $message->remetente
                        ? [
                            'id' => $message->remetente->id,
                            'nome_completo' => $message->remetente->nome_completo,
                        ]
                        : null,
                    'destinatario' => $message->destinatario
                        ? [
                            'id' => $message->destinatario->id,
                            'nome_completo' => $message->destinatario->nome_completo,
                        ]
                        : null,
                    'is_sender' => $message->remetente_id === $user->id,
                    'created_at' => $message->created_at,
                    'updated_at' => $message->updated_at,
                ];
            });

        return Inertia::render('messages/Index', [
            'messages' => $messages,
            'filters' => $filters,
        ]);
    }

    /**
     * Show the form for creating a new message.
     */
    public function create(): Response
    {
        $tenant = $this->getTenant();

        $users = \App\Models\User::query()
            ->whereHas('tenants', function ($query) use ($tenant) {
                $query->where('tenants.id', $tenant->id);
            })
            ->where('id', '!=', auth()->id())
            ->where('ativo', true)
            ->orderBy('nome_completo')
            ->get(['id', 'nome_completo', 'email']);

        return Inertia::render('messages/Create', [
            'users' => $users,
        ]);
    }

    /**
     * Store a newly created message.
     */
    public function store(StoreMessageRequest $request): RedirectResponse
    {
        $tenant = $this->getTenant();
        $validated = $request->validated();

        Mensagem::create([
            ...$validated,
            'tenant_id' => $tenant->id,
            'remetente_id' => auth()->id(),
            'lida' => false,
            'ativo' => $validated['ativo'] ?? true,
        ]);

        return redirect()
            ->route('messages.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Mensagem enviada',
                'message' => 'A mensagem foi enviada com sucesso.',
            ]);
    }

    /**
     * Show the form for editing the specified message.
     */
    public function edit(Mensagem $message): Response
    {
        $tenant = $this->getTenant();
        $user = auth()->user();

        if ($message->tenant_id !== $tenant->id) {
            abort(404);
        }

        if ($message->remetente_id !== $user->id) {
            abort(403, 'Você só pode editar mensagens que você enviou.');
        }

        $users = \App\Models\User::query()
            ->whereHas('tenants', function ($query) use ($tenant) {
                $query->where('tenants.id', $tenant->id);
            })
            ->where('id', '!=', auth()->id())
            ->where('ativo', true)
            ->orderBy('nome_completo')
            ->get(['id', 'nome_completo', 'email']);

        return Inertia::render('messages/Edit', [
            'message' => [
                'id' => $message->id,
                'destinatario_id' => $message->destinatario_id,
                'titulo' => $message->titulo,
                'conteudo' => $message->conteudo,
                'ativo' => (bool) $message->ativo,
                'created_at' => $message->created_at,
                'updated_at' => $message->updated_at,
            ],
            'users' => $users,
        ]);
    }

    /**
     * Update the specified message.
     */
    public function update(UpdateMessageRequest $request, Mensagem $message): RedirectResponse
    {
        $tenant = $this->getTenant();
        $user = auth()->user();

        if ($message->tenant_id !== $tenant->id) {
            abort(404);
        }

        if ($message->remetente_id !== $user->id) {
            abort(403, 'Você só pode editar mensagens que você enviou.');
        }

        $validated = $request->validated();

        $message->update([
            ...$validated,
            'ativo' => $validated['ativo'] ?? $message->ativo,
        ]);

        return redirect()
            ->route('messages.edit', $message)
            ->with('toast', [
                'type' => 'success',
                'title' => 'Mensagem atualizada',
                'message' => 'As alterações foram salvas com sucesso.',
            ]);
    }

    /**
     * Mark message as read.
     */
    public function markAsRead(Mensagem $message): RedirectResponse
    {
        $tenant = $this->getTenant();
        $user = auth()->user();

        if ($message->tenant_id !== $tenant->id) {
            abort(404);
        }

        if ($message->destinatario_id !== $user->id) {
            abort(403, 'Você só pode marcar como lida mensagens enviadas para você.');
        }

        $message->update(['lida' => true]);

        return redirect()
            ->back()
            ->with('toast', [
                'type' => 'success',
                'title' => 'Mensagem marcada como lida',
                'message' => 'A mensagem foi marcada como lida.',
            ]);
    }

    /**
     * Remove the specified message.
     */
    public function destroy(Mensagem $message): RedirectResponse
    {
        $tenant = $this->getTenant();
        $user = auth()->user();

        if ($message->tenant_id !== $tenant->id) {
            abort(404);
        }

        if ($message->remetente_id !== $user->id && $message->destinatario_id !== $user->id) {
            abort(403, 'Você não tem permissão para excluir esta mensagem.');
        }

        $message->delete();

        return redirect()
            ->route('messages.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Mensagem excluída',
                'message' => 'A mensagem foi removida com sucesso.',
            ]);
    }
}
