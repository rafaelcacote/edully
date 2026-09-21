<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

it('includes student photo on the students index page', function () {
    $this->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);

    $tenant = Tenant::factory()->create();
    $authUser = User::factory()->create();
    $authUser->tenants()->attach($tenant->id);

    $fotoUrl = 'https://example.com/aluno-foto.jpg';

    Student::query()->create([
        'tenant_id' => $tenant->id,
        'nome' => 'Aluno Com Foto',
        'foto_url' => $fotoUrl,
        'ativo' => true,
    ]);

    $response = $this->actingAs($authUser)->get(route('school.students.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('school/students/Index')
        ->has('students.data', 1)
        ->where('students.data.0.nome', 'Aluno Com Foto')
        ->where('students.data.0.foto_url', $fotoUrl)
    );
});
