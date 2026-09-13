<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $ultima = $this->ultima_mensagem;

        $createdAt = $this->created_at;
        $updatedAt = $this->updated_at;

        return [
            'conversa_id' => $this->conversa_id,
            'unread_count' => $this->unread_count,
            'messages_count' => $this->messages_count,
            'created_at' => $this->formatDateTime($createdAt) ?? $ultima?->created_at?->toIso8601String(),
            'updated_at' => $this->formatDateTime($updatedAt) ?? $ultima?->created_at?->toIso8601String(),
            'aluno' => $this->aluno ? [
                'id' => $this->aluno->id,
                'nome' => $this->aluno->nome,
                'nome_social' => $this->aluno->nome_social,
            ] : null,
            'participantes' => $this->participantes,
            'ultima_mensagem' => $ultima ? new MessageResource($ultima) : null,
            // Campos espelhados da última mensagem para compatibilidade com listagens antigas do app.
            'id' => $ultima?->id,
            'aluno_id' => $ultima?->aluno_id ?? $this->aluno?->id,
            'titulo' => $ultima?->titulo,
            'conteudo' => $ultima?->conteudo,
            'tipo' => $ultima?->tipo,
            'prioridade' => $ultima?->prioridade,
            'anexo_url' => $ultima?->anexo_url,
            'lida' => ($this->unread_count ?? 0) === 0,
            'lida_em' => $ultima?->lida_em?->toIso8601String(),
            'remetente' => $ultima?->relationLoaded('remetente') && $ultima->remetente ? [
                'id' => $ultima->remetente->id,
                'nome_completo' => $ultima->remetente->nome_completo,
                'avatar_url' => $ultima->remetente->avatar_url,
                'foto_url' => $ultima->remetente->avatar_url,
            ] : null,
            'destinatario' => $ultima?->relationLoaded('destinatario') && $ultima->destinatario ? [
                'id' => $ultima->destinatario->id,
                'nome_completo' => $ultima->destinatario->nome_completo,
                'avatar_url' => $ultima->destinatario->avatar_url,
                'foto_url' => $ultima->destinatario->avatar_url,
            ] : null,
        ];
    }

    protected function formatDateTime(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        if (is_string($value) && $value !== '') {
            try {
                return \Illuminate\Support\Carbon::parse($value)->toIso8601String();
            } catch (\Throwable) {
                return $value;
            }
        }

        return null;
    }
}
