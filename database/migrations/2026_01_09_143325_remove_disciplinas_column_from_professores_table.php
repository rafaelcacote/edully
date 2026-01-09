<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::connection('shared')->getDriverName();

        if ($driver === 'sqlite') {
            Schema::connection('shared')->table('professores', function ($table) {
                $table->dropColumn('disciplinas');
            });

            return;
        }

        // Postgres: remover coluna disciplinas
        DB::connection('shared')->statement('
            ALTER TABLE escola.professores 
            DROP COLUMN IF EXISTS disciplinas
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection('shared')->getDriverName();

        if ($driver === 'sqlite') {
            Schema::connection('shared')->table('professores', function ($table) {
                $table->text('disciplinas')->nullable();
            });

            return;
        }

        // Postgres: adicionar coluna disciplinas de volta
        DB::connection('shared')->statement('
            ALTER TABLE escola.professores 
            ADD COLUMN IF NOT EXISTS disciplinas TEXT
        ');
    }
};
