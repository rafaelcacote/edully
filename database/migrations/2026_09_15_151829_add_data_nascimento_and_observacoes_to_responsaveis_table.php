<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection('shared')->getDriverName();

        if ($driver === 'sqlite') {
            Schema::connection('shared')->table('responsaveis', function (Blueprint $table) {
                $table->date('data_nascimento')->nullable();
                $table->text('observacoes')->nullable();
            });

            return;
        }

        DB::connection('shared')->statement('ALTER TABLE escola.responsaveis ADD COLUMN IF NOT EXISTS data_nascimento DATE');
        DB::connection('shared')->statement('ALTER TABLE escola.responsaveis ADD COLUMN IF NOT EXISTS observacoes TEXT');
    }

    public function down(): void
    {
        $driver = DB::connection('shared')->getDriverName();

        if ($driver === 'sqlite') {
            Schema::connection('shared')->table('responsaveis', function (Blueprint $table) {
                $table->dropColumn(['data_nascimento', 'observacoes']);
            });

            return;
        }

        DB::connection('shared')->statement('ALTER TABLE escola.responsaveis DROP COLUMN IF EXISTS data_nascimento');
        DB::connection('shared')->statement('ALTER TABLE escola.responsaveis DROP COLUMN IF EXISTS observacoes');
    }
};
