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
            $this->migrateSqlite();

            return;
        }

        $this->migratePostgres();
    }

    private function migrateSqlite(): void
    {
        if (! Schema::connection('shared')->hasTable('notas')) {
            return;
        }

        if (! Schema::connection('shared')->hasColumn('notas', 'bimestre')) {
            if (Schema::connection('shared')->hasColumn('notas', 'trimestre')) {
                Schema::connection('shared')->table('notas', function ($table) {
                    $table->integer('bimestre')->nullable();
                });

                DB::connection('shared')->statement('UPDATE notas SET bimestre = trimestre');
            } else {
                Schema::connection('shared')->table('notas', function ($table) {
                    $table->integer('bimestre')->default(1);
                });
            }
        }

        // Recria a tabela no formato final (SQLite limita ALTER COLUMN).
        Schema::connection('shared')->create('notas_new', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('aluno_id');
            $table->uuid('professor_id');
            $table->uuid('turma_id');
            $table->string('disciplina', 100);
            $table->uuid('disciplina_id');
            $table->integer('bimestre');
            $table->decimal('nota', 3, 1);
            $table->integer('frequencia')->nullable();
            $table->string('comportamento')->nullable();
            $table->text('observacoes')->nullable();
            $table->integer('ano_letivo');
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'aluno_id']);
            $table->index('disciplina_id');
            $table->unique(
                ['tenant_id', 'aluno_id', 'turma_id', 'disciplina_id', 'bimestre', 'ano_letivo'],
                'notas_unique_aluno_turma_disciplina_bimestre'
            );
        });

        $hasTrimestre = Schema::connection('shared')->hasColumn('notas', 'trimestre');
        $bimestreExpr = Schema::connection('shared')->hasColumn('notas', 'bimestre')
            ? 'bimestre'
            : ($hasTrimestre ? 'trimestre' : '1');

        DB::connection('shared')->statement("
            INSERT INTO notas_new (
                id, tenant_id, aluno_id, professor_id, turma_id, disciplina, disciplina_id,
                bimestre, nota, frequencia, comportamento, observacoes, ano_letivo,
                created_at, updated_at, deleted_at
            )
            SELECT
                id, tenant_id, aluno_id, professor_id, turma_id, disciplina, disciplina_id,
                {$bimestreExpr}, nota, frequencia, comportamento, observacoes, ano_letivo,
                created_at, updated_at, deleted_at
            FROM notas
            WHERE turma_id IS NOT NULL
              AND disciplina_id IS NOT NULL
        ");

        Schema::connection('shared')->drop('notas');
        Schema::connection('shared')->rename('notas_new', 'notas');
    }

    private function migratePostgres(): void
    {
        DB::connection('shared')->statement('CREATE SCHEMA IF NOT EXISTS escola');

        // Renomeia trimestre -> bimestre quando necessário.
        DB::connection('shared')->statement("
            DO $$
            BEGIN
                IF EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_schema = 'escola' AND table_name = 'notas' AND column_name = 'trimestre'
                ) AND NOT EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_schema = 'escola' AND table_name = 'notas' AND column_name = 'bimestre'
                ) THEN
                    ALTER TABLE escola.notas RENAME COLUMN trimestre TO bimestre;
                ELSIF NOT EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_schema = 'escola' AND table_name = 'notas' AND column_name = 'bimestre'
                ) THEN
                    ALTER TABLE escola.notas ADD COLUMN bimestre INTEGER;
                    UPDATE escola.notas SET bimestre = 1 WHERE bimestre IS NULL;
                    ALTER TABLE escola.notas ALTER COLUMN bimestre SET NOT NULL;
                END IF;
            END $$;
        ");

        DB::connection('shared')->statement('ALTER TABLE escola.notas ADD COLUMN IF NOT EXISTS disciplina_id UUID');
        DB::connection('shared')->statement('ALTER TABLE escola.notas ADD COLUMN IF NOT EXISTS turma_id UUID');

        // Preenche disciplina_id a partir do nome legado quando possível.
        DB::connection('shared')->statement('
            UPDATE escola.notas n
            SET disciplina_id = d.id
            FROM escola.disciplinas d
            WHERE n.disciplina_id IS NULL
              AND n.tenant_id = d.tenant_id
              AND lower(n.disciplina) = lower(d.nome)
              AND d.deleted_at IS NULL
        ');

        // Remove unique/check legados de trimestre (constraint, não só índice).
        DB::connection('shared')->statement('
            ALTER TABLE escola.notas
            DROP CONSTRAINT IF EXISTS notas_tenant_id_aluno_id_professor_id_disciplina_trimestre__key
        ');
        DB::connection('shared')->statement('
            DROP INDEX IF EXISTS escola.notas_tenant_id_aluno_id_professor_id_disciplina_trimestre__key
        ');
        DB::connection('shared')->statement('
            DROP INDEX IF EXISTS escola.notas_tenant_id_aluno_id_turma_id_disciplina_id_bimestre_ano_letivo_unique
        ');
        DB::connection('shared')->statement('
            ALTER TABLE escola.notas DROP CONSTRAINT IF EXISTS notas_trimestre_check
        ');
        DB::connection('shared')->statement('
            ALTER TABLE escola.notas DROP CONSTRAINT IF EXISTS notas_bimestre_check
        ');
        DB::connection('shared')->statement('
            ALTER TABLE escola.notas
            ADD CONSTRAINT notas_bimestre_check CHECK (bimestre >= 1 AND bimestre <= 4)
        ');

        // Só força NOT NULL se não houver órfãos.
        DB::connection('shared')->statement('
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM escola.notas WHERE turma_id IS NULL OR disciplina_id IS NULL
                ) THEN
                    ALTER TABLE escola.notas ALTER COLUMN turma_id SET NOT NULL;
                    ALTER TABLE escola.notas ALTER COLUMN disciplina_id SET NOT NULL;
                END IF;
            END $$;
        ');

        DB::connection('shared')->statement('
            CREATE UNIQUE INDEX IF NOT EXISTS notas_tenant_id_aluno_id_turma_id_disciplina_id_bimestre_ano_letivo_unique
            ON escola.notas (tenant_id, aluno_id, turma_id, disciplina_id, bimestre, ano_letivo)
            WHERE deleted_at IS NULL
        ');

        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_notas_bimestre ON escola.notas(bimestre)');
        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_notas_turma_id ON escola.notas(turma_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection('shared')->getDriverName();

        if ($driver === 'sqlite') {
            // Reversão completa não é necessária para testes.
            return;
        }

        DB::connection('shared')->statement('
            DROP INDEX IF EXISTS escola.notas_tenant_id_aluno_id_turma_id_disciplina_id_bimestre_ano_letivo_unique
        ');

        DB::connection('shared')->statement("
            DO $$
            BEGIN
                IF EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_schema = 'escola' AND table_name = 'notas' AND column_name = 'bimestre'
                ) AND NOT EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_schema = 'escola' AND table_name = 'notas' AND column_name = 'trimestre'
                ) THEN
                    ALTER TABLE escola.notas RENAME COLUMN bimestre TO trimestre;
                END IF;
            END $$;
        ");
    }
};
