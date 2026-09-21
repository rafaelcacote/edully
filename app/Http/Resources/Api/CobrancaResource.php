<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CobrancaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'aluno_id' => $this->aluno_id,
            'tipo' => $this->tipo?->value ?? (string) $this->tipo,
            'tipo_label' => $this->tipo?->label() ?? (string) $this->tipo,
            'titulo' => $this->titulo,
            'descricao' => $this->descricao,
            'referencia' => $this->referencia,
            'valor' => $this->valor !== null ? round((float) $this->valor, 2) : null,
            'vencimento' => $this->vencimento?->format('Y-m-d'),
            'status' => $this->status?->value ?? (string) $this->status,
            'status_exibicao' => $this->status_exibicao,
            'status_label' => $this->statusLabel(),
            'esta_atrasada' => (bool) $this->esta_atrasada,
            'pago_em' => $this->pago_em?->toIso8601String(),
            'boleto_url' => $this->boleto_url,
            'pix_copia_cola' => $this->pix_copia_cola,
            'pix_chave' => $this->pix_chave,
            'pix_qrcode_url' => $this->pix_qrcode_url,
            'evento_financeiro_id' => $this->evento_financeiro_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
