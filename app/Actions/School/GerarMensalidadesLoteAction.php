<?php

namespace App\Actions\School;

use App\Enums\StatusCobranca;
use App\Enums\TipoCobranca;
use App\Models\Cobranca;
use App\Models\Tenant;
use App\Models\Turma;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class GerarMensalidadesLoteAction
{
    /**
     * @param  array{
     *     turma_id?: string|null,
     *     ano: int,
     *     mes: int,
     *     valor: float|int|string,
     *     vencimento: string,
     *     descricao?: string|null,
     *     pix_copia_cola?: string|null,
     *     pix_chave?: string|null,
     *     boleto?: UploadedFile|null
     * }  $payload
     * @return array{created: int, skipped: int, referencia: string}
     */
    public function execute(Tenant $tenant, array $payload): array
    {
        $ano = (int) $payload['ano'];
        $mes = (int) $payload['mes'];
        $referencia = sprintf('%04d-%02d', $ano, $mes);
        $titulo = $this->tituloMensalidade($mes, $ano);
        $boletoUrl = $this->storeBoleto($payload['boleto'] ?? null);

        $alunoIds = $this->resolveAlunoIds($tenant, $payload['turma_id'] ?? null);

        $created = 0;
        $skipped = 0;

        DB::connection('shared')->transaction(function () use (
            $tenant,
            $payload,
            $alunoIds,
            $referencia,
            $titulo,
            $boletoUrl,
            &$created,
            &$skipped
        ) {
            foreach ($alunoIds as $alunoId) {
                $exists = Cobranca::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('aluno_id', $alunoId)
                    ->where('tipo', TipoCobranca::Mensalidade)
                    ->where('referencia', $referencia)
                    ->exists();

                if ($exists) {
                    $skipped++;

                    continue;
                }

                Cobranca::create([
                    'tenant_id' => $tenant->id,
                    'aluno_id' => $alunoId,
                    'tipo' => TipoCobranca::Mensalidade,
                    'titulo' => $titulo,
                    'descricao' => $payload['descricao'] ?? null,
                    'referencia' => $referencia,
                    'valor' => $payload['valor'],
                    'vencimento' => $payload['vencimento'],
                    'status' => StatusCobranca::Pendente,
                    'boleto_url' => $boletoUrl,
                    'pix_copia_cola' => $payload['pix_copia_cola'] ?? null,
                    'pix_chave' => $payload['pix_chave'] ?? null,
                ]);

                $created++;
            }
        });

        return [
            'created' => $created,
            'skipped' => $skipped,
            'referencia' => $referencia,
        ];
    }

    /**
     * @return list<string>
     */
    protected function resolveAlunoIds(Tenant $tenant, ?string $turmaId): array
    {
        $driver = DB::connection('shared')->getDriverName();
        $matriculasTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';
        $alunosTable = $driver === 'sqlite' ? 'alunos' : 'escola.alunos';

        $query = DB::connection('shared')
            ->table($matriculasTable.' as matriculas')
            ->join($alunosTable.' as alunos', 'alunos.id', '=', 'matriculas.aluno_id')
            ->where('matriculas.tenant_id', $tenant->id)
            ->where('matriculas.status', 'ativo')
            ->where('alunos.ativo', true)
            ->whereNull('alunos.deleted_at');

        if ($turmaId) {
            $turma = Turma::query()
                ->where('tenant_id', $tenant->id)
                ->where('id', $turmaId)
                ->firstOrFail();

            $query->where('matriculas.turma_id', $turma->id);
        }

        return $query
            ->select(['alunos.id', 'alunos.nome'])
            ->distinct()
            ->orderBy('alunos.nome')
            ->pluck('alunos.id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    protected function tituloMensalidade(int $mes, int $ano): string
    {
        $meses = [
            1 => 'Jan',
            2 => 'Fev',
            3 => 'Mar',
            4 => 'Abr',
            5 => 'Mai',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Ago',
            9 => 'Set',
            10 => 'Out',
            11 => 'Nov',
            12 => 'Dez',
        ];

        return sprintf('Mensalidade %s/%d', $meses[$mes], $ano);
    }

    protected function storeBoleto(?UploadedFile $file): ?string
    {
        if (! $file) {
            return null;
        }

        $path = $file->store('financeiro/boletos', 'public');

        return asset('storage/'.$path);
    }
}
