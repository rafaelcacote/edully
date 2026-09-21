<?php

namespace App\Enums;

enum StatusEventoFinanceiro: string
{
    case Rascunho = 'rascunho';
    case Publicado = 'publicado';
    case Encerrado = 'encerrado';

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
            self::Rascunho => 'Rascunho',
            self::Publicado => 'Publicado',
            self::Encerrado => 'Encerrado',
        };
    }
}
