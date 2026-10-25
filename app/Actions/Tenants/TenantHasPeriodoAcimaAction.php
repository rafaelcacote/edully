<?php

namespace App\Actions\Tenants;

use App\Models\Exercise;
use App\Models\Nota;
use App\Models\Tenant;
use App\Models\Test;

class TenantHasPeriodoAcimaAction
{
    public function execute(Tenant $tenant, int $limite): bool
    {
        return Nota::query()
            ->where('tenant_id', $tenant->id)
            ->where('bimestre', '>', $limite)
            ->exists()
            || Test::query()
                ->where('tenant_id', $tenant->id)
                ->where('bimestre', '>', $limite)
                ->exists()
            || Exercise::query()
                ->where('tenant_id', $tenant->id)
                ->where('bimestre', '>', $limite)
                ->exists();
    }
}
