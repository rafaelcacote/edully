<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Responsavel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Spatie\Permission\Models\Role;

/**
 * SQLite :memory: uses a separate database per connection, so permission
 * tables migrated on the default connection are not visible on "shared".
 */
function ensureSharedPermissionTablesForParents(): void
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

it('creates usuario and responsavel with role on store', function () {
    $this->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);

    ensureSharedPermissionTablesForParents();

    Role::on('shared')->firstOrCreate([
        'name' => 'Responsável Aluno',
        'guard_name' => 'web',
    ]);

    $tenant = Tenant::factory()->create();
    $authUser = User::factory()->create();
    $authUser->tenants()->attach($tenant->id);

    $payload = [
        'nome_completo' => 'Ana Souza',
        'cpf' => '12345678909',
        'email' => 'ana@example.com',
        'telefone' => '11999999999',
        'parentesco' => 'Mãe',
        'profissao' => 'Advogada',
        'data_nascimento' => '1985-03-15',
        'observacoes' => 'Contato preferencial no período da manhã',
        'ativo' => '1',
    ];

    $response = $this->actingAs($authUser)->post('/school/parents', $payload);

    $response->assertRedirect(route('school.parents.index', absolute: false));

    $this->assertDatabaseHas('usuarios', [
        'cpf' => '12345678909',
        'nome_completo' => 'Ana Souza',
        'email' => 'ana@example.com',
    ], 'shared');

    $user = User::query()->where('cpf', '12345678909')->firstOrFail();

    expect($user->hasRole('Responsável Aluno'))->toBeTrue();

    $this->assertDatabaseHas('usuario_tenants', [
        'usuario_id' => $user->id,
        'tenant_id' => $tenant->id,
    ], 'shared');

    $this->assertDatabaseHas('responsaveis', [
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'parentesco' => 'Mãe',
        'profissao' => 'Advogada',
        'observacoes' => 'Contato preferencial no período da manhã',
    ], 'shared');

    $parent = Responsavel::query()->where('usuario_id', $user->id)->firstOrFail();
    expect(optional($parent->data_nascimento)->toDateString())->toBe('1985-03-15');
});

it('rejects invalid parentesco on store', function () {
    $this->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);

    Role::findOrCreate('Responsável Aluno', 'web');

    $tenant = Tenant::factory()->create();
    $authUser = User::factory()->create();
    $authUser->tenants()->attach($tenant->id);

    $response = $this->actingAs($authUser)->post('/school/parents', [
        'nome_completo' => 'Ana Souza',
        'parentesco' => 'Vizinho',
        'ativo' => '1',
    ]);

    $response->assertSessionHasErrors(['parentesco']);
});

it('rejects duplicate cpf on store', function () {
    $this->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);

    Role::findOrCreate('Responsável Aluno', 'web');

    $tenant = Tenant::factory()->create();
    $authUser = User::factory()->create();
    $authUser->tenants()->attach($tenant->id);

    User::factory()->create([
        'cpf' => '30447265067',
    ]);

    $response = $this->actingAs($authUser)->post('/school/parents', [
        'nome_completo' => 'Outro Responsável',
        'cpf' => '304.472.650-67',
        'email' => 'outro@example.com',
        'parentesco' => 'Pai',
        'ativo' => '1',
    ]);

    $response->assertSessionHasErrors(['cpf']);
    expect(session('errors')->get('cpf')[0])
        ->toBe('Este CPF já está cadastrado no sistema e não pode ser utilizado novamente.');
});

it('checks if cpf already exists', function () {
    $this->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);

    $tenant = Tenant::factory()->create();
    $authUser = User::factory()->create();
    $authUser->tenants()->attach($tenant->id);

    User::factory()->create([
        'cpf' => '39053344705',
    ]);

    $existsResponse = $this->actingAs($authUser)->postJson('/school/parents/check-cpf', [
        'cpf' => '390.533.447-05',
    ]);

    $existsResponse->assertSuccessful()->assertJson([
        'exists' => true,
        'valid' => true,
    ]);

    $availableResponse = $this->actingAs($authUser)->postJson('/school/parents/check-cpf', [
        'cpf' => '52998224725',
    ]);

    $availableResponse->assertSuccessful()->assertJson([
        'exists' => false,
        'valid' => true,
    ]);
});

it('rejects duplicate email on store', function () {
    $this->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);

    Role::findOrCreate('Responsável Aluno', 'web');

    $tenant = Tenant::factory()->create();
    $authUser = User::factory()->create();
    $authUser->tenants()->attach($tenant->id);

    User::factory()->create([
        'email' => 'maria@hotmail.com',
    ]);

    $response = $this->actingAs($authUser)->post('/school/parents', [
        'nome_completo' => 'Maria do Carmo',
        'cpf' => '98883946065',
        'email' => 'Maria@Hotmail.com',
        'parentesco' => 'Mãe',
        'ativo' => '1',
    ]);

    $response->assertSessionHasErrors(['email']);
    expect(session('errors')->get('email')[0])
        ->toBe('Este e-mail já está cadastrado no sistema e não pode ser utilizado novamente.');
});

