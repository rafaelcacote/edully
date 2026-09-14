<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection('shared')->getDriverName();

        if ($driver === 'sqlite') {
            if (! Schema::connection('shared')->hasTable('planos')) {
                Schema::connection('shared')->create('planos', function (Blueprint $table) {
                    $table->uuid('id')->primary();
                    $table->string('nome');
                    $table->text('descricao')->nullable();
                    $table->decimal('preco_mensal', 10, 2)->default(0);
                    $table->decimal('preco_anual', 10, 2)->nullable();
                    $table->unsignedInteger('max_alunos')->nullable();
                    $table->unsignedInteger('max_professores')->nullable();
                    $table->unsignedInteger('max_armazenamento_mb')->nullable();
                    $table->json('caracteristicas')->nullable();
                    $table->boolean('ativo')->default(true);
                    $table->timestamps();
                    $table->softDeletes();
                });
            }

            if (! Schema::connection('shared')->hasTable('assinaturas')) {
                Schema::connection('shared')->create('assinaturas', function (Blueprint $table) {
                    $table->uuid('id')->primary();
                    $table->uuid('tenant_id');
                    $table->uuid('plano_id');
                    $table->string('status', 50)->default('pendente');
                    $table->timestamp('data_inicio')->nullable();
                    $table->timestamp('data_fim')->nullable();
                    $table->decimal('valor', 10, 2)->nullable();
                    $table->string('periodo', 20)->nullable();
                    $table->timestamps();
                    $table->softDeletes();

                    $table->index('tenant_id');
                    $table->index('plano_id');
                    $table->index('status');
                });
            }

            return;
        }

        DB::connection('shared')->statement('CREATE SCHEMA IF NOT EXISTS saas');

        DB::connection('shared')->statement("
            CREATE TABLE IF NOT EXISTS saas.planos (
                id UUID PRIMARY KEY,
                nome VARCHAR(255) NOT NULL,
                descricao TEXT,
                preco_mensal NUMERIC(10, 2) NOT NULL DEFAULT 0,
                preco_anual NUMERIC(10, 2),
                max_alunos INTEGER,
                max_professores INTEGER,
                max_armazenamento_mb INTEGER,
                caracteristicas JSONB,
                ativo BOOLEAN NOT NULL DEFAULT true,
                created_at TIMESTAMP,
                updated_at TIMESTAMP,
                deleted_at TIMESTAMP
            )
        ");

        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_planos_ativo ON saas.planos(ativo)');

        DB::connection('shared')->statement("
            CREATE TABLE IF NOT EXISTS saas.assinaturas (
                id UUID PRIMARY KEY,
                tenant_id UUID NOT NULL,
                plano_id UUID NOT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'pendente',
                data_inicio TIMESTAMP,
                data_fim TIMESTAMP,
                valor NUMERIC(10, 2),
                periodo VARCHAR(20),
                created_at TIMESTAMP,
                updated_at TIMESTAMP,
                deleted_at TIMESTAMP,
                CONSTRAINT assinaturas_tenant_id_fkey FOREIGN KEY (tenant_id) REFERENCES shared.tenants(id) ON DELETE CASCADE,
                CONSTRAINT assinaturas_plano_id_fkey FOREIGN KEY (plano_id) REFERENCES saas.planos(id) ON DELETE RESTRICT
            )
        ");

        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_assinaturas_tenant_id ON saas.assinaturas(tenant_id)');
        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_assinaturas_plano_id ON saas.assinaturas(plano_id)');
        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_assinaturas_status ON saas.assinaturas(status)');
    }

    public function down(): void
    {
        $driver = DB::connection('shared')->getDriverName();

        if ($driver === 'sqlite') {
            Schema::connection('shared')->dropIfExists('assinaturas');
            Schema::connection('shared')->dropIfExists('planos');

            return;
        }

        DB::connection('shared')->statement('DROP TABLE IF EXISTS saas.assinaturas');
        DB::connection('shared')->statement('DROP TABLE IF EXISTS saas.planos');
    }
};
