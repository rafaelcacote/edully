<?php

namespace App\Enums;

enum DivisaoLetiva: string
{
    case Bimestral = 'bimestral';
    case Trimestral = 'trimestral';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function quantidade(): int
    {
        return match ($this) {
            self::Bimestral => 4,
            self::Trimestral => 3,
        };
    }

    public function unidade(): string
    {
        return match ($this) {
            self::Bimestral => 'bimestre',
            self::Trimestral => 'trimestre',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Bimestral => 'Bimestre (4 períodos)',
            self::Trimestral => 'Trimestre escolar (3 períodos)',
        };
    }

    public function rotulo(int $periodo): string
    {
        return $periodo.'º '.ucfirst($this->unidade());
    }

    public function atual(?int $mes = null): int
    {
        $mes ??= (int) now()->month;

        return match ($this) {
            self::Bimestral => match (true) {
                $mes <= 3 => 1,
                $mes <= 6 => 2,
                $mes <= 9 => 3,
                default => 4,
            },
            self::Trimestral => match (true) {
                $mes <= 4 => 1,
                $mes <= 8 => 2,
                default => 3,
            },
        };
    }
}
