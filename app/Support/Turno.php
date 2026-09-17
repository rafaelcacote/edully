<?php

namespace App\Support;

class Turno
{
    public const MATUTINO = 'matutino';

    public const VESPERTINO = 'vespertino';

    public const INTEGRAL = 'integral';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::MATUTINO,
            self::VESPERTINO,
            self::INTEGRAL,
        ];
    }

    public static function label(?string $turno): string
    {
        return match ($turno) {
            self::MATUTINO => 'Matutino',
            self::VESPERTINO => 'Vespertino',
            self::INTEGRAL => 'Integral',
            default => $turno ? (string) $turno : '—',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::MATUTINO => self::label(self::MATUTINO),
            self::VESPERTINO => self::label(self::VESPERTINO),
            self::INTEGRAL => self::label(self::INTEGRAL),
        ];
    }
}
