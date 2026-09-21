<?php

namespace App\Actions\School;

use App\Actions\Api\NotifyAvisoPushRecipients;
use App\Enums\NivelPrioridade;
use App\Models\Aviso;
use App\Models\Cobranca;
use App\Models\Tenant;
use App\Models\User;

class NotificarCobrancaViaAvisoAction
{
    public function __construct(
        private readonly NotifyAvisoPushRecipients $notifyAvisoPushRecipients,
    ) {}

    public function execute(Tenant $tenant, Cobranca $cobranca, User $criadoPor): Aviso
    {
        if ($cobranca->tenant_id !== $tenant->id) {
            abort(404);
        }

        $cobranca->loadMissing('aluno:id,nome,nome_social');

        $alunoNome = $cobranca->aluno
            ? ($cobranca->aluno->nome_social ?: $cobranca->aluno->nome)
            : 'o aluno';

        $valor = number_format((float) $cobranca->valor, 2, ',', '.');
        $vencimento = $cobranca->vencimento?->format('d/m/Y') ?? '—';
        $statusLabel = $cobranca->statusLabel();

        $titulo = 'Cobrança: '.$cobranca->titulo;

        $conteudo = implode("\n\n", array_filter([
            "Há uma cobrança referente a {$alunoNome}.",
            "Título: {$cobranca->titulo}",
            "Valor: R$ {$valor}",
            "Vencimento: {$vencimento}",
            "Status: {$statusLabel}",
            $cobranca->descricao ? "Detalhes: {$cobranca->descricao}" : null,
            'Acesse o app Edully para acompanhar o pagamento, visualizar o boleto e copiar os dados do PIX.',
        ]));

        $prioridade = $cobranca->esta_atrasada
            ? NivelPrioridade::Alta->value
            : NivelPrioridade::Normal->value;

        $aviso = Aviso::create([
            'tenant_id' => $tenant->id,
            'criado_por' => $criadoPor->id,
            'titulo' => mb_substr($titulo, 0, 255),
            'conteudo' => $conteudo,
            'prioridade' => $prioridade,
            'publico_alvo' => 'responsaveis',
            'anexo_url' => $cobranca->boleto_url,
            'publicado' => true,
            'publicado_em' => now(),
            'expira_em' => null,
        ]);

        $this->notifyAvisoPushRecipients->queue($aviso);

        return $aviso;
    }
}
