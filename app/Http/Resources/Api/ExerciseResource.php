<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExerciseResource extends JsonResource
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
            'data_entrega' => $this->data_entrega->format('Y-m-d'),
            'data_entrega_formatted' => $this->data_entrega->format('d/m/Y'),
            'anexo_url' => $this->anexo_url,
            'tipo_exercicio' => $this->tipo_exercicio,
            'disciplina' => $this->whenLoaded('disciplinaRelation', function () {
                return [
                    'id' => $this->disciplinaRelation->id,
                    'nome' => $this->disciplinaRelation->nome,
                    'sigla' => $this->disciplinaRelation->sigla,
                ];
            }, function () {
                return [
                    'nome' => $this->disciplina,
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
