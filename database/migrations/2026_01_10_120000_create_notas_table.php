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
            if (Schema::connection('shared')->hasTable('notas')) {
                return;
            }

            Schema::connection('shared')->create('notas', function ($table) {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id');
                $table->uuid('aluno_id');
                $table->uuid('professor_id');
                $table->uuid('turma_id')->nullable();
                $table->string('disciplina', 100);
                $table->uuid('disciplina_id')->nullable();
                $table->integer('trimestre');
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
            });

            return;
        }

        DB::connection('shared')->statement('CREATE SCHEMA IF NOT EXISTS escola');

        DB::connection('shared')->statement('
            CREATE TABLE IF NOT EXISTS escola.notas (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                tenant_id UUID NOT NULL,
                aluno_id UUID NOT NULL,
                professor_id UUID NOT NULL,
                turma_id UUID,
                disciplina VARCHAR(100) NOT NULL,
                disciplina_id UUID,
                trimestre INTEGER NOT NULL,
                nota NUMERIC(3,1) NOT NULL,
                frequencia INTEGER,
                comportamento VARCHAR(50),
                observacoes TEXT,
                ano_letivo INTEGER NOT NULL,
                created_at TIMESTAMP,
                updated_at TIMESTAMP,
                deleted_at TIMESTAMP
            )
        ');

        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_notas_tenant_id ON escola.notas(tenant_id)');
        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_notas_aluno_id ON escola.notas(tenant_id, aluno_id)');
        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_notas_disciplina_id ON escola.notas(disciplina_id)');
        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_notas_deleted_at ON escola.notas(deleted_at)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection('shared')->getDriverName();

        if ($driver === 'sqlite') {
            Schema::connection('shared')->dropIfExists('notas');

            return;
        }

        DB::connection('shared')->statement('DROP TABLE IF EXISTS escola.notas');
    }
};
