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

        // Em SQLite (testes), não existe schema. Criamos tabelas "planas".
        if ($driver === 'sqlite') {
            Schema::connection('shared')->create('turma_disciplinas', function ($table) {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id');
                $table->uuid('turma_id');
                $table->uuid('disciplina_id');
                $table->uuid('professor_id')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index('tenant_id');
                $table->index('turma_id');
                $table->index('disciplina_id');
                $table->index('professor_id');
                $table->unique(['tenant_id', 'turma_id', 'disciplina_id']);
            });

            return;
        }

        // Postgres: criação no schema `escola`
        DB::connection('shared')->statement('CREATE SCHEMA IF NOT EXISTS escola');

        DB::connection('shared')->statement('
            CREATE TABLE IF NOT EXISTS escola.turma_disciplinas (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                tenant_id UUID NOT NULL,
                turma_id UUID NOT NULL,
                disciplina_id UUID NOT NULL,
                professor_id UUID NULL,
                created_at TIMESTAMP,
                UNIQUE (tenant_id, turma_id, disciplina_id)
            )
        ');

        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_turma_disciplinas_tenant_id ON escola.turma_disciplinas(tenant_id)');
        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_turma_disciplinas_turma_id ON escola.turma_disciplinas(turma_id)');
        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_turma_disciplinas_disciplina_id ON escola.turma_disciplinas(disciplina_id)');
        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_turma_disciplinas_professor_id ON escola.turma_disciplinas(professor_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection('shared')->getDriverName();

        if ($driver === 'sqlite') {
            Schema::connection('shared')->dropIfExists('turma_disciplinas');

            return;
        }

        DB::connection('shared')->statement('DROP TABLE IF EXISTS escola.turma_disciplinas');
    }
};
