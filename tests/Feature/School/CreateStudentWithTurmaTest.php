<?php

use App\Actions\School\CreateStudentAction;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Tenant;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

it('creates student enrollment with matricula equal to pivot id', function () {
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
        'serie' => '1º Ano',
        'ano_letivo' => 2026,
        'ativo' => true,
    ]);

    $student = app(CreateStudentAction::class)->execute([
        'nome' => 'Maria Silva',
        'turma_id' => $turma->id,
        'ativo' => true,
    ], $tenant);

    $driver = DB::connection('shared')->getDriverName();
    $pivotTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';

    $matricula = DB::connection('shared')
        ->table($pivotTable)
        ->where('tenant_id', $tenant->id)
        ->where('aluno_id', $student->id)
        ->where('turma_id', $turma->id)
        ->first();

    expect($matricula)->not->toBeNull()
        ->and($matricula->status)->toBe('ativo')
        ->and((string) $matricula->id)->not->toBeEmpty();

    if ($driver === 'sqlite') {
        expect((string) $matricula->matricula)->toBe((string) $matricula->id);
    }
});

it('stores student with turma via http without null matricula', function () {
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
        'nome' => 'Turma B',
        'serie' => '2º Ano',
        'ano_letivo' => 2026,
        'ativo' => true,
    ]);

    $response = $this->actingAs($authUser)->post('/school/students', [
        'nome' => 'João Souza',
        'turma_id' => $turma->id,
        'ativo' => true,
    ]);

    $response->assertRedirect(route('school.students.index', absolute: false));

    $driver = DB::connection('shared')->getDriverName();
    $pivotTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';
    $alunosTable = $driver === 'sqlite' ? 'alunos' : 'escola.alunos';

    $student = DB::connection('shared')
        ->table($alunosTable)
        ->where('tenant_id', $tenant->id)
        ->where('nome', 'João Souza')
        ->first();

    expect($student)->not->toBeNull();

    $matricula = DB::connection('shared')
        ->table($pivotTable)
        ->where('aluno_id', $student->id)
        ->first();

    expect($matricula)->not->toBeNull()
        ->and((string) $matricula->id)->not->toBeEmpty()
        ->and($matricula->status)->toBe('ativo');

    if ($driver === 'sqlite') {
        expect($matricula->matricula)->not->toBeNull()
            ->and((string) $matricula->matricula)->toBe((string) $matricula->id);
    }
});
