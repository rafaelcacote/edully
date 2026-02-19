<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::connection('shared')->getDriverName();

        if ($driver !== 'sqlite') {
            // PostgreSQL: adicionar DEFAULT gen_random_uuid() na coluna id
            DB::connection('shared')->statement('
                ALTER TABLE escola.professor_turma
                ALTER COLUMN id SET DEFAULT gen_random_uuid()
            ');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection('shared')->getDriverName();

        if ($driver !== 'sqlite') {
            // PostgreSQL: remover DEFAULT da coluna id
            DB::connection('shared')->statement('
                ALTER TABLE escola.professor_turma
                ALTER COLUMN id DROP DEFAULT
            ');
        }
    }
};
