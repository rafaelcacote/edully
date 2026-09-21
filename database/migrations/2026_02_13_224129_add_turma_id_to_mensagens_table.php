<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * @deprecated Prefer 2026_09_16_225103_ensure_turma_id_on_mensagens_table.
 * Kept idempotent so fresh installs and re-runs stay safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection('shared')->getDriverName();

        if ($driver === 'sqlite') {
            if (! Schema::connection('shared')->hasColumn('mensagens', 'turma_id')) {
                Schema::connection('shared')->table('mensagens', function ($table) {
                    $table->uuid('turma_id')->nullable()->index();
                });
            }

            return;
        }

        DB::connection('shared')->statement("
            DO \$\$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_schema = 'escola'
                      AND table_name = 'mensagens'
                      AND column_name = 'turma_id'
                ) THEN
                    ALTER TABLE escola.mensagens ADD COLUMN turma_id UUID NULL;
                END IF;
            END \$\$;
        ");

        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_mensagens_turma_id ON escola.mensagens(turma_id)');
    }

    public function down(): void
    {
        // Intentionally empty: column may be required by application code.
        // Use 2026_09_16_225103 down() if you need to drop it deliberately.
    }
};
