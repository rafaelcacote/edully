<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection('shared')->getDriverName();

        if ($driver === 'sqlite') {
            if (! Schema::connection('shared')->hasColumn('provas', 'bimestre')) {
                Schema::connection('shared')->table('provas', function ($table) {
                    $table->integer('bimestre')->nullable();
                    $table->index(['tenant_id', 'turma_id', 'bimestre'], 'idx_provas_tenant_turma_bimestre');
                });
            }

            if (! Schema::connection('shared')->hasColumn('exercicios', 'bimestre')) {
                Schema::connection('shared')->table('exercicios', function ($table) {
                    $table->integer('bimestre')->nullable();
                    $table->index(['tenant_id', 'turma_id', 'bimestre'], 'idx_exercicios_tenant_turma_bimestre');
                });
            }

            return;
        }

        DB::connection('shared')->statement('ALTER TABLE escola.provas ADD COLUMN IF NOT EXISTS bimestre INTEGER');
        DB::connection('shared')->statement('ALTER TABLE escola.exercicios ADD COLUMN IF NOT EXISTS bimestre INTEGER');

        DB::connection('shared')->statement('
            ALTER TABLE escola.provas DROP CONSTRAINT IF EXISTS provas_bimestre_check
        ');
        DB::connection('shared')->statement('
            ALTER TABLE escola.provas
            ADD CONSTRAINT provas_bimestre_check CHECK (bimestre IS NULL OR (bimestre >= 1 AND bimestre <= 4))
        ');

        DB::connection('shared')->statement('
            ALTER TABLE escola.exercicios DROP CONSTRAINT IF EXISTS exercicios_bimestre_check
        ');
        DB::connection('shared')->statement('
            ALTER TABLE escola.exercicios
            ADD CONSTRAINT exercicios_bimestre_check CHECK (bimestre IS NULL OR (bimestre >= 1 AND bimestre <= 4))
        ');

        DB::connection('shared')->statement('
            CREATE INDEX IF NOT EXISTS idx_provas_tenant_turma_bimestre
            ON escola.provas (tenant_id, turma_id, bimestre)
        ');
        DB::connection('shared')->statement('
            CREATE INDEX IF NOT EXISTS idx_exercicios_tenant_turma_bimestre
            ON escola.exercicios (tenant_id, turma_id, bimestre)
        ');
    }

    public function down(): void
    {
        $driver = DB::connection('shared')->getDriverName();

        if ($driver === 'sqlite') {
            if (Schema::connection('shared')->hasColumn('provas', 'bimestre')) {
                Schema::connection('shared')->table('provas', function ($table) {
                    $table->dropIndex('idx_provas_tenant_turma_bimestre');
                    $table->dropColumn('bimestre');
                });
            }

            if (Schema::connection('shared')->hasColumn('exercicios', 'bimestre')) {
                Schema::connection('shared')->table('exercicios', function ($table) {
                    $table->dropIndex('idx_exercicios_tenant_turma_bimestre');
                    $table->dropColumn('bimestre');
                });
            }

            return;
        }

        DB::connection('shared')->statement('DROP INDEX IF EXISTS escola.idx_provas_tenant_turma_bimestre');
        DB::connection('shared')->statement('DROP INDEX IF EXISTS escola.idx_exercicios_tenant_turma_bimestre');
        DB::connection('shared')->statement('ALTER TABLE escola.provas DROP CONSTRAINT IF EXISTS provas_bimestre_check');
        DB::connection('shared')->statement('ALTER TABLE escola.exercicios DROP CONSTRAINT IF EXISTS exercicios_bimestre_check');
        DB::connection('shared')->statement('ALTER TABLE escola.provas DROP COLUMN IF EXISTS bimestre');
        DB::connection('shared')->statement('ALTER TABLE escola.exercicios DROP COLUMN IF EXISTS bimestre');
    }
};
