<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
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
            'tipo' => $this->tipo,
            'prioridade' => $this->prioridade,
            'anexo_url' => $this->anexo_url,
            'lida' => $this->lida,
            'lida_em' => $this->lida_em?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'aluno' => $this->whenLoaded('aluno', function () {
                return [
                    'id' => $this->aluno->id,
                    'nome' => $this->aluno->nome,
                    'nome_social' => $this->aluno->nome_social,
                ];
            }),
            'remetente' => $this->whenLoaded('remetente', function () {
                return [
                    'id' => $this->remetente->id,
                    'nome_completo' => $this->remetente->nome_completo,
                ];
            }),
            'turma' => $this->whenLoaded('turma', function () {
                return [
                    'id' => $this->turma->id,
                    'nome' => $this->turma->nome,
                ];
            }),
        ];
    }
}
