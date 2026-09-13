<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Disciplina;
use App\Models\Nota;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

function disableNotasAuthMiddleware(): void
{
    test()->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);
}

function notasPivotTables(): array
{
    $driver = DB::connection('shared')->getDriverName();

    return [
        'matriculas' => $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma',
        'turma_disciplinas' => $driver === 'sqlite' ? 'turma_disciplinas' : 'escola.turma_disciplinas',
    ];
}

/**
 * @return array{tenant: Tenant, user: User, turma: Turma, aluno: Student, professor: Teacher, disciplina: Disciplina}
 */
function setupNotaContext(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $user->tenants()->attach($tenant->id);

    $turma = Turma::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Turma 5A',
        'serie' => '5º ano',
        'turma_letra' => 'A',
        'ano_letivo' => 2026,
        'ativo' => true,
    ]);

    $aluno = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Aluno Teste',
        'ativo' => true,
    ]);

    $professor = Teacher::factory()->create([
        'tenant_id' => $tenant->id,
        'ativo' => true,
    ]);

    $disciplina = Disciplina::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Matemática',
        'sigla' => 'MAT',
        'ativo' => true,
    ]);

    $tables = notasPivotTables();
    $matriculaId = (string) Str::uuid();

    $matriculaRow = [
        'id' => $matriculaId,
        'tenant_id' => $tenant->id,
        'aluno_id' => $aluno->id,
        'turma_id' => $turma->id,
        'data_matricula' => now()->toDateString(),
        'status' => 'ativo',
        'created_at' => now(),
    ];

    if (DB::connection('shared')->getDriverName() === 'sqlite') {
        $matriculaRow['matricula'] = $matriculaId;
        $matriculaRow['ativo'] = true;
    }

    DB::connection('shared')->table($tables['matriculas'])->insert($matriculaRow);

    DB::connection('shared')->table($tables['turma_disciplinas'])->insert([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'turma_id' => $turma->id,
        'disciplina_id' => $disciplina->id,
        'professor_id' => $professor->id,
        'created_at' => now(),
    ]);

    return compact('tenant', 'user', 'turma', 'aluno', 'professor', 'disciplina');
}

it('stores a bimestral nota', function () {
    disableNotasAuthMiddleware();

    ['user' => $user, 'turma' => $turma, 'aluno' => $aluno, 'professor' => $professor, 'disciplina' => $disciplina] = setupNotaContext();

    $response = $this->actingAs($user)->post('/school/notas', [
        'aluno_id' => $aluno->id,
        'professor_id' => $professor->id,
        'turma_id' => $turma->id,
        'disciplina_id' => $disciplina->id,
        'bimestre' => 2,
        'nota' => 8.5,
        'ano_letivo' => 2026,
    ]);

    $response->assertRedirect(route('school.notas.index', absolute: false));

    $this->assertDatabaseHas('notas', [
        'aluno_id' => $aluno->id,
        'turma_id' => $turma->id,
        'disciplina_id' => $disciplina->id,
        'bimestre' => 2,
        'nota' => 8.5,
        'disciplina' => 'Matemática',
        'ano_letivo' => 2026,
    ], 'shared');
});

it('rejects bimestre greater than 4', function () {
    disableNotasAuthMiddleware();

    ['user' => $user, 'turma' => $turma, 'aluno' => $aluno, 'professor' => $professor, 'disciplina' => $disciplina] = setupNotaContext();

    $response = $this->actingAs($user)->post('/school/notas', [
        'aluno_id' => $aluno->id,
        'professor_id' => $professor->id,
        'turma_id' => $turma->id,
        'disciplina_id' => $disciplina->id,
        'bimestre' => 5,
        'nota' => 7.0,
        'ano_letivo' => 2026,
    ]);

    $response->assertSessionHasErrors(['bimestre']);
});

it('rejects duplicate nota for same aluno disciplina bimestre', function () {
    disableNotasAuthMiddleware();

    ['tenant' => $tenant, 'user' => $user, 'turma' => $turma, 'aluno' => $aluno, 'professor' => $professor, 'disciplina' => $disciplina] = setupNotaContext();

    Nota::create([
        'tenant_id' => $tenant->id,
        'aluno_id' => $aluno->id,
        'professor_id' => $professor->id,
        'turma_id' => $turma->id,
        'disciplina' => 'Matemática',
        'disciplina_id' => $disciplina->id,
        'bimestre' => 1,
        'nota' => 7.0,
        'ano_letivo' => 2026,
    ]);

    $response = $this->actingAs($user)->post('/school/notas', [
        'aluno_id' => $aluno->id,
        'professor_id' => $professor->id,
        'turma_id' => $turma->id,
        'disciplina_id' => $disciplina->id,
        'bimestre' => 1,
        'nota' => 8.0,
        'ano_letivo' => 2026,
    ]);

    $response->assertSessionHasErrors(['bimestre']);
});

it('rejects disciplina outside turma grade', function () {
    disableNotasAuthMiddleware();

    ['tenant' => $tenant, 'user' => $user, 'turma' => $turma, 'aluno' => $aluno, 'professor' => $professor] = setupNotaContext();

    $outraDisciplina = Disciplina::create([
        'tenant_id' => $tenant->id,
        'nome' => 'História',
        'ativo' => true,
    ]);

    $response = $this->actingAs($user)->post('/school/notas', [
        'aluno_id' => $aluno->id,
        'professor_id' => $professor->id,
        'turma_id' => $turma->id,
        'disciplina_id' => $outraDisciplina->id,
        'bimestre' => 1,
        'nota' => 6.0,
        'ano_letivo' => 2026,
    ]);

    $response->assertSessionHasErrors(['disciplina_id']);
});

