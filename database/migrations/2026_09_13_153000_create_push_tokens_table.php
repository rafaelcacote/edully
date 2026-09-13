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
            if (! Schema::connection('shared')->hasTable('push_tokens')) {
                Schema::connection('shared')->create('push_tokens', function ($table) {
                    $table->uuid('id')->primary();
                    $table->uuid('usuario_id');
                    $table->string('push_token')->unique();
                    $table->string('platform', 20)->nullable();
                    $table->uuid('tenant_id')->nullable();
                    $table->timestamp('last_used_at')->nullable();
                    $table->timestamps();
                    $table->index('usuario_id');
                });
            }

            return;
        }

        DB::connection('shared')->statement('CREATE SCHEMA IF NOT EXISTS shared');

        DB::connection('shared')->statement("
            CREATE TABLE IF NOT EXISTS shared.push_tokens (
                id UUID PRIMARY KEY,
                usuario_id UUID NOT NULL,
                push_token VARCHAR(255) NOT NULL UNIQUE,
                platform VARCHAR(20) NULL,
                tenant_id UUID NULL,
                last_used_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            )
        ");

        DB::connection('shared')->statement('
            CREATE INDEX IF NOT EXISTS idx_push_tokens_usuario_id
                ON shared.push_tokens(usuario_id)
        ');

        DB::connection('shared')->statement("
            DO \$\$
            BEGIN
                IF EXISTS (
                    SELECT 1 FROM information_schema.tables
                    WHERE table_schema = 'shared' AND table_name = 'usuarios'
                ) AND NOT EXISTS (
                    SELECT 1 FROM information_schema.table_constraints
                    WHERE table_schema = 'shared'
                      AND table_name = 'push_tokens'
                      AND constraint_name = 'push_tokens_usuario_id_fkey'
                ) THEN
                    ALTER TABLE shared.push_tokens
                        ADD CONSTRAINT push_tokens_usuario_id_fkey
                        FOREIGN KEY (usuario_id)
                        REFERENCES shared.usuarios(id)
                        ON DELETE CASCADE;
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
            Schema::connection('shared')->dropIfExists('push_tokens');

            return;
        }

        DB::connection('shared')->statement('
            ALTER TABLE shared.push_tokens
                DROP CONSTRAINT IF EXISTS push_tokens_usuario_id_fkey
        ');
        DB::connection('shared')->statement('DROP TABLE IF EXISTS shared.push_tokens');
    }
};
