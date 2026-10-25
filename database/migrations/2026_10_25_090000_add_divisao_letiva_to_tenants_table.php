<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('shared')->hasColumn('tenants', 'divisao_letiva')) {
            Schema::connection('shared')->table('tenants', function (Blueprint $table) {
                $table->string('divisao_letiva', 20)->default('bimestral');
            });
        }

        if (DB::connection('shared')->getDriverName() !== 'pgsql') {
            return;
        }

        DB::connection('shared')->statement('ALTER TABLE tenants DROP CONSTRAINT IF EXISTS tenants_divisao_letiva_check');
        DB::connection('shared')->statement("
            ALTER TABLE tenants
            ADD CONSTRAINT tenants_divisao_letiva_check
            CHECK (divisao_letiva IN ('bimestral', 'trimestral'))
        ");
    }

    public function down(): void
    {
        if (DB::connection('shared')->getDriverName() === 'pgsql') {
            DB::connection('shared')->statement('ALTER TABLE tenants DROP CONSTRAINT IF EXISTS tenants_divisao_letiva_check');
        }

        if (Schema::connection('shared')->hasColumn('tenants', 'divisao_letiva')) {
            Schema::connection('shared')->table('tenants', function (Blueprint $table) {
                $table->dropColumn('divisao_letiva');
            });
        }
    }
};
