<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ensures escola.turmas.turno exists on Postgres.
 *
 * The create migration declares turno, but uses CREATE TABLE IF NOT EXISTS.
 * Databases that already had turmas without the column never received it.
 * App code started writing to turno without a separate ALTER migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection('shared')->getDriverName();

        if ($driver === 'sqlite') {
            if (! Schema::connection('shared')->hasColumn('turmas', 'turno')) {
                Schema::connection('shared')->table('turmas', function ($table) {
                    $table->string('turno', 20)->nullable();
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
                      AND table_name = 'turmas'
                      AND column_name = 'turno'
                ) THEN
                    ALTER TABLE escola.turmas ADD COLUMN turno VARCHAR(20) NULL;
                END IF;
            END \$\$;
        ");
    }

    public function down(): void
    {
        $driver = DB::connection('shared')->getDriverName();

        if ($driver === 'sqlite') {
            if (Schema::connection('shared')->hasColumn('turmas', 'turno')) {
                Schema::connection('shared')->table('turmas', function ($table) {
                    $table->dropColumn('turno');
                });
            }

            return;
        }

        DB::connection('shared')->statement('ALTER TABLE escola.turmas DROP COLUMN IF EXISTS turno');
    }
};
