<?php

namespace App\Enums;

enum CategoriaDeclaracao: string
{
    case Matricula = 'matricula';
    case Frequencia = 'frequencia';
    case Transferencia = 'transferencia';
    case Conclusao = 'conclusao';
    case Outro = 'outro';

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
            self::Matricula => 'Declaração de matrícula',
            self::Frequencia => 'Declaração de frequência',
            self::Transferencia => 'Declaração de transferência',
            self::Conclusao => 'Declaração de conclusão',
            self::Outro => 'Outro',
        };
    }
}
