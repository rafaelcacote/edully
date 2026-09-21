<?php

namespace App\Enums;

enum PublicoEventoFinanceiro: string
{
    case Turma = 'turma';
    case Alunos = 'alunos';
    case TodosAtivos = 'todos_ativos';

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
            self::Turma => 'Turma',
            self::Alunos => 'Alunos selecionados',
            self::TodosAtivos => 'Todos os alunos ativos',
        };
    }
}
