<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Disciplina;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\Test;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

function disableTestsBimestreAuthMiddleware(): void
{
    test()->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);
}

/**
 * @return array{tenant: Tenant, user: User, teacher: Teacher, turma: Turma, disciplina: Disciplina}
 */
function setupTestsBimestreContext(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $user->tenants()->attach($tenant->id);

    $teacher = Teacher::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'matricula' => 'PROF'.fake()->unique()->numberBetween(2024000, 2024999),
        'ativo' => true,
    ]);

    $disciplina = Disciplina::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Matemática',
        'sigla' => 'MAT',
        'ativo' => true,
    ]);

    $turma = Turma::create([
        'tenant_id' => $tenant->id,
        'professor_id' => $teacher->id,
        'nome' => '5º Ano A',
        'serie' => '5º Ano',
        'ano_letivo' => 2026,
        'ativo' => true,
    ]);

    $driver = DB::connection('shared')->getDriverName();
    $pivotTable = $driver === 'sqlite' ? 'professor_disciplinas' : 'escola.professor_disciplinas';
    DB::connection('shared')->table($pivotTable)->insert([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'professor_id' => $teacher->id,
        'disciplina_id' => $disciplina->id,
        'tenant_id' => $tenant->id,
    ]);

    return compact('tenant', 'user', 'teacher', 'turma', 'disciplina');
}

it('stores a test with bimestre', function () {
    disableTestsBimestreAuthMiddleware();

    ['user' => $user, 'turma' => $turma, 'disciplina' => $disciplina] = setupTestsBimestreContext();

    $response = $this->actingAs($user)->post('/school/tests', [
        'disciplina_id' => $disciplina->id,
        'titulo' => 'Prova bimestral',
        'descricao' => 'Conteúdo do 2º bimestre',
        'data_prova' => now()->addDays(10)->format('Y-m-d'),
        'turma_id' => $turma->id,
        'bimestre' => 2,
    ]);

    $response->assertRedirect(route('school.tests.index', absolute: false));

    $test = Test::query()->first();
    expect($test)->not->toBeNull();
    expect($test->bimestre)->toBe(2);
    expect($test->titulo)->toBe('Prova bimestral');
});

it('rejects test without bimestre', function () {
    disableTestsBimestreAuthMiddleware();

    ['user' => $user, 'turma' => $turma, 'disciplina' => $disciplina] = setupTestsBimestreContext();

    $response = $this->actingAs($user)->post('/school/tests', [
        'disciplina_id' => $disciplina->id,
        'titulo' => 'Prova sem bimestre',
        'data_prova' => now()->addDays(10)->format('Y-m-d'),
        'turma_id' => $turma->id,
    ]);

    $response->assertSessionHasErrors(['bimestre']);
    expect(Test::query()->count())->toBe(0);
});

it('filters tests index by bimestre', function () {
    disableTestsBimestreAuthMiddleware();

    ['user' => $user, 'teacher' => $teacher, 'turma' => $turma, 'disciplina' => $disciplina, 'tenant' => $tenant] = setupTestsBimestreContext();

    Test::create([
        'tenant_id' => $tenant->id,
        'professor_id' => $teacher->id,
        'turma_id' => $turma->id,
        'disciplina_id' => $disciplina->id,
        'titulo' => 'Prova 1º',
        'data_prova' => now()->addDays(5),
        'bimestre' => 1,
    ]);

    Test::create([
        'tenant_id' => $tenant->id,
        'professor_id' => $teacher->id,
        'turma_id' => $turma->id,
        'disciplina_id' => $disciplina->id,
        'titulo' => 'Prova 3º',
        'data_prova' => now()->addDays(8),
        'bimestre' => 3,
    ]);

    $response = $this->actingAs($user)->get('/school/tests?bimestre=3');

    $response->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('school/tests/Index')
            ->where('filters.bimestre', '3')
            ->has('tests.data', 1)
            ->where('tests.data.0.titulo', 'Prova 3º')
            ->where('tests.data.0.bimestre', 3)
            ->where('defaultBimestre', fn ($value) => in_array($value, [1, 2, 3, 4], true))
        );
});

it('defaults tests index filter to the current bimestre', function () {
    disableTestsBimestreAuthMiddleware();

    ['user' => $user, 'teacher' => $teacher, 'turma' => $turma, 'disciplina' => $disciplina, 'tenant' => $tenant] = setupTestsBimestreContext();

    $atual = \App\Support\Bimestre::atual();
    $outro = $atual === 1 ? 2 : 1;

    Test::create([
        'tenant_id' => $tenant->id,
        'professor_id' => $teacher->id,
        'turma_id' => $turma->id,
        'disciplina_id' => $disciplina->id,
        'titulo' => 'Prova atual',
        'data_prova' => now()->addDays(5),
        'bimestre' => $atual,
    ]);

    Test::create([
        'tenant_id' => $tenant->id,
        'professor_id' => $teacher->id,
        'turma_id' => $turma->id,
        'disciplina_id' => $disciplina->id,
        'titulo' => 'Prova outro',
        'data_prova' => now()->addDays(8),
        'bimestre' => $outro,
    ]);

    $response = $this->actingAs($user)->get('/school/tests');

    $response->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('school/tests/Index')
            ->where('filters.bimestre', (string) $atual)
            ->has('tests.data', 1)
            ->where('tests.data.0.titulo', 'Prova atual')
        );
});

it('lists all tests when bimestre filter is all', function () {
    disableTestsBimestreAuthMiddleware();

    ['user' => $user, 'teacher' => $teacher, 'turma' => $turma, 'disciplina' => $disciplina, 'tenant' => $tenant] = setupTestsBimestreContext();

    Test::create([
        'tenant_id' => $tenant->id,
        'professor_id' => $teacher->id,
        'turma_id' => $turma->id,
        'disciplina_id' => $disciplina->id,
        'titulo' => 'Prova A',
        'data_prova' => now()->addDays(5),
        'bimestre' => 1,
    ]);

    Test::create([
        'tenant_id' => $tenant->id,
        'professor_id' => $teacher->id,
        'turma_id' => $turma->id,
        'disciplina_id' => $disciplina->id,
        'titulo' => 'Prova B',
        'data_prova' => now()->addDays(8),
        'bimestre' => 3,
    ]);

    $response = $this->actingAs($user)->get('/school/tests?bimestre=all');

    $response->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('school/tests/Index')
            ->where('filters.bimestre', 'all')
            ->has('tests.data', 2)
        );
});
