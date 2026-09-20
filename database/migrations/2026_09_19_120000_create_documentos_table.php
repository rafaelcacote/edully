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
            Schema::connection('shared')->create('documentos', function ($table) {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id');
                $table->uuid('aluno_id');
                $table->uuid('criado_por')->nullable();
                $table->string('tipo', 50);
                $table->string('status', 50);
                $table->string('titulo');
                $table->text('descricao')->nullable();
                $table->date('data_inicio')->nullable();
                $table->date('data_fim')->nullable();
                $table->string('categoria_declaracao', 50)->nullable();
                $table->string('anexo_url', 2048)->nullable();
                $table->string('anexo_resposta_url', 2048)->nullable();
                $table->text('motivo_recusa')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('tenant_id');
                $table->index('aluno_id');
                $table->index('criado_por');
                $table->index('tipo');
                $table->index('status');
            });

            return;
        }

        DB::connection('shared')->statement('CREATE SCHEMA IF NOT EXISTS escola');
        DB::connection('shared')->statement('CREATE SCHEMA IF NOT EXISTS shared');

        DB::connection('shared')->statement("
            DO \$\$ BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM pg_type t
                    JOIN pg_namespace n ON n.oid = t.typnamespace
                    WHERE t.typname = 'tipo_documento' AND n.nspname = 'shared'
                ) THEN
                    CREATE TYPE shared.tipo_documento AS ENUM (
                        'atestado',
                        'pedido_declaracao',
                        'documento_escola'
                    );
                END IF;
            END \$\$;
        ");

        DB::connection('shared')->statement("
            DO \$\$ BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM pg_type t
                    JOIN pg_namespace n ON n.oid = t.typnamespace
                    WHERE t.typname = 'status_documento' AND n.nspname = 'shared'
                ) THEN
                    CREATE TYPE shared.status_documento AS ENUM (
                        'enviado',
                        'em_analise',
                        'aprovado',
                        'recusado',
                        'atendido',
                        'disponivel',
                        'cancelado'
                    );
                END IF;
            END \$\$;
        ");

        DB::connection('shared')->statement("
            DO \$\$ BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM pg_type t
                    JOIN pg_namespace n ON n.oid = t.typnamespace
                    WHERE t.typname = 'categoria_declaracao' AND n.nspname = 'shared'
                ) THEN
                    CREATE TYPE shared.categoria_declaracao AS ENUM (
                        'matricula',
                        'frequencia',
                        'transferencia',
                        'conclusao',
                        'outro'
                    );
                END IF;
            END \$\$;
        ");

        DB::connection('shared')->statement('
            CREATE TABLE IF NOT EXISTS escola.documentos (
                id UUID PRIMARY KEY,
                tenant_id UUID NOT NULL,
                aluno_id UUID NOT NULL,
                criado_por UUID,
                tipo shared.tipo_documento NOT NULL,
                status shared.status_documento NOT NULL,
                titulo VARCHAR(255) NOT NULL,
                descricao TEXT,
                data_inicio DATE,
                data_fim DATE,
                categoria_declaracao shared.categoria_declaracao,
                anexo_url VARCHAR(2048),
                anexo_resposta_url VARCHAR(2048),
                motivo_recusa TEXT,
                created_at TIMESTAMP,
                updated_at TIMESTAMP,
                deleted_at TIMESTAMP
            )
        ');

        DB::connection('shared')->statement('
            CREATE INDEX IF NOT EXISTS idx_documentos_tenant_id ON escola.documentos(tenant_id)
        ');

        DB::connection('shared')->statement('
            CREATE INDEX IF NOT EXISTS idx_documentos_aluno_id ON escola.documentos(aluno_id)
        ');

        DB::connection('shared')->statement('
            CREATE INDEX IF NOT EXISTS idx_documentos_criado_por ON escola.documentos(criado_por)
        ');

        DB::connection('shared')->statement('
            CREATE INDEX IF NOT EXISTS idx_documentos_tipo ON escola.documentos(tipo)
        ');

        DB::connection('shared')->statement('
            CREATE INDEX IF NOT EXISTS idx_documentos_status ON escola.documentos(status)
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection('shared')->getDriverName();

        if ($driver === 'sqlite') {
            Schema::connection('shared')->dropIfExists('documentos');

            return;
        }

        DB::connection('shared')->statement('DROP TABLE IF EXISTS escola.documentos');
        DB::connection('shared')->statement('DROP TYPE IF EXISTS shared.categoria_declaracao');
        DB::connection('shared')->statement('DROP TYPE IF EXISTS shared.status_documento');
        DB::connection('shared')->statement('DROP TYPE IF EXISTS shared.tipo_documento');
    }
};
