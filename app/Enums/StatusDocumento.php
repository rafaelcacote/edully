<?php

namespace App\Enums;

enum StatusDocumento: string
{
    case Enviado = 'enviado';
    case EmAnalise = 'em_analise';
    case Aprovado = 'aprovado';
    case Recusado = 'recusado';
    case Atendido = 'atendido';
    case Disponivel = 'disponivel';
    case Cancelado = 'cancelado';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Status que a secretaria pode aplicar em atestados/pedidos.
     *
     * @return list<string>
     */
    public static function schoolUpdatableValues(): array
    {
        return [
            self::EmAnalise->value,
            self::Aprovado->value,
            self::Recusado->value,
            self::Atendido->value,
            self::Cancelado->value,
        ];
    }

    /**
     * Status que pedem atenção da secretaria/admin.
     *
     * @return list<string>
     */
    public static function attentionValues(): array
    {
        return [
            self::Enviado->value,
            self::EmAnalise->value,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::Enviado => 'Enviado',
            self::EmAnalise => 'Em análise',
            self::Aprovado => 'Aprovado',
            self::Recusado => 'Recusado',
            self::Atendido => 'Atendido',
            self::Disponivel => 'Disponível',
            self::Cancelado => 'Cancelado',
        };
    }
}
