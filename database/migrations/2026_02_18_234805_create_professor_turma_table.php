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
            Schema::connection('shared')->create('professor_turma', function ($table) {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id');
                $table->uuid('professor_id');
                $table->uuid('turma_id');
                $table->timestamp('created_at')->nullable();

                $table->index('tenant_id');
                $table->index('professor_id');
                $table->index('turma_id');
                $table->unique(['tenant_id', 'professor_id', 'turma_id']);
            });

            return;
        }

        // Postgres: criação no schema `escola`
        DB::connection('shared')->statement('CREATE SCHEMA IF NOT EXISTS escola');

        DB::connection('shared')->statement('
            CREATE TABLE IF NOT EXISTS escola.professor_turma (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                tenant_id UUID NOT NULL,
                professor_id UUID NOT NULL,
                turma_id UUID NOT NULL,
                created_at TIMESTAMP,
                UNIQUE (tenant_id, professor_id, turma_id)
            )
        ');

        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_professor_turma_tenant_id ON escola.professor_turma(tenant_id)');
        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_professor_turma_professor_id ON escola.professor_turma(professor_id)');
        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_professor_turma_turma_id ON escola.professor_turma(turma_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection('shared')->getDriverName();

        if ($driver === 'sqlite') {
            Schema::connection('shared')->dropIfExists('professor_turma');

            return;
        }

        DB::connection('shared')->statement('DROP TABLE IF EXISTS escola.professor_turma');
    }
};
