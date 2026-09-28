<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alinha escola.exercicios com o código atual (disciplina_id + tipo_exercicio),
     * no mesmo padrão já aplicado em escola.provas.
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

    public function down(): void
    {
        $driver = DB::connection('shared')->getDriverName();

        if ($driver === 'sqlite') {
            $this->rollbackSqlite();

            return;
        }

        $this->rollbackPostgres();
    }

    private function migrateSqlite(): void
    {
        if (! Schema::connection('shared')->hasTable('exercicios')) {
            return;
        }

        $hasDisciplinaId = Schema::connection('shared')->hasColumn('exercicios', 'disciplina_id');
        $hasTipo = Schema::connection('shared')->hasColumn('exercicios', 'tipo_exercicio');
        $hasDisciplina = Schema::connection('shared')->hasColumn('exercicios', 'disciplina');
        $hasBimestre = Schema::connection('shared')->hasColumn('exercicios', 'bimestre');

        if ($hasDisciplinaId && $hasTipo && ! $hasDisciplina) {
            return;
        }

        DB::connection('shared')->statement('
            CREATE TABLE exercicios_new (
                id TEXT PRIMARY KEY,
                tenant_id TEXT NOT NULL,
                professor_id TEXT NOT NULL,
                turma_id TEXT NOT NULL,
                disciplina_id TEXT,
                titulo TEXT NOT NULL,
                descricao TEXT,
                data_entrega TEXT NOT NULL,
                anexo_url TEXT,
                tipo_exercicio TEXT,
                bimestre INTEGER,
                created_at TEXT,
                updated_at TEXT,
                deleted_at TEXT
            )
        ');

        $bimestreSelect = $hasBimestre ? 'bimestre' : 'NULL';
        $tipoSelect = $hasTipo ? 'tipo_exercicio' : 'NULL';
        $disciplinaIdSelect = $hasDisciplinaId ? 'disciplina_id' : 'NULL';

        DB::connection('shared')->statement("
            INSERT INTO exercicios_new (
                id, tenant_id, professor_id, turma_id, disciplina_id, titulo, descricao,
                data_entrega, anexo_url, tipo_exercicio, bimestre, created_at, updated_at, deleted_at
            )
            SELECT
                id, tenant_id, professor_id, turma_id, {$disciplinaIdSelect}, titulo, descricao,
                data_entrega, anexo_url, {$tipoSelect}, {$bimestreSelect}, created_at, updated_at, deleted_at
            FROM exercicios
        ");

        if ($hasDisciplina && ! $hasDisciplinaId) {
            DB::connection('shared')->statement('
                UPDATE exercicios_new
                SET disciplina_id = (
                    SELECT d.id
                    FROM disciplinas d
                    WHERE d.tenant_id = exercicios_new.tenant_id
                      AND lower(d.nome) = lower((
                          SELECT e.disciplina FROM exercicios e WHERE e.id = exercicios_new.id
                      ))
                    LIMIT 1
                )
                WHERE disciplina_id IS NULL
            ');
        }

        DB::connection('shared')->statement('DROP TABLE exercicios');
        DB::connection('shared')->statement('ALTER TABLE exercicios_new RENAME TO exercicios');

        DB::connection('shared')->statement('CREATE INDEX idx_exercicios_tenant_id ON exercicios(tenant_id)');
        DB::connection('shared')->statement('CREATE INDEX idx_exercicios_professor_id ON exercicios(professor_id)');
        DB::connection('shared')->statement('CREATE INDEX idx_exercicios_turma_id ON exercicios(turma_id)');
        DB::connection('shared')->statement('CREATE INDEX idx_exercicios_disciplina_id ON exercicios(disciplina_id)');
        DB::connection('shared')->statement('CREATE INDEX idx_exercicios_tenant_turma_bimestre ON exercicios(tenant_id, turma_id, bimestre)');
    }

    private function migratePostgres(): void
    {
        DB::connection('shared')->statement('ALTER TABLE escola.exercicios ADD COLUMN IF NOT EXISTS disciplina_id UUID');
        DB::connection('shared')->statement('ALTER TABLE escola.exercicios ADD COLUMN IF NOT EXISTS tipo_exercicio VARCHAR(50)');

        // Backfill disciplina_id a partir do nome legado quando possível.
        DB::connection('shared')->statement("
            DO $$
            BEGIN
                IF EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_schema = 'escola'
                      AND table_name = 'exercicios'
                      AND column_name = 'disciplina'
                ) THEN
                    UPDATE escola.exercicios e
                    SET disciplina_id = d.id
                    FROM escola.disciplinas d
                    WHERE e.disciplina_id IS NULL
                      AND e.tenant_id = d.tenant_id
                      AND lower(e.disciplina) = lower(d.nome);
                END IF;
            END $$;
        ");

        DB::connection('shared')->statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'exercicios_disciplina_id_fkey'
                ) THEN
                    ALTER TABLE escola.exercicios
                    ADD CONSTRAINT exercicios_disciplina_id_fkey
                    FOREIGN KEY (disciplina_id)
                    REFERENCES escola.disciplinas(id)
                    ON DELETE SET NULL;
                END IF;
            END $$;
        ");

        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_exercicios_disciplina_id ON escola.exercicios(disciplina_id)');

        DB::connection('shared')->statement('ALTER TABLE escola.exercicios DROP COLUMN IF EXISTS disciplina');
    }

    private function rollbackSqlite(): void
    {
        if (! Schema::connection('shared')->hasTable('exercicios')) {
            return;
        }

        DB::connection('shared')->statement('
            CREATE TABLE exercicios_old (
                id TEXT PRIMARY KEY,
                tenant_id TEXT NOT NULL,
                professor_id TEXT NOT NULL,
                turma_id TEXT NOT NULL,
                disciplina TEXT NOT NULL DEFAULT \'\',
                titulo TEXT NOT NULL,
                descricao TEXT,
                data_entrega TEXT NOT NULL,
                anexo_url TEXT,
                bimestre INTEGER,
                created_at TEXT,
                updated_at TEXT,
                deleted_at TEXT
            )
        ');

        DB::connection('shared')->statement("
            INSERT INTO exercicios_old (
                id, tenant_id, professor_id, turma_id, disciplina, titulo, descricao,
                data_entrega, anexo_url, bimestre, created_at, updated_at, deleted_at
            )
            SELECT
                e.id, e.tenant_id, e.professor_id, e.turma_id,
                COALESCE((SELECT d.nome FROM disciplinas d WHERE d.id = e.disciplina_id), ''),
                e.titulo, e.descricao, e.data_entrega, e.anexo_url, e.bimestre,
                e.created_at, e.updated_at, e.deleted_at
            FROM exercicios e
        ");

        DB::connection('shared')->statement('DROP TABLE exercicios');
        DB::connection('shared')->statement('ALTER TABLE exercicios_old RENAME TO exercicios');

        DB::connection('shared')->statement('CREATE INDEX idx_exercicios_tenant_id ON exercicios(tenant_id)');
        DB::connection('shared')->statement('CREATE INDEX idx_exercicios_professor_id ON exercicios(professor_id)');
        DB::connection('shared')->statement('CREATE INDEX idx_exercicios_turma_id ON exercicios(turma_id)');
        DB::connection('shared')->statement('CREATE INDEX idx_exercicios_tenant_turma_bimestre ON exercicios(tenant_id, turma_id, bimestre)');
    }

    private function rollbackPostgres(): void
    {
        DB::connection('shared')->statement('ALTER TABLE escola.exercicios DROP CONSTRAINT IF EXISTS exercicios_disciplina_id_fkey');
        DB::connection('shared')->statement('DROP INDEX IF EXISTS escola.idx_exercicios_disciplina_id');
        DB::connection('shared')->statement('ALTER TABLE escola.exercicios DROP COLUMN IF EXISTS disciplina_id');
        DB::connection('shared')->statement('ALTER TABLE escola.exercicios DROP COLUMN IF EXISTS tipo_exercicio');
        DB::connection('shared')->statement("ALTER TABLE escola.exercicios ADD COLUMN IF NOT EXISTS disciplina VARCHAR(100) NOT NULL DEFAULT ''");
    }
};
