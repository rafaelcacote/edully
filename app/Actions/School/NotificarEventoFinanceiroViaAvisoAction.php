<?php

namespace App\Actions\School;

use App\Actions\Api\NotifyAvisoPushRecipients;
use App\Enums\NivelPrioridade;
use App\Models\Aviso;
use App\Models\EventoFinanceiro;
use App\Models\Tenant;
use App\Models\User;

class NotificarEventoFinanceiroViaAvisoAction
{
    public function __construct(
        private readonly NotifyAvisoPushRecipients $notifyAvisoPushRecipients,
    ) {}

    public function execute(Tenant $tenant, EventoFinanceiro $evento, User $criadoPor): Aviso
    {
        if ($evento->tenant_id !== $tenant->id) {
            abort(404);
        }

        $evento->loadMissing('turma:id,nome');
        $evento->loadCount('cobrancas');

        $valor = number_format((float) $evento->valor, 2, ',', '.');
        $vencimento = $evento->vencimento?->format('d/m/Y') ?? '—';
        $publico = $evento->publico?->label() ?? (string) $evento->publico;
        $turma = $evento->turma?->nome;

        $titulo = 'Cobrança de evento: '.$evento->titulo;

        $conteudo = implode("\n\n", array_filter([
            'A escola publicou uma cobrança de evento.',
            "Evento: {$evento->titulo}",
            "Valor: R$ {$valor}",
            "Vencimento: {$vencimento}",
            "Público: {$publico}".($turma ? " ({$turma})" : ''),
            $evento->descricao ? "Detalhes: {$evento->descricao}" : null,
            $evento->cobrancas_count > 0
                ? "Foram geradas {$evento->cobrancas_count} cobrança(s) individuais."
                : null,
            'Acesse o app Edully para acompanhar o pagamento, visualizar o boleto e copiar os dados do PIX.',
        ]));

        $aviso = Aviso::create([
            'tenant_id' => $tenant->id,
            'criado_por' => $criadoPor->id,
            'titulo' => mb_substr($titulo, 0, 255),
            'conteudo' => $conteudo,
            'prioridade' => NivelPrioridade::Normal->value,
            'publico_alvo' => 'responsaveis',
            'anexo_url' => $evento->boleto_url,
            'publicado' => true,
            'publicado_em' => now(),
            'expira_em' => null,
        ]);

        $this->notifyAvisoPushRecipients->queue($aviso);

        return $aviso;
    }
}
