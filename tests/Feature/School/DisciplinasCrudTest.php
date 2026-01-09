<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Disciplina;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

it('creates a disciplina on store', function () {
    $this->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);

    $tenant = Tenant::factory()->create();
    $authUser = User::factory()->create();
    $authUser->tenants()->attach($tenant->id);

    $payload = [
        'nome' => 'Matemática',
        'sigla' => 'MAT',
        'descricao' => 'Disciplina de matemática',
        'carga_horaria_semanal' => 4,
        'ativo' => true,
    ];

    $response = $this->actingAs($authUser)->post('/school/disciplinas', $payload);

    $response->assertRedirect(route('school.disciplinas.index', absolute: false));

    $this->assertDatabaseHas('disciplinas', [
        'tenant_id' => $tenant->id,
        'nome' => 'Matemática',
        'sigla' => 'MAT',
        'descricao' => 'Disciplina de matemática',
        'carga_horaria_semanal' => 4,
        'ativo' => true,
    ], 'shared');
});

it('validates nome uniqueness within the tenant', function () {
    $this->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);

    $tenant = Tenant::factory()->create();
    $authUser = User::factory()->create();
    $authUser->tenants()->attach($tenant->id);

    Disciplina::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Matemática',
        'ativo' => true,
    ]);

    $payload = [
        'nome' => 'Matemática',
        'ativo' => true,
    ];

    $response = $this->actingAs($authUser)->post('/school/disciplinas', $payload);

    $response->assertSessionHasErrors(['nome']);
});

it('updates a disciplina', function () {
    $this->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);

    $tenant = Tenant::factory()->create();
    $authUser = User::factory()->create();
    $authUser->tenants()->attach($tenant->id);

    $disciplina = Disciplina::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Matemática',
        'sigla' => 'MAT',
        'ativo' => true,
    ]);

    $payload = [
        'nome' => 'Matemática Avançada',
        'sigla' => 'MAT-A',
        'descricao' => 'Disciplina de matemática avançada',
        'carga_horaria_semanal' => 6,
        'ativo' => true,
    ];

    $response = $this->actingAs($authUser)->patch("/school/disciplinas/{$disciplina->id}", $payload);

    $response->assertRedirect(route('school.disciplinas.edit', $disciplina, absolute: false));

    $disciplina->refresh();
    expect($disciplina->nome)->toBe('Matemática Avançada');
    expect($disciplina->sigla)->toBe('MAT-A');
    expect($disciplina->descricao)->toBe('Disciplina de matemática avançada');
    expect($disciplina->carga_horaria_semanal)->toBe(6);
});

it('can toggle disciplina status', function () {
    $this->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);

    $tenant = Tenant::factory()->create();
    $authUser = User::factory()->create();
    $authUser->tenants()->attach($tenant->id);

    $disciplina = Disciplina::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Matemática',
        'ativo' => true,
    ]);

    $response = $this->actingAs($authUser)->patch("/school/disciplinas/{$disciplina->id}/toggle-status", [
        'ativo' => false,
    ]);

    $response->assertRedirect();

    $disciplina->refresh();
    expect($disciplina->ativo)->toBeFalse();

    $response->assertSessionHas('toast', [
        'type' => 'success',
        'title' => 'Status atualizado',
        'message' => 'O status da disciplina foi atualizado com sucesso.',
    ]);
});

it('deletes a disciplina', function () {
    $this->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);

    $tenant = Tenant::factory()->create();
    $authUser = User::factory()->create();
    $authUser->tenants()->attach($tenant->id);

    $disciplina = Disciplina::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Matemática',
        'ativo' => true,
    ]);

    $response = $this->actingAs($authUser)->delete("/school/disciplinas/{$disciplina->id}");

    $response->assertRedirect(route('school.disciplinas.index', absolute: false));

    $this->assertSoftDeleted('disciplinas', [
        'id' => $disciplina->id,
    ], 'shared');
});

it('prevents access to disciplina from different tenant', function () {
    $this->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);

    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    $authUser = User::factory()->create();
    $authUser->tenants()->attach($tenant1->id);

    $disciplina = Disciplina::create([
        'tenant_id' => $tenant2->id,
        'nome' => 'Matemática',
        'ativo' => true,
    ]);

    $response = $this->actingAs($authUser)->get("/school/disciplinas/{$disciplina->id}");

    $response->assertNotFound();
});
