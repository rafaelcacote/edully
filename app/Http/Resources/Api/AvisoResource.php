<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AvisoResource extends JsonResource
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
            'titulo' => $this->titulo,
            'conteudo' => $this->conteudo,
            'prioridade' => $this->prioridade,
            'publico_alvo' => $this->publico_alvo,
            'anexo_url' => $this->anexo_url,
            'publicado' => $this->publicado,
            'publicado_em' => $this->publicado_em?->toIso8601String(),
            'expira_em' => $this->expira_em?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'criado_por' => $this->whenLoaded('criadoPor', function () {
                if (! $this->criadoPor) {
                    return null;
                }

                return [
                    'id' => $this->criadoPor->id,
                    'nome_completo' => $this->criadoPor->nome_completo,
                    'avatar_url' => $this->criadoPor->avatar_url,
                    'foto_url' => $this->criadoPor->avatar_url,
                ];
            }),
            'tenant' => $this->whenLoaded('tenant', function () {
                return [
                    'id' => $this->tenant->id,
                    'nome' => $this->tenant->nome,
                ];
            }),
        ];
    }
}
