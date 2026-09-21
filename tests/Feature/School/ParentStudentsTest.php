<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Responsavel;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

it('creates and links a student from the parent screen', function () {
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
        'cpf' => '33322211100',
        'email' => 'parent@example.com',
    ]);
    $parentUser->tenants()->attach($tenant->id);

    $parent = Responsavel::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $parentUser->id,
        'cpf' => $parentUser->cpf,
        'parentesco' => 'Pai',
    ]);

    $turma = Turma::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Turma A',
        'serie' => '5º ano',
        'ano_letivo' => 2026,
        'ativo' => true,
    ]);

    $payload = [
        'nome' => 'Aluno Filho',
        'turma_id' => $turma->id,
        'ativo' => '1',
    ];

    $response = $this->actingAs($authUser)->post("/school/parents/{$parent->id}/students", $payload);

    $response->assertRedirect(route('school.parents.show', $parent, absolute: false));

    $student = Student::query()->where('nome', 'Aluno Filho')->first();
    expect($student)->not()->toBeNull();

    $this->assertDatabaseHas('alunos', [
        'tenant_id' => $tenant->id,
        'nome' => 'Aluno Filho',
    ], 'shared');

    $this->assertDatabaseHas('aluno_responsavel', [
        'tenant_id' => $tenant->id,
        'aluno_id' => $student?->id,
        'responsavel_id' => $parent->id,
    ], 'shared');
});

it('detaches a student from the parent', function () {
    $this->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);

    $tenant = Tenant::factory()->create();
    $authUser = User::factory()->create();
    $authUser->tenants()->attach($tenant->id);

    $parentUser = User::factory()->create(['cpf' => '55544433322']);
    $parentUser->tenants()->attach($tenant->id);

    $parent = Responsavel::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $parentUser->id,
        'cpf' => $parentUser->cpf,
        'parentesco' => 'Mãe',
    ]);

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Aluno Vinculado',
        'ativo' => true,
    ]);

    DB::connection('shared')->table('aluno_responsavel')->insert([
        'id' => Str::uuid(),
        'tenant_id' => $tenant->id,
        'aluno_id' => $student->id,
        'responsavel_id' => $parent->id,
        'principal' => false,
        'created_at' => now(),
    ]);

    $response = $this->actingAs($authUser)->delete("/school/parents/{$parent->id}/students/{$student->id}");

    $response->assertRedirect(route('school.parents.show', $parent, absolute: false));

    $this->assertDatabaseMissing('aluno_responsavel', [
        'tenant_id' => $tenant->id,
        'aluno_id' => $student->id,
        'responsavel_id' => $parent->id,
    ], 'shared');
});

it('does not allow creating and linking a student to an inactive parent', function () {
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
        'cpf' => '11122233344',
        'email' => 'inactive.parent@example.com',
        'ativo' => false,
    ]);
    $parentUser->tenants()->attach($tenant->id);

    $parent = Responsavel::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $parentUser->id,
        'cpf' => $parentUser->cpf,
        'parentesco' => 'Pai',
    ]);

    $turma = Turma::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Turma B',
        'serie' => '5º ano',
        'ano_letivo' => 2026,
        'ativo' => true,
    ]);

    $payload = [
        'nome' => 'Aluno Bloqueado',
        'turma_id' => $turma->id,
        'ativo' => '1',
    ];

    $response = $this->actingAs($authUser)->post("/school/parents/{$parent->id}/students", $payload);

    $response->assertRedirect(route('school.parents.show', $parent, absolute: false));
    $response->assertSessionHas('toast.type', 'error');
    $response->assertSessionHas('toast.message', 'Não é possível vincular alunos a um responsável inativo.');

    $this->assertDatabaseMissing('alunos', [
        'tenant_id' => $tenant->id,
        'nome' => 'Aluno Bloqueado',
    ], 'shared');
});

it('does not allow attaching an existing student to an inactive parent', function () {
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
        'cpf' => '22233344455',
        'ativo' => false,
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
        'nome' => 'Aluno Existente',
        'ativo' => true,
    ]);

    $response = $this->actingAs($authUser)->post("/school/parents/{$parent->id}/students/attach", [
        'student_id' => $student->id,
    ]);

    $response->assertRedirect(route('school.parents.show', $parent, absolute: false));
    $response->assertSessionHas('toast.type', 'error');
    $response->assertSessionHas('toast.message', 'Não é possível vincular alunos a um responsável inativo.');

    $this->assertDatabaseMissing('aluno_responsavel', [
        'tenant_id' => $tenant->id,
        'aluno_id' => $student->id,
        'responsavel_id' => $parent->id,
    ], 'shared');
});
