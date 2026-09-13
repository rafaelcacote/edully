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
            Schema::connection('shared')->create('matriculas_turma', function ($table) {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id');
                $table->uuid('aluno_id');
                $table->uuid('turma_id');
                $table->uuid('matricula'); // Matrícula é o próprio ID
                $table->date('data_matricula')->nullable();
                $table->boolean('ativo')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index('tenant_id');
                $table->index('aluno_id');
                $table->index('turma_id');
                $table->unique(['tenant_id', 'matricula']);
            });

            return;
        }

        // Postgres: criação no schema `escola`
        DB::connection('shared')->statement('CREATE SCHEMA IF NOT EXISTS escola');

        DB::connection('shared')->statement('
            CREATE TABLE IF NOT EXISTS escola.matriculas_turma (
                id UUID PRIMARY KEY,
                tenant_id UUID NOT NULL,
                aluno_id UUID NOT NULL,
                turma_id UUID NOT NULL,
                matricula UUID NOT NULL,
                data_matricula DATE,
                ativo BOOLEAN DEFAULT true,
                created_at TIMESTAMP,
                updated_at TIMESTAMP,
                deleted_at TIMESTAMP
            )
        ');

        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_matriculas_turma_tenant_id ON escola.matriculas_turma(tenant_id)');
        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_matriculas_turma_aluno_id ON escola.matriculas_turma(aluno_id)');
        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_matriculas_turma_turma_id ON escola.matriculas_turma(turma_id)');

        // Bancos legados podem não ter matricula/deleted_at (usam status/numero_matricula).
        DB::connection('shared')->statement("
            DO $$
            BEGIN
                IF EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_schema = 'escola' AND table_name = 'matriculas_turma' AND column_name = 'matricula'
                ) AND EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_schema = 'escola' AND table_name = 'matriculas_turma' AND column_name = 'deleted_at'
                ) THEN
                    CREATE UNIQUE INDEX IF NOT EXISTS matriculas_turma_tenant_id_matricula_key
                        ON escola.matriculas_turma(tenant_id, matricula)
                        WHERE deleted_at IS NULL;
                END IF;
            END $$;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection('shared')->getDriverName();

        if ($driver === 'sqlite') {
            Schema::connection('shared')->dropIfExists('matriculas_turma');

            return;
        }

        DB::connection('shared')->statement('DROP INDEX IF EXISTS escola.idx_matriculas_turma_tenant_id');
        DB::connection('shared')->statement('DROP INDEX IF EXISTS escola.idx_matriculas_turma_aluno_id');
        DB::connection('shared')->statement('DROP INDEX IF EXISTS escola.idx_matriculas_turma_turma_id');
        DB::connection('shared')->statement('DROP INDEX IF EXISTS escola.matriculas_turma_tenant_id_matricula_key');
        DB::connection('shared')->statement('DROP TABLE IF EXISTS escola.matriculas_turma');
    }
};
