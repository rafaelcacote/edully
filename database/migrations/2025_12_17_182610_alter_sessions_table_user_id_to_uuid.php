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
        // Usar SQL direto para evitar problemas com constraints
        // Remover foreign key se existir
        DB::statement("
            DO \$\$
            BEGIN
                IF EXISTS (
                    SELECT 1 FROM information_schema.table_constraints 
                    WHERE constraint_name = 'sessions_user_id_foreign'
                ) THEN
                    ALTER TABLE sessions DROP CONSTRAINT sessions_user_id_foreign;
                END IF;
            END \$\$;
        ");
        
        // Remover índice se existir
        DB::statement('DROP INDEX IF EXISTS sessions_user_id_index');
        
        // Dropar a coluna
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });
        
        // Recriar a coluna como uuid
        Schema::table('sessions', function (Blueprint $table) {
            $table->uuid('user_id')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });
        
        // Limpar dados antes de reverter
        DB::table('sessions')->whereNotNull('user_id')->update(['user_id' => null]);
        
        DB::statement('ALTER TABLE sessions ALTER COLUMN user_id TYPE bigint USING NULL');
        
        Schema::table('sessions', function (Blueprint $table) {
            $table->index('user_id');
        });
    }
};
