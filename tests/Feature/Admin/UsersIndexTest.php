<?php

use App\Http\Middleware\EnsureUserIsAdminGeral;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

/**
 * SQLite :memory: uses a separate database per connection, so permission
 * tables migrated on the default connection are not visible on "shared".
 */
function ensureSharedPermissionTablesForUsersIndex(): void
{
    $connection = 'shared';

    if (! Schema::connection($connection)->hasTable('roles')) {
        Schema::connection($connection)->create('roles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });
    }

    if (! Schema::connection($connection)->hasTable('model_has_roles')) {
        Schema::connection($connection)->create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->uuid('model_id');
            $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
        });
    }
}

beforeEach(function () {
    $this->withoutMiddleware([
        EnsureUserIsAdminGeral::class,
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);

    ensureSharedPermissionTablesForUsersIndex();
});

it('filters users by tenant on the admin users index', function () {
    $admin = User::factory()->create(['ativo' => true]);

    $escolaA = Tenant::factory()->create(['nome' => 'Escola Alpha']);
    $escolaB = Tenant::factory()->create(['nome' => 'Escola Beta']);

    $professor = User::factory()->create([
        'nome_completo' => 'Professor Alpha',
        'ativo' => true,
    ]);
    $professor->tenants()->attach($escolaA->id);

    $responsavel = User::factory()->create([
        'nome_completo' => 'Responsavel Alpha',
        'ativo' => true,
    ]);
    $responsavel->tenants()->attach($escolaA->id);

    $adminEscola = User::factory()->create([
        'nome_completo' => 'Admin Escola Alpha',
        'ativo' => true,
    ]);
    $adminEscola->tenants()->attach($escolaA->id);

    $outroTenant = User::factory()->create([
        'nome_completo' => 'Professor Beta',
        'ativo' => true,
    ]);
    $outroTenant->tenants()->attach($escolaB->id);

    $this->actingAs($admin)
        ->get(route('users.index', ['tenant_id' => $escolaA->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('users/Index')
            ->where('filters.tenant_id', $escolaA->id)
            ->where('selectedTenant.id', $escolaA->id)
            ->where('selectedTenant.name', 'Escola Alpha')
            ->where('selectedTenant.users_count', 3)
            ->has('tenants')
            ->has('users.data', 3)
            ->where('users.data.0.nome_completo', 'Admin Escola Alpha')
            ->where('users.data.1.nome_completo', 'Professor Alpha')
            ->where('users.data.2.nome_completo', 'Responsavel Alpha')
        );
});

it('searches users inside the selected tenant by name', function () {
    $admin = User::factory()->create(['ativo' => true]);
    $escola = Tenant::factory()->create(['nome' => 'Escola Alpha']);

    $giselle = User::factory()->create([
        'nome_completo' => 'Giselle Almeida',
        'ativo' => true,
    ]);
    $giselle->tenants()->attach($escola->id);

    $outro = User::factory()->create([
        'nome_completo' => 'Benedito Dias',
        'ativo' => true,
    ]);
    $outro->tenants()->attach($escola->id);

    $this->actingAs($admin)
        ->get(route('users.index', [
            'tenant_id' => $escola->id,
            'search' => 'Giselle',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('users/Index')
            ->has('users.data', 1)
            ->where('users.data.0.nome_completo', 'Giselle Almeida')
        );
});

it('does not list users until a tenant is selected', function () {
    $admin = User::factory()->create(['ativo' => true]);
    $escolaA = Tenant::factory()->create(['nome' => 'Escola Alpha']);
    $escolaB = Tenant::factory()->create(['nome' => 'Escola Beta']);

    $professor = User::factory()->create([
        'nome_completo' => 'Professor Alpha',
        'ativo' => true,
    ]);
    $professor->tenants()->attach($escolaA->id);

    $outro = User::factory()->create([
        'nome_completo' => 'Professor Beta',
        'ativo' => true,
    ]);
    $outro->tenants()->attach($escolaB->id);

    $this->actingAs($admin)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('users/Index')
            ->where('selectedTenant', null)
            ->where('users', null)
            ->has('tenants', 2)
            ->where('tenants.0.name', 'Escola Alpha')
            ->where('tenants.0.users_count', 1)
            ->where('tenants.1.name', 'Escola Beta')
            ->where('tenants.1.users_count', 1)
        );
});

it('redirects to the school picker when the tenant does not exist', function () {
    $admin = User::factory()->create(['ativo' => true]);

    $this->actingAs($admin)
        ->get(route('users.index', ['tenant_id' => '01a0cfe6-db93-7392-b01d-f34d11e44583']))
        ->assertRedirect(route('users.index'));
});

it('prefills the school when creating a user from a selected tenant', function () {
    $admin = User::factory()->create(['ativo' => true]);
    $tenant = Tenant::factory()->create(['nome' => 'Escola Horizonte']);

    $this->actingAs($admin)
        ->get(route('users.create', ['tenant_id' => $tenant->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('users/Create')
            ->where('defaultTenantId', $tenant->id)
        );
});

it('includes tenants options on users index', function () {
    $admin = User::factory()->create(['ativo' => true]);
    $tenant = Tenant::factory()->create(['nome' => 'Escola Horizonte']);

    $this->actingAs($admin)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('users/Index')
            ->where('users', null)
            ->has('tenants', 1)
            ->where('tenants.0.id', $tenant->id)
            ->where('tenants.0.name', 'Escola Horizonte')
            ->where('tenants.0.users_count', 0)
        );
});
