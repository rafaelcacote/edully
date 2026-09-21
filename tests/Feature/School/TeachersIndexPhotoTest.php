<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

it('includes teacher photo on the teachers index page', function () {
    $this->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);

    $tenant = Tenant::factory()->create();
    $authUser = User::factory()->create();
    $authUser->tenants()->attach($tenant->id);

    $fotoUrl = 'https://example.com/professor-foto.jpg';

    $usuario = User::factory()->create([
        'nome_completo' => 'Professor Com Foto',
        'avatar_url' => $fotoUrl,
    ]);

    $teacher = Teacher::query()->create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $usuario->id,
        'matricula' => 'PROF-FOTO-1',
        'ativo' => true,
    ]);

    $response = $this->actingAs($authUser)->get(route('school.teachers.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('school/teachers/Index')
        ->has('teachers.data', 1)
        ->where('teachers.data.0.id', $teacher->id)
        ->where('teachers.data.0.foto_url', $fotoUrl)
    );
});
