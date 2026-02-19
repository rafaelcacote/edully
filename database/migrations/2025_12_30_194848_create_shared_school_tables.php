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
            Schema::connection('shared')->create('alunos', function ($table) {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id');
                $table->uuid('usuario_id')->nullable();
                $table->string('nome');
                $table->string('nome_social')->nullable();
                $table->string('foto_url')->nullable();
                $table->date('data_nascimento')->nullable();
                $table->text('informacoes_medicas')->nullable();
                $table->boolean('ativo')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index('tenant_id');
                $table->index('usuario_id');
            });

            Schema::connection('shared')->create('responsaveis', function ($table) {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id');
                $table->uuid('usuario_id');
                $table->string('parentesco', 50)->nullable();
                $table->string('cpf', 20)->nullable();
                $table->string('profissao', 100)->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('tenant_id');
                $table->index('usuario_id');
                $table->unique('cpf');
            });

            Schema::connection('shared')->create('professores', function ($table) {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id');
                $table->string('nome_completo');
                $table->string('cpf', 11)->nullable();
                $table->date('data_nascimento')->nullable();
                $table->string('telefone', 20)->nullable();
                $table->string('email')->nullable();
                $table->string('formacao')->nullable();
                $table->string('especializacao')->nullable();
                $table->boolean('ativo')->default(true);
                $table->text('observacoes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('tenant_id');
                $table->index('cpf');
                $table->index('email');
            });

            Schema::connection('shared')->create('aluno_responsavel', function ($table) {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id');
                $table->uuid('aluno_id');
                $table->uuid('responsavel_id');
                $table->boolean('principal')->default(false);
                $table->timestamp('created_at')->nullable();

                $table->unique(['tenant_id', 'aluno_id', 'responsavel_id']);
                $table->index('tenant_id');
                $table->index('aluno_id');
                $table->index('responsavel_id');
            });

            return;
        }

        // Postgres: criação no schema `escola` (alunos/responsáveis) + FK para `shared.usuarios`.
        DB::connection('shared')->statement('CREATE SCHEMA IF NOT EXISTS escola');

        DB::connection('shared')->statement('
            CREATE TABLE IF NOT EXISTS escola.alunos (
                id UUID PRIMARY KEY,
                tenant_id UUID NOT NULL,
                usuario_id UUID,
                nome VARCHAR(255) NOT NULL,
                nome_social VARCHAR(255),
                foto_url TEXT,
                data_nascimento DATE,
                informacoes_medicas TEXT,
                ativo BOOLEAN DEFAULT true,
                created_at TIMESTAMP,
                updated_at TIMESTAMP,
                deleted_at TIMESTAMP
            )
        ');

        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_alunos_tenant_id ON escola.alunos(tenant_id)');
        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_alunos_usuario_id ON escola.alunos(usuario_id)');

        DB::connection('shared')->statement('
            CREATE TABLE IF NOT EXISTS escola.responsaveis (
                id UUID PRIMARY KEY,
                tenant_id UUID NOT NULL,
                usuario_id UUID NOT NULL,
                parentesco VARCHAR(50),
                cpf VARCHAR(20),
                profissao VARCHAR(100),
                created_at TIMESTAMP,
                updated_at TIMESTAMP,
                deleted_at TIMESTAMP
            )
        ');

        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_responsaveis_tenant_id ON escola.responsaveis(tenant_id)');
        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_responsaveis_usuario_id ON escola.responsaveis(usuario_id)');
        DB::connection('shared')->statement('CREATE UNIQUE INDEX IF NOT EXISTS responsaveis_cpf_key ON escola.responsaveis(cpf)');

        DB::connection('shared')->statement('
            CREATE TABLE IF NOT EXISTS escola.professores (
                id UUID PRIMARY KEY,
                tenant_id UUID NOT NULL,
                nome_completo VARCHAR(255) NOT NULL,
                cpf VARCHAR(11),
                data_nascimento DATE,
                telefone VARCHAR(20),
                email VARCHAR(255),
                formacao VARCHAR(255),
                especializacao VARCHAR(255),
                ativo BOOLEAN DEFAULT true,
                observacoes TEXT,
                created_at TIMESTAMP,
                updated_at TIMESTAMP,
                deleted_at TIMESTAMP
            )
        ');

        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_professores_tenant_id ON escola.professores(tenant_id)');
        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_professores_cpf ON escola.professores(cpf)');
        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_professores_email ON escola.professores(email)');

        DB::connection('shared')->statement('
            CREATE TABLE IF NOT EXISTS escola.aluno_responsavel (
                id UUID PRIMARY KEY,
                tenant_id UUID NOT NULL,
                aluno_id UUID NOT NULL,
                responsavel_id UUID NOT NULL,
                principal BOOLEAN DEFAULT false,
                created_at TIMESTAMP,
                UNIQUE (tenant_id, aluno_id, responsavel_id)
            )
        ');

        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_aluno_responsavel_tenant_id ON escola.aluno_responsavel(tenant_id)');
        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_aluno_responsavel_aluno_id ON escola.aluno_responsavel(aluno_id)');
        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_aluno_responsavel_responsavel_id ON escola.aluno_responsavel(responsavel_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection('shared')->getDriverName();

        if ($driver === 'sqlite') {
            Schema::connection('shared')->dropIfExists('aluno_responsavel');
            Schema::connection('shared')->dropIfExists('professores');
            Schema::connection('shared')->dropIfExists('responsaveis');
            Schema::connection('shared')->dropIfExists('alunos');

            return;
        }

        DB::connection('shared')->statement('DROP TABLE IF EXISTS escola.aluno_responsavel');
        DB::connection('shared')->statement('DROP TABLE IF EXISTS escola.professores');
        DB::connection('shared')->statement('DROP TABLE IF EXISTS escola.responsaveis');
        DB::connection('shared')->statement('DROP TABLE IF EXISTS escola.alunos');
    }
};
