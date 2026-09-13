<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection('shared')->getDriverName();

        if ($driver === 'sqlite') {
            if (! Schema::connection('shared')->hasColumn('mensagens', 'conversa_id')) {
                Schema::connection('shared')->table('mensagens', function ($table) {
                    $table->uuid('conversa_id')->nullable()->index();
                });
            }

            if (! Schema::connection('shared')->hasColumn('mensagens', 'mensagem_pai_id')) {
                Schema::connection('shared')->table('mensagens', function ($table) {
                    $table->uuid('mensagem_pai_id')->nullable()->index();
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
                      AND column_name = 'conversa_id'
                ) THEN
                    ALTER TABLE escola.mensagens ADD COLUMN conversa_id UUID NULL;
                END IF;

                IF NOT EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_schema = 'escola'
                      AND table_name = 'mensagens'
                      AND column_name = 'mensagem_pai_id'
                ) THEN
                    ALTER TABLE escola.mensagens ADD COLUMN mensagem_pai_id UUID NULL;
                END IF;
            END \$\$;
        ");

        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_mensagens_conversa_id ON escola.mensagens(conversa_id)');
        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_mensagens_mensagem_pai_id ON escola.mensagens(mensagem_pai_id)');
    }

    public function down(): void
    {
        $driver = DB::connection('shared')->getDriverName();

        if ($driver === 'sqlite') {
            if (Schema::connection('shared')->hasColumn('mensagens', 'conversa_id')) {
                Schema::connection('shared')->table('mensagens', function ($table) {
                    $table->dropColumn('conversa_id');
                });
            }

            if (Schema::connection('shared')->hasColumn('mensagens', 'mensagem_pai_id')) {
                Schema::connection('shared')->table('mensagens', function ($table) {
                    $table->dropColumn('mensagem_pai_id');
                });
            }

            return;
        }

        DB::connection('shared')->statement('DROP INDEX IF EXISTS escola.idx_mensagens_conversa_id');
        DB::connection('shared')->statement('DROP INDEX IF EXISTS escola.idx_mensagens_mensagem_pai_id');
        DB::connection('shared')->statement('ALTER TABLE escola.mensagens DROP COLUMN IF EXISTS conversa_id');
        DB::connection('shared')->statement('ALTER TABLE escola.mensagens DROP COLUMN IF EXISTS mensagem_pai_id');
    }
};
