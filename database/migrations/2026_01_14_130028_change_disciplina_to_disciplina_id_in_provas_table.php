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

        if ($driver === 'sqlite') {
            // SQLite: Não suporta ALTER COLUMN complexo, então precisamos recriar a tabela
            DB::connection('shared')->statement('
                CREATE TABLE provas_new (
                    id TEXT PRIMARY KEY,
                    tenant_id TEXT NOT NULL,
                    professor_id TEXT NOT NULL,
                    turma_id TEXT NOT NULL,
                    disciplina_id TEXT,
                    titulo TEXT NOT NULL,
                    descricao TEXT,
                    data_prova TEXT NOT NULL,
                    horario TEXT,
                    sala TEXT,
                    duracao_minutos INTEGER,
                    created_at TEXT,
                    updated_at TEXT,
                    deleted_at TEXT
                )
            ');

            DB::connection('shared')->statement('
                INSERT INTO provas_new (id, tenant_id, professor_id, turma_id, disciplina_id, titulo, descricao, data_prova, horario, sala, duracao_minutos, created_at, updated_at, deleted_at)
                SELECT id, tenant_id, professor_id, turma_id, NULL, titulo, descricao, data_prova, horario, sala, duracao_minutos, created_at, updated_at, deleted_at
                FROM provas
            ');

            DB::connection('shared')->statement('DROP TABLE provas');
            DB::connection('shared')->statement('ALTER TABLE provas_new RENAME TO provas');

            // Recriar índices
            DB::connection('shared')->statement('CREATE INDEX idx_provas_tenant_id ON provas(tenant_id)');
            DB::connection('shared')->statement('CREATE INDEX idx_provas_professor_id ON provas(professor_id)');
            DB::connection('shared')->statement('CREATE INDEX idx_provas_turma_id ON provas(turma_id)');
            DB::connection('shared')->statement('CREATE INDEX idx_provas_disciplina_id ON provas(disciplina_id)');

            return;
        }

        // Postgres: Remover coluna disciplina e adicionar disciplina_id
        DB::connection('shared')->statement('ALTER TABLE escola.provas DROP COLUMN IF EXISTS disciplina');
        DB::connection('shared')->statement('ALTER TABLE escola.provas ADD COLUMN IF NOT EXISTS disciplina_id UUID');
        DB::connection('shared')->statement('
            ALTER TABLE escola.provas 
            ADD CONSTRAINT IF NOT EXISTS provas_disciplina_id_fkey 
            FOREIGN KEY (disciplina_id) 
            REFERENCES escola.disciplinas(id) 
            ON DELETE SET NULL
        ');
        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_provas_disciplina_id ON escola.provas(disciplina_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection('shared')->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite: Recriar tabela com disciplina
            DB::connection('shared')->statement('
                CREATE TABLE provas_new (
                    id TEXT PRIMARY KEY,
                    tenant_id TEXT NOT NULL,
                    professor_id TEXT NOT NULL,
                    turma_id TEXT NOT NULL,
                    disciplina TEXT NOT NULL,
                    titulo TEXT NOT NULL,
                    descricao TEXT,
                    data_prova TEXT NOT NULL,
                    horario TEXT,
                    sala TEXT,
                    duracao_minutos INTEGER,
                    created_at TEXT,
                    updated_at TEXT,
                    deleted_at TEXT
                )
            ');

            DB::connection('shared')->statement('
                INSERT INTO provas_new (id, tenant_id, professor_id, turma_id, disciplina, titulo, descricao, data_prova, horario, sala, duracao_minutos, created_at, updated_at, deleted_at)
                SELECT id, tenant_id, professor_id, turma_id, "", titulo, descricao, data_prova, horario, sala, duracao_minutos, created_at, updated_at, deleted_at
                FROM provas
            ');

            DB::connection('shared')->statement('DROP TABLE provas');
            DB::connection('shared')->statement('ALTER TABLE provas_new RENAME TO provas');

            // Recriar índices
            DB::connection('shared')->statement('CREATE INDEX idx_provas_tenant_id ON provas(tenant_id)');
            DB::connection('shared')->statement('CREATE INDEX idx_provas_professor_id ON provas(professor_id)');
            DB::connection('shared')->statement('CREATE INDEX idx_provas_turma_id ON provas(turma_id)');

            return;
        }

        // Postgres: Adicionar coluna disciplina e remover disciplina_id
        DB::connection('shared')->statement('ALTER TABLE escola.provas DROP CONSTRAINT IF EXISTS provas_disciplina_id_fkey');
        DB::connection('shared')->statement('DROP INDEX IF EXISTS escola.idx_provas_disciplina_id');
        DB::connection('shared')->statement('ALTER TABLE escola.provas DROP COLUMN IF EXISTS disciplina_id');
        DB::connection('shared')->statement('ALTER TABLE escola.provas ADD COLUMN IF NOT EXISTS disciplina VARCHAR(100) NOT NULL DEFAULT \'\'');
    }
};
