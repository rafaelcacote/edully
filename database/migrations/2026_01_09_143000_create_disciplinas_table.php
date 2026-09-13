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
            Schema::connection('shared')->create('disciplinas', function ($table) {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id');
                $table->string('nome', 100);
                $table->string('sigla', 20)->nullable();
                $table->text('descricao')->nullable();
                $table->integer('carga_horaria_semanal')->nullable();
                $table->boolean('ativo')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index('tenant_id');
            });

            return;
        }

        // Postgres: criação no schema `escola`
        DB::connection('shared')->statement('CREATE SCHEMA IF NOT EXISTS escola');

        DB::connection('shared')->statement('
            CREATE TABLE IF NOT EXISTS escola.disciplinas (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                tenant_id UUID NOT NULL,
                nome VARCHAR(100) NOT NULL,
                sigla VARCHAR(20),
                descricao TEXT,
                carga_horaria_semanal INTEGER,
                ativo BOOLEAN DEFAULT true,
                created_at TIMESTAMP,
                updated_at TIMESTAMP,
                deleted_at TIMESTAMP
            )
        ');

        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_disciplinas_tenant_id ON escola.disciplinas(tenant_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection('shared')->getDriverName();

        if ($driver === 'sqlite') {
            Schema::connection('shared')->dropIfExists('disciplinas');

            return;
        }

        DB::connection('shared')->statement('DROP TABLE IF EXISTS escola.disciplinas');
    }
};