it('updates a nota', function () {
    disableNotasAuthMiddleware();

    ['tenant' => $tenant, 'user' => $user, 'turma' => $turma, 'aluno' => $aluno, 'professor' => $professor, 'disciplina' => $disciplina] = setupNotaContext();

    $nota = Nota::create([
        'tenant_id' => $tenant->id,
        'aluno_id' => $aluno->id,
        'professor_id' => $professor->id,
        'turma_id' => $turma->id,
        'disciplina' => 'Matemática',
        'disciplina_id' => $disciplina->id,
        'bimestre' => 3,
        'nota' => 5.0,
        'ano_letivo' => 2026,
    ]);

    $response = $this->actingAs($user)->patch("/school/notas/{$nota->id}", [
        'aluno_id' => $aluno->id,
        'professor_id' => $professor->id,
        'turma_id' => $turma->id,
        'disciplina_id' => $disciplina->id,
        'bimestre' => 3,
        'nota' => 9.0,
        'ano_letivo' => 2026,
    ]);

    $response->assertRedirect(route('school.notas.edit', $nota, absolute: false));

    expect($nota->fresh()->nota)->toBe('9.0');
});

it('renders the lote page with enrolled students', function () {
    disableNotasAuthMiddleware();

    ['user' => $user, 'turma' => $turma, 'aluno' => $aluno, 'disciplina' => $disciplina] = setupNotaContext();

    $response = $this->actingAs($user)->get('/school/notas/lote?'.http_build_query([
        'turma_id' => $turma->id,
        'disciplina_id' => $disciplina->id,
        'bimestre' => 1,
    ]));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('school/notas/Lote')
        ->has('alunos', 1)
        ->where('alunos.0.id', $aluno->id)
        ->where('filters.bimestre', '1')
    );
});

it('syncs batch grades for a class', function () {
    disableNotasAuthMiddleware();

    ['tenant' => $tenant, 'user' => $user, 'turma' => $turma, 'aluno' => $aluno, 'professor' => $professor, 'disciplina' => $disciplina] = setupNotaContext();

    $aluno2 = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Aluno Dois',
        'ativo' => true,
    ]);

    $tables = notasPivotTables();
    $matriculaId = (string) Str::uuid();
    $matriculaRow = [
        'id' => $matriculaId,
        'tenant_id' => $tenant->id,
        'aluno_id' => $aluno2->id,
        'turma_id' => $turma->id,
        'data_matricula' => now()->toDateString(),
        'status' => 'ativo',
        'created_at' => now(),
    ];

    if (DB::connection('shared')->getDriverName() === 'sqlite') {
        $matriculaRow['matricula'] = $matriculaId;
        $matriculaRow['ativo'] = true;
    }

    DB::connection('shared')->table($tables['matriculas'])->insert($matriculaRow);

    $response = $this->actingAs($user)->put('/school/notas/lote', [
        'turma_id' => $turma->id,
        'disciplina_id' => $disciplina->id,
        'professor_id' => $professor->id,
        'bimestre' => 1,
        'ano_letivo' => 2026,
        'notas' => [
            ['aluno_id' => $aluno->id, 'nota' => 7.5],
            ['aluno_id' => $aluno2->id, 'nota' => 9.0],
        ],
    ]);

    $response->assertRedirect();

    expect(Nota::query()->where('turma_id', $turma->id)->where('bimestre', 1)->count())->toBe(2);

    $this->assertDatabaseHas('notas', [
        'aluno_id' => $aluno->id,
        'disciplina_id' => $disciplina->id,
        'bimestre' => 1,
        'nota' => 7.5,
    ], 'shared');
});

it('updates and clears notes in batch sync', function () {
    disableNotasAuthMiddleware();

    ['tenant' => $tenant, 'user' => $user, 'turma' => $turma, 'aluno' => $aluno, 'professor' => $professor, 'disciplina' => $disciplina] = setupNotaContext();

    $nota = Nota::create([
        'tenant_id' => $tenant->id,
        'aluno_id' => $aluno->id,
        'professor_id' => $professor->id,
        'turma_id' => $turma->id,
        'disciplina' => 'Matemática',
        'disciplina_id' => $disciplina->id,
        'bimestre' => 2,
        'nota' => 6.0,
        'ano_letivo' => 2026,
    ]);

    $this->actingAs($user)->put('/school/notas/lote', [
        'turma_id' => $turma->id,
        'disciplina_id' => $disciplina->id,
        'professor_id' => $professor->id,
        'bimestre' => 2,
        'ano_letivo' => 2026,
        'notas' => [
            ['aluno_id' => $aluno->id, 'nota' => 8.0],
        ],
    ])->assertRedirect();

    expect($nota->fresh()->nota)->toBe('8.0');

    $this->actingAs($user)->put('/school/notas/lote', [
        'turma_id' => $turma->id,
        'disciplina_id' => $disciplina->id,
        'professor_id' => $professor->id,
        'bimestre' => 2,
        'ano_letivo' => 2026,
        'notas' => [
            ['aluno_id' => $aluno->id, 'nota' => null],
        ],
    ])->assertRedirect();

    expect($nota->fresh()->deleted_at)->not->toBeNull();
});
