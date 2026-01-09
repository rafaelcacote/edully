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
            Schema::connection('shared')->create('professor_disciplinas', function ($table) {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id');
                $table->uuid('professor_id');
                $table->uuid('disciplina_id');
                $table->timestamp('created_at')->nullable();

                $table->index('tenant_id');
                $table->index('professor_id');
                $table->index('disciplina_id');
                $table->unique(['tenant_id', 'professor_id', 'disciplina_id']);
            });

            return;
        }

        // Postgres: criação no schema `escola`
        DB::connection('shared')->statement('CREATE SCHEMA IF NOT EXISTS escola');

        DB::connection('shared')->statement('
            CREATE TABLE IF NOT EXISTS escola.professor_disciplinas (
                id UUID PRIMARY KEY,
                tenant_id UUID NOT NULL,
                professor_id UUID NOT NULL,
                disciplina_id UUID NOT NULL,
                created_at TIMESTAMP,
                UNIQUE (tenant_id, professor_id, disciplina_id)
            )
        ');

        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_professor_disciplinas_tenant_id ON escola.professor_disciplinas(tenant_id)');
        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_professor_disciplinas_professor_id ON escola.professor_disciplinas(professor_id)');
        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_professor_disciplinas_disciplina_id ON escola.professor_disciplinas(disciplina_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection('shared')->getDriverName();

        if ($driver === 'sqlite') {
            Schema::connection('shared')->dropIfExists('professor_disciplinas');

            return;
        }

        DB::connection('shared')->statement('DROP TABLE IF EXISTS escola.professor_disciplinas');
    }
};