it('checks if email already exists', function () {
    $this->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);

    $tenant = Tenant::factory()->create();
    $authUser = User::factory()->create();
    $authUser->tenants()->attach($tenant->id);

    $existing = User::factory()->create([
        'email' => 'maria@hotmail.com',
    ]);

    $existsResponse = $this->actingAs($authUser)->postJson('/school/parents/check-email', [
        'email' => 'maria@hotmail.com',
    ]);

    $existsResponse->assertSuccessful()->assertJson([
        'exists' => true,
    ]);

    $ignoredResponse = $this->actingAs($authUser)->postJson('/school/parents/check-email', [
        'email' => 'maria@hotmail.com',
        'ignore_user_id' => $existing->id,
    ]);

    $ignoredResponse->assertSuccessful()->assertJson([
        'exists' => false,
    ]);

    $availableResponse = $this->actingAs($authUser)->postJson('/school/parents/check-email', [
        'email' => 'disponivel@example.com',
    ]);

    $availableResponse->assertSuccessful()->assertJson([
        'exists' => false,
    ]);
});

it('can update parent parentesco', function () {
    $this->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);

    $tenant = Tenant::factory()->create();
    $authUser = User::factory()->create();
    $authUser->tenants()->attach($tenant->id);

    $usuario = User::factory()->create([
        'nome_completo' => 'Carlos Lima',
        'cpf' => '11122233344',
        'email' => 'carlos@example.com',
    ]);
    $usuario->tenants()->attach($tenant->id);

    $parent = Responsavel::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $usuario->id,
        'cpf' => $usuario->cpf,
        'parentesco' => 'Pai',
        'profissao' => 'Engenheiro',
        'data_nascimento' => '1980-01-10',
        'observacoes' => 'Observação antiga',
    ]);

    $response = $this->actingAs($authUser)->patch("/school/parents/{$parent->id}", [
        'nome_completo' => 'Carlos Lima',
        'email' => 'carlos@example.com',
        'telefone' => '11988887777',
        'parentesco' => 'Padrasto',
        'profissao' => 'Engenheiro',
        'data_nascimento' => '1980-05-20',
        'observacoes' => 'Nova observação do responsável',
        'ativo' => '1',
    ]);

    $response->assertRedirect(route('school.parents.edit', $parent, absolute: false));

    $this->assertDatabaseHas('responsaveis', [
        'id' => $parent->id,
        'parentesco' => 'Padrasto',
        'observacoes' => 'Nova observação do responsável',
    ], 'shared');

    expect(optional($parent->fresh()->data_nascimento)->toDateString())->toBe('1980-05-20');
});

it('includes data_nascimento and observacoes on edit page', function () {
    $this->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);

    $tenant = Tenant::factory()->create();
    $authUser = User::factory()->create();
    $authUser->tenants()->attach($tenant->id);

    $usuario = User::factory()->create([
        'nome_completo' => 'Maria Silva',
        'cpf' => '39053344705',
        'email' => 'maria.silva@example.com',
    ]);
    $usuario->tenants()->attach($tenant->id);

    $parent = Responsavel::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $usuario->id,
        'cpf' => $usuario->cpf,
        'parentesco' => 'Mãe',
        'profissao' => 'Médica',
        'data_nascimento' => '1978-11-02',
        'observacoes' => 'Prefere contato por WhatsApp',
    ]);

    $response = $this->actingAs($authUser)->get("/school/parents/{$parent->id}/edit");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('school/parents/Edit')
        ->where('parent.data_nascimento', '1978-11-02')
        ->where('parent.observacoes', 'Prefere contato por WhatsApp')
    );
});

it('includes data_nascimento and observacoes on show page', function () {
    $this->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);

    $tenant = Tenant::factory()->create();
    $authUser = User::factory()->create();
    $authUser->tenants()->attach($tenant->id);

    $usuario = User::factory()->create([
        'nome_completo' => 'João Souza',
        'cpf' => '39053344705',
        'email' => 'joao.souza@example.com',
    ]);
    $usuario->tenants()->attach($tenant->id);

    $parent = Responsavel::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $usuario->id,
        'cpf' => $usuario->cpf,
        'parentesco' => 'Pai',
        'profissao' => 'Engenheiro',
        'data_nascimento' => '1975-08-20',
        'observacoes' => 'Disponível após as 18h',
    ]);

    $response = $this->actingAs($authUser)->get("/school/parents/{$parent->id}");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('school/parents/Show')
        ->where('parent.data_nascimento', '1975-08-20')
        ->where('parent.observacoes', 'Disponível após as 18h')
    );
});
