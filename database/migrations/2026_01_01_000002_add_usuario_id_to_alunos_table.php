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
            Schema::connection('shared')->table('alunos', function ($table) {
                if (! Schema::connection('shared')->hasColumn('alunos', 'usuario_id')) {
                    $table->uuid('usuario_id')->nullable()->after('tenant_id');
                    $table->index('usuario_id');
                }
            });

            return;
        }

        // Postgres (schema escola): hasColumn() do Laravel não resolve bem o schema.
        DB::connection('shared')->statement('ALTER TABLE escola.alunos ADD COLUMN IF NOT EXISTS usuario_id UUID');
        DB::connection('shared')->statement('CREATE INDEX IF NOT EXISTS idx_alunos_usuario_id ON escola.alunos(usuario_id)');
    }

    public function down(): void
    {
        $driver = DB::connection('shared')->getDriverName();

        if ($driver === 'sqlite') {
            Schema::connection('shared')->table('alunos', function ($table) {
                if (Schema::connection('shared')->hasColumn('alunos', 'usuario_id')) {
                    $table->dropIndex(['usuario_id']);
                    $table->dropColumn('usuario_id');
                }
            });

            return;
        }

        DB::connection('shared')->statement('DROP INDEX IF EXISTS escola.idx_alunos_usuario_id');
        DB::connection('shared')->statement('ALTER TABLE escola.alunos DROP COLUMN IF EXISTS usuario_id');
    }
};
