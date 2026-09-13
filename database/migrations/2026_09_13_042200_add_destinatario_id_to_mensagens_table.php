<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Schema legado de mensagens pode existir sem destinatario_id.
     * A migration create_mensagens usa CREATE TABLE IF NOT EXISTS e
     * não adiciona a coluna em tabelas já existentes.
     */
    public function up(): void
    {
        $driver = DB::connection('shared')->getDriverName();

        if ($driver === 'sqlite') {
            if (! Schema::connection('shared')->hasColumn('mensagens', 'destinatario_id')) {
                Schema::connection('shared')->table('mensagens', function ($table) {
                    $table->uuid('destinatario_id')->nullable()->index();
                });
            }

            return;
        }

        DB::connection('shared')->statement("
            DO \$\$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_schema = 'escola'
                      AND table_name = 'mensagens'
                      AND column_name = 'destinatario_id'
                ) THEN
                    ALTER TABLE escola.mensagens
                        ADD COLUMN destinatario_id UUID NULL;
                END IF;
            END \$\$;
        ");

        DB::connection('shared')->statement('
            CREATE INDEX IF NOT EXISTS idx_mensagens_destinatario_id
                ON escola.mensagens(destinatario_id)
        ');

        // FK opcional: só cria se a tabela de usuários existir e a constraint ainda não existir.
        DB::connection('shared')->statement("
            DO \$\$
            BEGIN
                IF EXISTS (
                    SELECT 1 FROM information_schema.tables
                    WHERE table_schema = 'shared' AND table_name = 'usuarios'
                ) AND NOT EXISTS (
                    SELECT 1 FROM information_schema.table_constraints
                    WHERE table_schema = 'escola'
                      AND table_name = 'mensagens'
                      AND constraint_name = 'mensagens_destinatario_id_fkey'
                ) THEN
                    ALTER TABLE escola.mensagens
                        ADD CONSTRAINT mensagens_destinatario_id_fkey
                        FOREIGN KEY (destinatario_id)
                        REFERENCES shared.usuarios(id)
                        ON DELETE SET NULL;
                END IF;
            END \$\$;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection('shared')->getDriverName();

        if ($driver === 'sqlite') {
            if (Schema::connection('shared')->hasColumn('mensagens', 'destinatario_id')) {
                Schema::connection('shared')->table('mensagens', function ($table) {
                    $table->dropColumn('destinatario_id');
                });
            }

            return;
        }

        DB::connection('shared')->statement('
            ALTER TABLE escola.mensagens
                DROP CONSTRAINT IF EXISTS mensagens_destinatario_id_fkey
        ');
        DB::connection('shared')->statement('
            DROP INDEX IF EXISTS escola.idx_mensagens_destinatario_id
        ');
        DB::connection('shared')->statement('
            ALTER TABLE escola.mensagens DROP COLUMN IF EXISTS destinatario_id
        ');
    }
};
