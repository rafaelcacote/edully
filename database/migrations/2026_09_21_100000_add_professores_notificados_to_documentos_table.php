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
            if (! Schema::connection('shared')->hasColumn('documentos', 'professores_notificados_em')) {
                Schema::connection('shared')->table('documentos', function ($table) {
                    $table->timestamp('professores_notificados_em')->nullable();
                });
            }

            if (! Schema::connection('shared')->hasColumn('documentos', 'professores_notificados_por')) {
                Schema::connection('shared')->table('documentos', function ($table) {
                    $table->uuid('professores_notificados_por')->nullable();
                });
            }

            if (! Schema::connection('shared')->hasColumn('documentos', 'professores_notificados_ids')) {
                Schema::connection('shared')->table('documentos', function ($table) {
                    $table->json('professores_notificados_ids')->nullable();
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
                      AND table_name = 'documentos'
                      AND column_name = 'professores_notificados_em'
                ) THEN
                    ALTER TABLE escola.documentos
                        ADD COLUMN professores_notificados_em TIMESTAMP NULL;
                END IF;

                IF NOT EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_schema = 'escola'
                      AND table_name = 'documentos'
                      AND column_name = 'professores_notificados_por'
                ) THEN
                    ALTER TABLE escola.documentos
                        ADD COLUMN professores_notificados_por UUID NULL;
                END IF;

                IF NOT EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_schema = 'escola'
                      AND table_name = 'documentos'
                      AND column_name = 'professores_notificados_ids'
                ) THEN
                    ALTER TABLE escola.documentos
                        ADD COLUMN professores_notificados_ids JSONB NULL;
                END IF;
            END \$\$;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection('shared')->getDriverName();

        if ($driver === 'sqlite') {
            if (Schema::connection('shared')->hasColumn('documentos', 'professores_notificados_em')) {
                Schema::connection('shared')->table('documentos', function ($table) {
                    $table->dropColumn('professores_notificados_em');
                });
            }

            if (Schema::connection('shared')->hasColumn('documentos', 'professores_notificados_por')) {
                Schema::connection('shared')->table('documentos', function ($table) {
                    $table->dropColumn('professores_notificados_por');
                });
            }

            if (Schema::connection('shared')->hasColumn('documentos', 'professores_notificados_ids')) {
                Schema::connection('shared')->table('documentos', function ($table) {
                    $table->dropColumn('professores_notificados_ids');
                });
            }

            return;
        }

        DB::connection('shared')->statement('
            ALTER TABLE escola.documentos
                DROP COLUMN IF EXISTS professores_notificados_em,
                DROP COLUMN IF EXISTS professores_notificados_por,
                DROP COLUMN IF EXISTS professores_notificados_ids
        ');
    }
};
