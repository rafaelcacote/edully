<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TestResource extends JsonResource
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
            'descricao' => $this->descricao,
            'data_prova' => $this->data_prova->format('Y-m-d'),
            'data_prova_formatted' => $this->data_prova->format('d/m/Y'),
            'horario' => $this->horario,
            'sala' => $this->sala,
            'duracao_minutos' => $this->duracao_minutos,
            'disciplina' => $this->whenLoaded('disciplinaRelation', function () {
                return [
                    'id' => $this->disciplinaRelation->id,
                    'nome' => $this->disciplinaRelation->nome,
                    'sigla' => $this->disciplinaRelation->sigla,
                ];
            }),
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
                    'usuario' => $this->professor->usuario ? [
                        'id' => $this->professor->usuario->id,
                        'nome_completo' => $this->professor->usuario->nome_completo,
                    ] : null,
                ];
            }),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
