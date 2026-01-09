<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Http\Requests\School\StoreDisciplinaRequest;
use App\Http\Requests\School\UpdateDisciplinaRequest;
use App\Models\Disciplina;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DisciplinasController extends Controller
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
     * Display a listing of the disciplines.
     */
    public function index(Request $request): Response
    {
        $tenant = $this->getTenant();
        $filters = $request->only(['search', 'active']);

        $disciplinas = Disciplina::query()
            ->where('tenant_id', $tenant->id)
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $search = trim($search);
                $query->where(function ($q) use ($search) {
                    $q->where('nome', 'ilike', "%{$search}%")
                        ->orWhere('sigla', 'ilike', "%{$search}%")
                        ->orWhere('descricao', 'ilike', "%{$search}%");
                });
            })
            ->when(isset($filters['active']) && $filters['active'] !== '' && $filters['active'] !== null, function ($query) use ($filters) {
                $active = filter_var($filters['active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($active !== null) {
                    $query->where('ativo', $active);
                }
            })
            ->orderBy('nome')
            ->paginate(10)
            ->withQueryString()
            ->through(function (Disciplina $disciplina) {
                return [
                    'id' => $disciplina->id,
                    'nome' => $disciplina->nome,
                    'sigla' => $disciplina->sigla,
                    'descricao' => $disciplina->descricao,
                    'carga_horaria_semanal' => $disciplina->carga_horaria_semanal,
                    'ativo' => (bool) $disciplina->ativo,
                ];
            });

        return Inertia::render('school/disciplinas/Index', [
            'disciplinas' => $disciplinas,
            'filters' => $filters,
        ]);
    }

    /**
     * Show the form for creating a new discipline.
     */
    public function create(): Response
    {
        return Inertia::render('school/disciplinas/Create');
    }

    /**
     * Store a newly created discipline.
     */
    public function store(StoreDisciplinaRequest $request): RedirectResponse
    {
        $tenant = $this->getTenant();
        $validated = $request->validated();

        Disciplina::create([
            ...$validated,
            'tenant_id' => $tenant->id,
            'ativo' => $validated['ativo'] ?? true,
        ]);

        return redirect()
            ->route('school.disciplinas.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Disciplina criada',
                'message' => 'A disciplina foi cadastrada com sucesso.',
            ]);
    }

    /**
     * Display the specified discipline.
     */
    public function show(Disciplina $disciplina): Response
    {
        $tenant = $this->getTenant();

        if ($disciplina->tenant_id !== $tenant->id) {
            abort(404);
        }

        return Inertia::render('school/disciplinas/Show', [
            'disciplina' => [
                'id' => $disciplina->id,
                'nome' => $disciplina->nome,
                'sigla' => $disciplina->sigla,
                'descricao' => $disciplina->descricao,
                'carga_horaria_semanal' => $disciplina->carga_horaria_semanal,
                'ativo' => (bool) $disciplina->ativo,
                'created_at' => $disciplina->created_at,
                'updated_at' => $disciplina->updated_at,
            ],
        ]);
    }

    /**
     * Show the form for editing the specified discipline.
     */
    public function edit(Disciplina $disciplina): Response
    {
        $tenant = $this->getTenant();

        if ($disciplina->tenant_id !== $tenant->id) {
            abort(404);
        }

        return Inertia::render('school/disciplinas/Edit', [
            'disciplina' => [
                'id' => $disciplina->id,
                'nome' => $disciplina->nome,
                'sigla' => $disciplina->sigla,
                'descricao' => $disciplina->descricao,
                'carga_horaria_semanal' => $disciplina->carga_horaria_semanal,
                'ativo' => (bool) $disciplina->ativo,
            ],
        ]);
    }

    /**
     * Update the specified discipline.
     */
    public function update(UpdateDisciplinaRequest $request, Disciplina $disciplina): RedirectResponse
    {
        $tenant = $this->getTenant();

        if ($disciplina->tenant_id !== $tenant->id) {
            abort(404);
        }

        $validated = $request->validated();

        $disciplina->update([
            ...$validated,
            'ativo' => $validated['ativo'] ?? $disciplina->ativo,
        ]);

        return redirect()
            ->route('school.disciplinas.edit', $disciplina)
            ->with('toast', [
                'type' => 'success',
                'title' => 'Disciplina atualizada',
                'message' => 'As alterações foram salvas com sucesso.',
            ]);
    }

    /**
     * Toggle the status of the specified discipline.
     */
    public function toggleStatus(Request $request, Disciplina $disciplina): RedirectResponse
    {
        $tenant = $this->getTenant();

        if ($disciplina->tenant_id !== $tenant->id) {
            abort(404);
        }

        $request->validate([
            'ativo' => ['required', 'boolean'],
        ]);

        $disciplina->update([
            'ativo' => $request->boolean('ativo'),
        ]);

        return redirect()
            ->back()
            ->with('toast', [
                'type' => 'success',
                'title' => 'Status atualizado',
                'message' => 'O status da disciplina foi atualizado com sucesso.',
            ]);
    }

    /**
     * Remove the specified discipline.
     */
    public function destroy(Disciplina $disciplina): RedirectResponse
    {
        $tenant = $this->getTenant();

        if ($disciplina->tenant_id !== $tenant->id) {
            abort(404);
        }

        $disciplina->delete();

        return redirect()
            ->route('school.disciplinas.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Disciplina excluída',
                'message' => 'A disciplina foi removida com sucesso.',
            ]);
    }
}
