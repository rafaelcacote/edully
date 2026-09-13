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
            if (Schema::connection('shared')->hasTable('cobrancas')) {
                return;
            }

            Schema::connection('shared')->create('cobrancas', function ($table) {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id');
                $table->uuid('aluno_id');
                $table->string('tipo', 20); // mensalidade|evento
                $table->uuid('evento_financeiro_id')->nullable();
                $table->string('titulo');
                $table->text('descricao')->nullable();
                $table->string('referencia', 50)->nullable();
                $table->decimal('valor', 10, 2);
                $table->date('vencimento');
                $table->string('status', 20)->default('pendente'); // pendente|pago|cancelado
                $table->timestamp('pago_em')->nullable();
                $table->text('pago_observacao')->nullable();
                $table->string('boleto_url')->nullable();
                $table->text('pix_copia_cola')->nullable();
                $table->string('pix_chave')->nullable();
                $table->string('pix_qrcode_url')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('tenant_id');
                $table->index(['tenant_id', 'aluno_id']);
                $table->index(['tenant_id', 'status']);
                $table->index(['tenant_id', 'tipo']);
                $table->index('evento_financeiro_id');
                $table->unique(['tenant_id', 'aluno_id', 'tipo', 'referencia']);
            });

            return;
        }

        DB::connection('shared')->statement('CREATE SCHEMA IF NOT EXISTS escola');

        DB::connection('shared')->statement('
            CREATE TABLE IF NOT EXISTS escola.cobrancas (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                tenant_id UUID NOT NULL,
                aluno_id UUID NOT NULL,
                tipo VARCHAR(20) NOT NULL,
                evento_financeiro_id UUID,
                titulo VARCHAR(255) NOT NULL,
                descricao TEXT,
                referencia VARCHAR(50),
                valor NUMERIC(10,2) NOT NULL,
                vencimento DATE NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT \'pendente\',
                pago_em TIMESTAMP,
                pago_observacao TEXT,
                boleto_url TEXT,
                pix_copia_cola TEXT,
                pix_chave VARCHAR(255),
                pix_qrcode_url TEXT,
                created_at TIMESTAMP,
                updated_at TIMESTAMP,
                deleted_at TIMESTAMP
            )
        ');

        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_cobrancas_tenant_id ON escola.cobrancas(tenant_id)');
        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_cobrancas_aluno_id ON escola.cobrancas(tenant_id, aluno_id)');
        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_cobrancas_status ON escola.cobrancas(tenant_id, status)');
        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_cobrancas_tipo ON escola.cobrancas(tenant_id, tipo)');
        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_cobrancas_evento_id ON escola.cobrancas(evento_financeiro_id)');
        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_cobrancas_deleted_at ON escola.cobrancas(deleted_at)');
        DB::connection('shared')->statement('
            CREATE UNIQUE INDEX IF NOT EXISTS idx_cobrancas_unique_referencia
            ON escola.cobrancas (tenant_id, aluno_id, tipo, referencia)
            WHERE referencia IS NOT NULL AND deleted_at IS NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection('shared')->getDriverName();

        if ($driver === 'sqlite') {
            Schema::connection('shared')->dropIfExists('cobrancas');

            return;
        }

        DB::connection('shared')->statement('DROP TABLE IF EXISTS escola.cobrancas');
    }
};
