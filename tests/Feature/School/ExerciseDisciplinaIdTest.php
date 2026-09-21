<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Disciplina;
use App\Models\Exercise;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

it('persists exercicio with disciplina_id and tipo_exercicio', function () {
    $this->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);

    expect(Schema::connection('shared')->hasColumn('exercicios', 'disciplina_id'))->toBeTrue()
        ->and(Schema::connection('shared')->hasColumn('exercicios', 'tipo_exercicio'))->toBeTrue()
        ->and(Schema::connection('shared')->hasColumn('exercicios', 'disciplina'))->toBeFalse();

    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $user->tenants()->attach($tenant->id);

    $teacher = Teacher::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'matricula' => 'PROF-EX-1',
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

    $response = $this->actingAs($user)->post('/school/exercises', [
        'disciplina_id' => $disciplina->id,
        'titulo' => 'Lista de frações',
        'descricao' => 'Resolver páginas 10 e 11',
        'data_entrega' => now()->addDays(3)->format('Y-m-d'),
        'turma_id' => $turma->id,
        'tipo_exercicio' => 'exercicio_caderno',
        'bimestre' => 3,
    ]);

    $response->assertRedirect(route('school.exercises.index', absolute: false));

    $exercise = Exercise::query()
        ->where('tenant_id', $tenant->id)
        ->where('titulo', 'Lista de frações')
        ->first();

    expect($exercise)->not->toBeNull()
        ->and((string) $exercise->disciplina_id)->toBe((string) $disciplina->id)
        ->and($exercise->tipo_exercicio)->toBe('exercicio_caderno')
        ->and($exercise->bimestre)->toBe(3);
});
