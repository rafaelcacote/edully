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
            if (! Schema::connection('shared')->hasColumn('mensagens', 'lida_em')) {
                Schema::connection('shared')->table('mensagens', function ($table) {
                    $table->timestamp('lida_em')->nullable();
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
                      AND column_name = 'lida_em'
                ) THEN
                    ALTER TABLE escola.mensagens ADD COLUMN lida_em TIMESTAMP NULL;
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
            if (Schema::connection('shared')->hasColumn('mensagens', 'lida_em')) {
                Schema::connection('shared')->table('mensagens', function ($table) {
                    $table->dropColumn('lida_em');
                });
            }

            return;
        }

        DB::connection('shared')->statement('
            ALTER TABLE escola.mensagens DROP COLUMN IF EXISTS lida_em
        ');
    }
};
