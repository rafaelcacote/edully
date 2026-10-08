<?php

use App\Actions\School\CreateStudentAction;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Tenant;
use App\Models\Turma;
use App\Models\User;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

it('searches students by name and includes the active class', function () {
    $this->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);

    $tenant = Tenant::factory()->create();
    $authUser = User::factory()->create();
    $authUser->tenants()->attach($tenant->id);

    $turma = Turma::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Turma A',
        'serie' => '5º ano',
        'turma_letra' => 'A',
        'ano_letivo' => 2026,
        'ativo' => true,
    ]);

    $matching = app(CreateStudentAction::class)->execute([
        'nome' => 'Ana Souza',
        'nome_social' => 'Aninha',
        'turma_id' => $turma->id,
        'ativo' => true,
    ], $tenant);

    app(CreateStudentAction::class)->execute([
        'nome' => 'Bruno Lima',
        'ativo' => true,
    ], $tenant);

    $response = $this->actingAs($authUser)->getJson('/school/students/search?search=Ana');

    $response->assertOk();
    $response->assertJsonCount(1, 'students');
    $response->assertJsonPath('students.0.id', $matching->id);
    $response->assertJsonPath('students.0.nome', 'Ana Souza');
    $response->assertJsonPath('students.0.nome_social', 'Aninha');
    $response->assertJsonPath('students.0.turma.nome', 'Turma A');
    $response->assertJsonPath('students.0.turma.serie', '5º ano');
    $response->assertJsonPath('students.0.turma.ano_letivo', 2026);
});

it('searches students by social name', function () {
    $this->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);

    $tenant = Tenant::factory()->create();
    $authUser = User::factory()->create();
    $authUser->tenants()->attach($tenant->id);

    app(CreateStudentAction::class)->execute([
        'nome' => 'Carlos Mendes',
        'nome_social' => 'Cacá',
        'ativo' => true,
    ], $tenant);

    $response = $this->actingAs($authUser)->getJson('/school/students/search?search=Cacá');

    $response->assertOk();
    $response->assertJsonPath('students.0.nome', 'Carlos Mendes');
    $response->assertJsonPath('students.0.turma', null);
});
