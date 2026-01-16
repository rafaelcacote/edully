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
            Schema::connection('shared')->create('mensagens', function ($table) {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id');
                $table->uuid('remetente_id');
                $table->uuid('destinatario_id')->nullable();
                $table->string('titulo', 255);
                $table->text('conteudo');
                $table->boolean('lida')->default(false);
                $table->boolean('ativo')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index('tenant_id');
                $table->index('remetente_id');
                $table->index('destinatario_id');
                $table->index('lida');
            });

            return;
        }

        // Postgres: criação no schema `escola`
        DB::connection('shared')->statement('CREATE SCHEMA IF NOT EXISTS escola');

        DB::connection('shared')->statement('
            CREATE TABLE IF NOT EXISTS escola.mensagens (
                id UUID PRIMARY KEY,
                tenant_id UUID NOT NULL,
                remetente_id UUID NOT NULL,
                destinatario_id UUID,
                titulo VARCHAR(255) NOT NULL,
                conteudo TEXT NOT NULL,
                lida BOOLEAN DEFAULT false,
                ativo BOOLEAN DEFAULT true,
                created_at TIMESTAMP,
                updated_at TIMESTAMP,
                deleted_at TIMESTAMP
            )
        ');

        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_mensagens_tenant_id ON escola.mensagens(tenant_id)');
        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_mensagens_remetente_id ON escola.mensagens(remetente_id)');
        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_mensagens_destinatario_id ON escola.mensagens(destinatario_id)');
        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_mensagens_lida ON escola.mensagens(lida)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection('shared')->getDriverName();

        if ($driver === 'sqlite') {
            Schema::connection('shared')->dropIfExists('mensagens');
        } else {
            DB::connection('shared')->statement('DROP TABLE IF EXISTS escola.mensagens');
        }
    }
};
