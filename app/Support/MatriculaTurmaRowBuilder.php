<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MatriculaTurmaRowBuilder
{
    /** @var list<string>|null */
    private static ?array $columns = null;

    /**
     * Monta a linha de insert para escola.matriculas_turma.
     *
     * Ambientes podem divergir: schema oficial tem `matricula` NOT NULL;
     * alguns bancos legados não têm a coluna. Incluímos só o que existir.
     *
     * @param  array{
     *     tenant_id: string,
     *     aluno_id: string,
     *     turma_id: string,
     *     data_matricula?: string|null,
     *     status?: string,
     *     updated_at?: mixed
     * }  $attributes
     * @return array<string, mixed>
     */
    public static function forInsert(array $attributes): array
    {
        $matriculaId = (string) Str::uuid();
        $columns = self::columns();

        $row = [
            'id' => $matriculaId,
            'tenant_id' => $attributes['tenant_id'],
            'aluno_id' => $attributes['aluno_id'],
            'turma_id' => $attributes['turma_id'],
            'data_matricula' => $attributes['data_matricula'] ?? now()->toDateString(),
            'status' => $attributes['status'] ?? 'ativo',
            'created_at' => now(),
        ];

        if (array_key_exists('updated_at', $attributes)) {
            $row['updated_at'] = $attributes['updated_at'];
        }

        if (in_array('matricula', $columns, true)) {
            $row['matricula'] = $matriculaId;
        }

        if (in_array('ativo', $columns, true)) {
            $row['ativo'] = true;
        }

        return $row;
    }

    /**
     * @return list<string>
     */
    public static function columns(): array
    {
        if (self::$columns !== null) {
            return self::$columns;
        }

        $connection = DB::connection('shared');
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            self::$columns = Schema::connection('shared')->getColumnListing('matriculas_turma');

            return self::$columns;
        }

        self::$columns = collect($connection->select(
            "SELECT column_name
             FROM information_schema.columns
             WHERE table_schema = 'escola'
               AND table_name = 'matriculas_turma'"
        ))->pluck('column_name')->map(fn ($name) => (string) $name)->all();

        return self::$columns;
    }

    public static function flushColumnCache(): void
    {
        self::$columns = null;
    }
}
