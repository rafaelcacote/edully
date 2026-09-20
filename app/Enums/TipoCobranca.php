<?php

namespace App\Enums;

enum TipoCobranca: string
{
    case Mensalidade = 'mensalidade';
    case Evento = 'evento';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Mensalidade => 'Mensalidade',
            self::Evento => 'Evento',
        };
    }
}
