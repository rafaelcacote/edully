<?php

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionsAndRolesSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * SQLite :memory: uses a separate database per connection, so permission
 * tables migrated on the default connection are not visible on "shared".
 */
function ensureSharedPermissionTablesForAvisos(): void
{
    $connection = 'shared';

    if (! Schema::connection($connection)->hasTable('permissions')) {
        Schema::connection($connection)->create('permissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });
    }

    if (! Schema::connection($connection)->hasTable('roles')) {
        Schema::connection($connection)->create('roles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });
    }

    if (! Schema::connection($connection)->hasTable('model_has_permissions')) {
        Schema::connection($connection)->create('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->uuid('model_id');
            $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
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

    if (! Schema::connection($connection)->hasTable('role_has_permissions')) {
        Schema::connection($connection)->create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_permission_id_role_id_primary');
        });
    }
}

function giveSharedPermission(User $user, string $permissionName): void
{
    ensureSharedPermissionTablesForAvisos();
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    // Keep default + shared in sync for Spatie lookups in tests.
    Permission::findOrCreate($permissionName, 'web');

    $permission = Permission::on('shared')->firstOrCreate([
        'name' => $permissionName,
        'guard_name' => 'web',
    ]);

    if (! $user->permissions()->where('permissions.id', $permission->id)->exists()) {
        $user->permissions()->attach($permission->id);
    }

    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $user->unsetRelation('permissions');
    $user->unsetRelation('roles');
}

it('seeds professor with avisos visualizar only', function () {
    $this->seed(PermissionsAndRolesSeeder::class);

    $role = Role::findByName('Professor', 'web');

    expect($role->hasPermissionTo('escola.avisos.visualizar'))->toBeTrue();
    expect($role->hasPermissionTo('escola.avisos.criar'))->toBeFalse();
    expect($role->hasPermissionTo('escola.avisos.editar'))->toBeFalse();
    expect($role->hasPermissionTo('escola.avisos.excluir'))->toBeFalse();
});

it('forbids professors from creating comunicados', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $user->tenants()->attach($tenant->id);
    giveSharedPermission($user, 'escola.avisos.visualizar');

    $this->actingAs($user)
        ->get(route('school.avisos.create'))
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('school.avisos.store'), [
            'titulo' => 'Tentativa',
            'conteudo' => 'Não deveria criar',
            'prioridade' => 'normal',
            'publico_alvo' => 'todos',
            'publicado' => true,
        ])
        ->assertForbidden();
});

it('allows professors to view comunicados index', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $user->tenants()->attach($tenant->id);
    giveSharedPermission($user, 'escola.avisos.visualizar');

    $this->actingAs($user)
        ->get(route('school.avisos.index'))
        ->assertSuccessful();
});
