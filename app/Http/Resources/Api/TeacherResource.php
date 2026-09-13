<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherResource extends JsonResource
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
            'usuario_id' => $this->usuario_id,
            'matricula' => $this->matricula,
            'especializacao' => $this->especializacao,
            'nome_completo' => $this->usuario?->nome_completo,
            'avatar_url' => $this->usuario?->avatar_url,
            'foto_url' => $this->usuario?->avatar_url,
            'turmas' => $this->whenLoaded('turmas', function () {
                return $this->turmas->map(fn ($turma) => [
                    'id' => $turma->id,
                    'nome' => $turma->nome,
                    'serie' => $turma->serie,
                    'turma_letra' => $turma->turma_letra,
                    'ano_letivo' => $turma->ano_letivo,
                ])->values();
            }),
            'disciplinas' => $this->when(
                $this->relationLoaded('disciplinas_aluno') || $this->relationLoaded('disciplinas'),
                function () {
                    $source = $this->relationLoaded('disciplinas_aluno')
                        ? $this->getRelation('disciplinas_aluno')
                        : $this->disciplinas;

                    return collect($source)->map(fn ($disciplina) => [
                        'id' => $disciplina->id,
                        'nome' => $disciplina->nome,
                        'sigla' => $disciplina->sigla,
                    ])->values();
                }
            ),
        ];
    }
}
