<?php

namespace App\Enums;

enum Parentesco: string
{
    case Pai = 'Pai';
    case Mae = 'Mãe';
    case Avo = 'Avô';
    case AvoFem = 'Avó';
    case Tio = 'Tio';
    case Tia = 'Tia';
    case Padrasto = 'Padrasto';
    case Madrasta = 'Madrasta';
    case Irmao = 'Irmão';
    case Irma = 'Irmã';
    case TutorLegal = 'Tutor(a) legal';
    case ResponsavelLegal = 'Responsável legal';
    case Outro = 'Outro';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
