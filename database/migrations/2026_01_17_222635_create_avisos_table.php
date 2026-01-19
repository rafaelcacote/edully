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
            Schema::connection('shared')->create('avisos', function ($table) {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id');
                $table->uuid('criado_por')->nullable();
                $table->string('titulo');
                $table->text('conteudo');
                $table->string('prioridade', 20)->default('normal');
                $table->string('publico_alvo', 50)->default('todos');
                $table->string('anexo_url', 2048)->nullable();
                $table->boolean('publicado')->default(false);
                $table->timestamp('publicado_em')->nullable();
                $table->timestamp('expira_em')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('tenant_id');
                $table->index('criado_por');
                $table->index('publicado');
                $table->index('prioridade');
                $table->index('publico_alvo');
            });

            return;
        }

        // Postgres: criação no schema `escola`.
        DB::connection('shared')->statement('CREATE SCHEMA IF NOT EXISTS escola');

        DB::connection('shared')->statement('
            CREATE TABLE IF NOT EXISTS escola.avisos (
                id UUID PRIMARY KEY,
                tenant_id UUID NOT NULL,
                criado_por UUID,
                titulo VARCHAR(255) NOT NULL,
                conteudo TEXT NOT NULL,
                prioridade VARCHAR(20) DEFAULT \'normal\',
                publico_alvo VARCHAR(50) DEFAULT \'todos\',
                anexo_url VARCHAR(2048),
                publicado BOOLEAN DEFAULT FALSE,
                publicado_em TIMESTAMP,
                expira_em TIMESTAMP,
                created_at TIMESTAMP,
                updated_at TIMESTAMP,
                deleted_at TIMESTAMP
            )
        ');

        DB::connection('shared')->statement('
            CREATE INDEX IF NOT EXISTS idx_avisos_tenant_id ON escola.avisos(tenant_id)
        ');

        DB::connection('shared')->statement('
            CREATE INDEX IF NOT EXISTS idx_avisos_criado_por ON escola.avisos(criado_por)
        ');

        DB::connection('shared')->statement('
            CREATE INDEX IF NOT EXISTS idx_avisos_publicado ON escola.avisos(publicado)
        ');

        DB::connection('shared')->statement('
            CREATE INDEX IF NOT EXISTS idx_avisos_prioridade ON escola.avisos(prioridade)
        ');

        DB::connection('shared')->statement('
            CREATE INDEX IF NOT EXISTS idx_avisos_publico_alvo ON escola.avisos(publico_alvo)
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection('shared')->getDriverName();

        if ($driver === 'sqlite') {
            Schema::connection('shared')->dropIfExists('avisos');

            return;
        }

        DB::connection('shared')->statement('DROP TABLE IF EXISTS escola.avisos');
    }
};
