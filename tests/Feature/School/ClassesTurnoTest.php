<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Tenant;
use App\Models\Turma;
use App\Models\User;
use App\Support\Turno;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

function disableClassesTurnoAuthMiddleware(): void
{
    test()->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);
}

/**
 * @return array{tenant: Tenant, user: User}
 */
function setupClassesTurnoContext(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $user->tenants()->attach($tenant->id);

    return compact('tenant', 'user');
}

it('stores a class with turno', function () {
    disableClassesTurnoAuthMiddleware();

    ['user' => $user] = setupClassesTurnoContext();

    $response = $this->actingAs($user)->post('/school/classes', [
        'nome' => '5º Ano A - Manhã',
        'serie' => '5º ano',
        'turma_letra' => 'A',
        'ano_letivo' => 2026,
        'turno' => Turno::MATUTINO,
        'capacidade' => 30,
        'ativo' => true,
    ]);

    $response->assertRedirect(route('school.classes.index', absolute: false));

    $turma = Turma::query()->first();
    expect($turma)->not->toBeNull();
    expect($turma->turno)->toBe(Turno::MATUTINO);
    expect($turma->nome)->toBe('5º Ano A - Manhã');
});

it('allows creating a class without turno', function () {
    disableClassesTurnoAuthMiddleware();

    ['user' => $user] = setupClassesTurnoContext();

    $response = $this->actingAs($user)->post('/school/classes', [
        'nome' => '5º Ano B',
        'ano_letivo' => 2026,
        'turno' => '',
    ]);

    $response->assertRedirect(route('school.classes.index', absolute: false));

    $turma = Turma::query()->first();
    expect($turma)->not->toBeNull();
    expect($turma->turno)->toBeNull();
});

it('rejects invalid turno values', function () {
    disableClassesTurnoAuthMiddleware();

    ['user' => $user] = setupClassesTurnoContext();

    $response = $this->actingAs($user)->post('/school/classes', [
        'nome' => '5º Ano C',
        'ano_letivo' => 2026,
        'turno' => 'noturno',
    ]);

    $response->assertSessionHasErrors('turno');
});

it('updates a class turno', function () {
    disableClassesTurnoAuthMiddleware();

    ['tenant' => $tenant, 'user' => $user] = setupClassesTurnoContext();

    $class = Turma::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Turma Turno',
        'serie' => '6º ano',
        'turma_letra' => 'A',
        'ano_letivo' => 2026,
        'turno' => Turno::MATUTINO,
        'ativo' => true,
    ]);

    $response = $this->actingAs($user)->patch("/school/classes/{$class->id}", [
        'nome' => 'Turma Turno',
        'serie' => '6º ano',
        'turma_letra' => 'A',
        'ano_letivo' => 2026,
        'turno' => Turno::INTEGRAL,
        'ativo' => true,
    ]);

    $response->assertRedirect(route('school.classes.edit', $class, absolute: false));

    $class->refresh();
    expect($class->turno)->toBe(Turno::INTEGRAL);
});

it('includes turno on show and edit inertia props', function () {
    disableClassesTurnoAuthMiddleware();

    ['tenant' => $tenant, 'user' => $user] = setupClassesTurnoContext();

    $class = Turma::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Turma Vespertino',
        'serie' => '7º ano',
        'turma_letra' => 'B',
        'ano_letivo' => 2026,
        'turno' => Turno::VESPERTINO,
        'capacidade' => 28,
        'ativo' => true,
    ]);

    $this->actingAs($user)
        ->get("/school/classes/{$class->id}")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('school/classes/Show')
            ->has('turma', fn (Assert $turma) => $turma
                ->where('id', $class->id)
                ->where('turno', Turno::VESPERTINO)
                ->etc()
            )
        );

    $this->actingAs($user)
        ->get("/school/classes/{$class->id}/edit")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('school/classes/Edit')
            ->has('turma', fn (Assert $turma) => $turma
                ->where('id', $class->id)
                ->where('turno', Turno::VESPERTINO)
                ->etc()
            )
            ->has('teachers')
        );
});
