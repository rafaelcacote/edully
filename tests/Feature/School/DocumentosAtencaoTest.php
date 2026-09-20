<?php

use App\Enums\CategoriaDeclaracao;
use App\Enums\StatusDocumento;
use App\Enums\TipoDocumento;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Documento;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * SQLite :memory: uses a separate database per connection, so permission
 * tables migrated on the default connection are not visible on "shared".
 */
function ensureSharedPermissionTablesForDocumentosAtencao(): void
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

function giveDocumentosVisualizarPermission(User $user): void
{
    ensureSharedPermissionTablesForDocumentosAtencao();
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    Permission::findOrCreate('escola.documentos.visualizar', 'web');

    $permission = Permission::on('shared')->firstOrCreate([
        'name' => 'escola.documentos.visualizar',
        'guard_name' => 'web',
    ]);

    if (! $user->permissions()->where('permissions.id', $permission->id)->exists()) {
        $user->permissions()->attach($permission->id);
    }

    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $user->unsetRelation('permissions');
    $user->unsetRelation('roles');
}

it('shares unseen documentos atenção for school users with permission', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['ativo' => true]);
    $admin->tenants()->attach($tenant->id);
    giveDocumentosVisualizarPermission($admin);

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Maria',
        'ativo' => true,
    ]);

    Documento::create([
        'tenant_id' => $tenant->id,
        'aluno_id' => $student->id,
        'criado_por' => $admin->id,
        'tipo' => TipoDocumento::PedidoDeclaracao,
        'status' => StatusDocumento::Enviado,
        'titulo' => 'Pedido de declaração de matrícula',
        'categoria_declaracao' => CategoriaDeclaracao::Matricula,
    ]);

    $request = Request::create('/dashboard');
    $request->setLaravelSession($this->app['session']->driver('array'));
    $request->session()->start();
    $request->session()->put('tenant_id', $tenant->id);
    $request->setUserResolver(static fn () => $admin->fresh());

    $shared = (new HandleInertiaRequests)->share($request);
    $atencao = value($shared['documentos_atencao']);

    expect($atencao)
        ->toMatchArray([
            'count' => 1,
            'has_unseen' => true,
        ]);
});

it('marks documentos atenção as seen after visiting the listing', function () {
    $this->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);

    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['ativo' => true]);
    $admin->tenants()->attach($tenant->id);
    giveDocumentosVisualizarPermission($admin);

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'João',
        'ativo' => true,
    ]);

    Documento::create([
        'tenant_id' => $tenant->id,
        'aluno_id' => $student->id,
        'criado_por' => $admin->id,
        'tipo' => TipoDocumento::PedidoDeclaracao,
        'status' => StatusDocumento::Enviado,
        'titulo' => 'Pedido de declaração',
        'categoria_declaracao' => CategoriaDeclaracao::Frequencia,
    ]);

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->get('/school/documentos');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('school/documentos/Index')
        ->where('documentos.data.0.precisa_atencao', true)
        ->where('documentos.data.0.titulo', 'Pedido de declaração')
    );

    expect(session('documentos_atencao_seen_at'))->not->toBeNull();

    $request = Request::create('/dashboard');
    $request->setLaravelSession(app('session.store'));
    $request->session()->put('tenant_id', $tenant->id);
    $request->setUserResolver(static fn () => $admin->fresh());

    $shared = (new HandleInertiaRequests)->share($request);
    $atencao = value($shared['documentos_atencao']);

    expect($atencao['count'])->toBe(1);
    expect($atencao['has_unseen'])->toBeFalse();
});

it('does not flag documento_escola as needing atenção', function () {
    $this->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);

    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['ativo' => true]);
    $admin->tenants()->attach($tenant->id);

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Ana',
        'ativo' => true,
    ]);

    Documento::create([
        'tenant_id' => $tenant->id,
        'aluno_id' => $student->id,
        'criado_por' => $admin->id,
        'tipo' => TipoDocumento::DocumentoEscola,
        'status' => StatusDocumento::Disponivel,
        'titulo' => 'Declaração emitida',
        'categoria_declaracao' => CategoriaDeclaracao::Matricula,
    ]);

    $response = $this->actingAs($admin)->get('/school/documentos');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->where('documentos.data.0.precisa_atencao', false)
    );
});
