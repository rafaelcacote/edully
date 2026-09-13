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
            if (Schema::connection('shared')->hasTable('eventos_financeiros')) {
                return;
            }

            Schema::connection('shared')->create('eventos_financeiros', function ($table) {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id');
                $table->string('titulo');
                $table->text('descricao')->nullable();
                $table->decimal('valor', 10, 2);
                $table->date('vencimento');
                $table->string('publico', 20); // turma|alunos|todos_ativos
                $table->uuid('turma_id')->nullable();
                $table->string('status', 20)->default('rascunho'); // rascunho|publicado|encerrado
                $table->string('pix_copia_cola')->nullable();
                $table->string('pix_chave')->nullable();
                $table->string('pix_qrcode_url')->nullable();
                $table->string('boleto_url')->nullable();
                $table->timestamp('publicado_em')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('tenant_id');
                $table->index(['tenant_id', 'status']);
                $table->index('turma_id');
            });

            Schema::connection('shared')->create('evento_financeiro_alunos', function ($table) {
                $table->uuid('id')->primary();
                $table->uuid('evento_financeiro_id');
                $table->uuid('aluno_id');
                $table->timestamp('created_at')->nullable();

                $table->unique(['evento_financeiro_id', 'aluno_id']);
                $table->index('aluno_id');
            });

            return;
        }

        DB::connection('shared')->statement('CREATE SCHEMA IF NOT EXISTS escola');

        DB::connection('shared')->statement('
            CREATE TABLE IF NOT EXISTS escola.eventos_financeiros (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                tenant_id UUID NOT NULL,
                titulo VARCHAR(255) NOT NULL,
                descricao TEXT,
                valor NUMERIC(10,2) NOT NULL,
                vencimento DATE NOT NULL,
                publico VARCHAR(20) NOT NULL,
                turma_id UUID,
                status VARCHAR(20) NOT NULL DEFAULT \'rascunho\',
                pix_copia_cola TEXT,
                pix_chave VARCHAR(255),
                pix_qrcode_url TEXT,
                boleto_url TEXT,
                publicado_em TIMESTAMP,
                created_at TIMESTAMP,
                updated_at TIMESTAMP,
                deleted_at TIMESTAMP
            )
        ');

        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_eventos_financeiros_tenant_id ON escola.eventos_financeiros(tenant_id)');
        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_eventos_financeiros_status ON escola.eventos_financeiros(tenant_id, status)');
        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_eventos_financeiros_turma_id ON escola.eventos_financeiros(turma_id)');
        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_eventos_financeiros_deleted_at ON escola.eventos_financeiros(deleted_at)');

        DB::connection('shared')->statement('
            CREATE TABLE IF NOT EXISTS escola.evento_financeiro_alunos (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                evento_financeiro_id UUID NOT NULL,
                aluno_id UUID NOT NULL,
                created_at TIMESTAMP,
                UNIQUE (evento_financeiro_id, aluno_id)
            )
        ');

        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_evento_financeiro_alunos_aluno_id ON escola.evento_financeiro_alunos(aluno_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection('shared')->getDriverName();

        if ($driver === 'sqlite') {
            Schema::connection('shared')->dropIfExists('evento_financeiro_alunos');
            Schema::connection('shared')->dropIfExists('eventos_financeiros');

            return;
        }

        DB::connection('shared')->statement('DROP TABLE IF EXISTS escola.evento_financeiro_alunos');
        DB::connection('shared')->statement('DROP TABLE IF EXISTS escola.eventos_financeiros');
    }
};
