<?php

namespace App\Actions\School;

use App\Enums\PublicoEventoFinanceiro;
use App\Enums\StatusCobranca;
use App\Enums\StatusEventoFinanceiro;
use App\Enums\TipoCobranca;
use App\Models\Cobranca;
use App\Models\EventoFinanceiro;
use App\Models\Student;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublicarEventoFinanceiroAction
{
    /**
     * @return array{created: int, skipped: int}
     */
    public function execute(Tenant $tenant, EventoFinanceiro $evento): array
    {
        if ($evento->tenant_id !== $tenant->id) {
            abort(404);
        }

        if ($evento->status !== StatusEventoFinanceiro::Rascunho) {
            throw ValidationException::withMessages([
                'status' => 'Somente eventos em rascunho podem ser publicados.',
            ]);
        }

        $alunoIds = $this->resolveAlunoIds($tenant, $evento);

        if ($alunoIds === []) {
            throw ValidationException::withMessages([
                'publico' => 'Nenhum aluno encontrado para o público selecionado.',
            ]);
        }

        $created = 0;
        $skipped = 0;

        DB::connection('shared')->transaction(function () use (
            $tenant,
            $evento,
            $alunoIds,
            &$created,
            &$skipped
        ) {
            foreach ($alunoIds as $alunoId) {
                $exists = Cobranca::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('aluno_id', $alunoId)
                    ->where('evento_financeiro_id', $evento->id)
                    ->exists();

                if ($exists) {
                    $skipped++;

                    continue;
                }

                Cobranca::create([
                    'tenant_id' => $tenant->id,
                    'aluno_id' => $alunoId,
                    'tipo' => TipoCobranca::Evento,
                    'evento_financeiro_id' => $evento->id,
                    'titulo' => $evento->titulo,
                    'descricao' => $evento->descricao,
                    'referencia' => null,
                    'valor' => $evento->valor,
                    'vencimento' => $evento->vencimento,
                    'status' => StatusCobranca::Pendente,
                    'boleto_url' => $evento->boleto_url,
                    'pix_copia_cola' => $evento->pix_copia_cola,
                    'pix_chave' => $evento->pix_chave,
                    'pix_qrcode_url' => $evento->pix_qrcode_url,
                ]);

                $created++;
            }

            $evento->status = StatusEventoFinanceiro::Publicado;
            $evento->publicado_em = now();
            $evento->save();
        });

        return [
            'created' => $created,
            'skipped' => $skipped,
        ];
    }

    /**
     * @return list<string>
     */
    protected function resolveAlunoIds(Tenant $tenant, EventoFinanceiro $evento): array
    {
        $publico = $evento->publico instanceof PublicoEventoFinanceiro
            ? $evento->publico
            : PublicoEventoFinanceiro::from((string) $evento->publico);

        return match ($publico) {
            PublicoEventoFinanceiro::Turma => $this->alunosDaTurma($tenant->id, (string) $evento->turma_id),
            PublicoEventoFinanceiro::Alunos => $evento->alunos()
                ->where('tenant_id', $tenant->id)
                ->where('ativo', true)
                ->get()
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->values()
                ->all(),
            PublicoEventoFinanceiro::TodosAtivos => $this->todosAlunosAtivos($tenant->id),
        };
    }

    /**
     * @return list<string>
     */
    protected function alunosDaTurma(string $tenantId, string $turmaId): array
    {
        $driver = DB::connection('shared')->getDriverName();
        $matriculasTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';
        $alunosTable = $driver === 'sqlite' ? 'alunos' : 'escola.alunos';

        return DB::connection('shared')
            ->table($matriculasTable.' as matriculas')
            ->join($alunosTable.' as alunos', 'alunos.id', '=', 'matriculas.aluno_id')
            ->where('matriculas.tenant_id', $tenantId)
            ->where('matriculas.turma_id', $turmaId)
            ->where('matriculas.status', 'ativo')
            ->where('alunos.ativo', true)
            ->whereNull('alunos.deleted_at')
            ->distinct()
            ->pluck('alunos.id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    protected function todosAlunosAtivos(string $tenantId): array
    {
        return Student::query()
            ->where('tenant_id', $tenantId)
            ->where('ativo', true)
            ->orderBy('nome')
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }
}
