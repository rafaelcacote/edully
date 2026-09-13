<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotaResource extends JsonResource
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
            'bimestre' => $this->bimestre,
            'nota' => $this->nota !== null ? round((float) $this->nota, 1) : null,
            'comportamento' => $this->comportamento,
            'observacoes' => $this->observacoes,
            'ano_letivo' => $this->ano_letivo,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'disciplina' => $this->whenLoaded('disciplinaRelation', function () {
                if (! $this->disciplinaRelation) {
                    return [
                        'id' => $this->disciplina_id,
                        'nome' => $this->disciplina,
                        'sigla' => null,
                    ];
                }

                return [
                    'id' => $this->disciplinaRelation->id,
                    'nome' => $this->disciplinaRelation->nome,
                    'sigla' => $this->disciplinaRelation->sigla,
                ];
            }, [
                'id' => $this->disciplina_id,
                'nome' => $this->disciplina,
                'sigla' => null,
            ]),
            'turma' => $this->whenLoaded('turma', function () {
                return [
                    'id' => $this->turma->id,
                    'nome' => $this->turma->nome,
                    'serie' => $this->turma->serie,
                    'turma_letra' => $this->turma->turma_letra,
                    'ano_letivo' => $this->turma->ano_letivo,
                ];
            }),
            'professor' => $this->whenLoaded('professor', function () {
                return [
                    'id' => $this->professor->id,
                    'nome_completo' => $this->professor->usuario?->nome_completo,
                ];
            }),
        ];
    }
}
