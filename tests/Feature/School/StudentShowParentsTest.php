<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Responsavel;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

it('includes linked parents on the student show page', function () {
    $this->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);

    $tenant = Tenant::factory()->create();
    $authUser = User::factory()->create();
    $authUser->tenants()->attach($tenant->id);

    $parentUser = User::factory()->create([
        'nome_completo' => 'Maria Responsável',
        'email' => 'maria.responsavel@example.com',
        'telefone' => '11987654321',
        'cpf' => '12345678909',
        'ativo' => true,
    ]);
    $parentUser->tenants()->attach($tenant->id);

    $parent = Responsavel::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $parentUser->id,
        'cpf' => $parentUser->cpf,
        'parentesco' => 'Mãe',
    ]);

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'João Aluno',
        'ativo' => true,
    ]);

    DB::connection('shared')->table('aluno_responsavel')->insert([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'aluno_id' => $student->id,
        'responsavel_id' => $parent->id,
        'principal' => true,
        'created_at' => now(),
    ]);

    $response = $this->actingAs($authUser)->get("/school/students/{$student->id}");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('school/students/Show')
        ->where('student.nome', 'João Aluno')
        ->has('student.parents', 1)
        ->where('student.parents.0.id', $parent->id)
        ->where('student.parents.0.nome_completo', 'Maria Responsável')
        ->where('student.parents.0.email', 'maria.responsavel@example.com')
        ->where('student.parents.0.telefone', '11987654321')
        ->where('student.parents.0.parentesco', 'Mãe')
        ->where('student.parents.0.principal', true)
        ->where('student.parents.0.ativo', true)
    );
});

it('returns an empty parents list when the student has no linked guardian', function () {
    $this->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);

    $tenant = Tenant::factory()->create();
    $authUser = User::factory()->create();
    $authUser->tenants()->attach($tenant->id);

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Aluno Sem Responsável',
        'ativo' => true,
    ]);

    $response = $this->actingAs($authUser)->get("/school/students/{$student->id}");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('school/students/Show')
        ->has('student.parents', 0)
    );
});
