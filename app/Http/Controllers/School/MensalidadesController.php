<?php

namespace App\Http\Controllers\School;

use App\Actions\School\GerarMensalidadesLoteAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\GerarMensalidadesLoteRequest;
use App\Models\Turma;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MensalidadesController extends Controller
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
     * Show the form for generating mensalidades in batch.
     */
    public function create(): Response
    {
        $tenant = $this->getTenant();

        $turmas = Turma::query()
            ->where('tenant_id', $tenant->id)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome', 'ano_letivo']);

        return Inertia::render('school/financeiro/MensalidadesCreate', [
            'turmas' => $turmas,
            'defaults' => [
                'ano' => (int) now()->format('Y'),
                'mes' => (int) now()->format('n'),
                'vencimento' => now()->endOfMonth()->toDateString(),
            ],
        ]);
    }

    /**
     * Generate mensalidades for active students.
     */
    public function store(GerarMensalidadesLoteRequest $request, GerarMensalidadesLoteAction $action): RedirectResponse
    {
        $tenant = $this->getTenant();
        $validated = $request->validated();
        $validated['boleto'] = $request->file('boleto');

        $result = $action->execute($tenant, $validated);

        if ($result['created'] === 0 && $result['skipped'] === 0) {
            return back()
                ->withErrors(['turma_id' => 'Nenhum aluno ativo encontrado para gerar mensalidades.'])
                ->withInput();
        }

        $message = sprintf(
            '%d mensalidade(s) gerada(s) para %s.',
            $result['created'],
            $result['referencia']
        );

        if ($result['skipped'] > 0) {
            $message .= sprintf(' %d já existiam e foram ignoradas.', $result['skipped']);
        }

        return redirect()
            ->route('school.cobrancas.index', ['referencia' => $result['referencia'], 'tipo' => 'mensalidade'])
            ->with('toast', [
                'type' => 'success',
                'title' => 'Mensalidades geradas',
                'message' => $message,
            ]);
    }
}
