<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
            Schema::connection('shared')->create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->morphs('tokenable');
                $table->text('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamps();
            });

            return;
        }

        // Postgres: criação no schema `laravel`
        DB::connection('shared')->statement('CREATE SCHEMA IF NOT EXISTS laravel');

        DB::connection('shared')->statement('
            CREATE TABLE IF NOT EXISTS laravel.personal_access_tokens (
                id BIGSERIAL PRIMARY KEY,
                tokenable_type VARCHAR(255) NOT NULL,
                tokenable_id VARCHAR(255) NOT NULL,
                name TEXT NOT NULL,
                token VARCHAR(64) UNIQUE NOT NULL,
                abilities TEXT,
                last_used_at TIMESTAMP,
                expires_at TIMESTAMP,
                created_at TIMESTAMP,
                updated_at TIMESTAMP
            )
        ');

        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_personal_access_tokens_tokenable ON laravel.personal_access_tokens(tokenable_type, tokenable_id)');
        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_personal_access_tokens_expires_at ON laravel.personal_access_tokens(expires_at)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection('shared')->getDriverName();

        if ($driver === 'sqlite') {
            Schema::connection('shared')->dropIfExists('personal_access_tokens');

            return;
        }

        DB::connection('shared')->statement('DROP TABLE IF EXISTS laravel.personal_access_tokens');
    }
};
