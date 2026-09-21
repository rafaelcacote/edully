<?php

namespace App\Support;

class Bimestre
{
    /**
     * Retorna o bimestre atual com base no mês do calendário.
     * 1: Jan–Mar, 2: Abr–Jun, 3: Jul–Set, 4: Out–Dez.
     */
    public static function atual(): int
    {
        $mes = (int) now()->month;

        return match (true) {
            $mes <= 3 => 1,
            $mes <= 6 => 2,
            $mes <= 9 => 3,
            default => 4,
        };
    }

    public static function label(int $bimestre): string
    {
        return match ($bimestre) {
            1 => '1º bimestre',
            2 => '2º bimestre',
            3 => '3º bimestre',
            4 => '4º bimestre',
            default => "{$bimestre}º bimestre",
        };
    }
}
