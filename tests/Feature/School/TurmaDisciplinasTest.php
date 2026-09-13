<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Disciplina;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

function disableSchoolAuthMiddleware(): void
{
    test()->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);
}

function turmaDisciplinasPivotTable(): string
{
    return DB::connection('shared')->getDriverName() === 'sqlite'
        ? 'turma_disciplinas'
        : 'escola.turma_disciplinas';
}

it('renders the class disciplinas page', function () {
    disableSchoolAuthMiddleware();

    $tenant = Tenant::factory()->create();
    $authUser = User::factory()->create();
    $authUser->tenants()->attach($tenant->id);

    $class = Turma::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Turma 5A',
        'serie' => '5º ano',
        'turma_letra' => 'A',
        'ano_letivo' => 2026,
        'capacidade' => 30,
        'ativo' => true,
    ]);

    Disciplina::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Matemática',
        'sigla' => 'MAT',
        'ativo' => true,
    ]);

    $response = $this->actingAs($authUser)->get("/school/classes/{$class->id}/disciplinas");

    $response->assertSuccessful();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('school/classes/Disciplinas')
        ->where('turma.id', $class->id)
        ->has('disciplinas', 1)
        ->has('professores')
        ->has('vinculadas', 0)
    );
});

it('syncs disciplinas for a class with optional professors', function () {
    disableSchoolAuthMiddleware();

    $tenant = Tenant::factory()->create();
    $authUser = User::factory()->create();
    $authUser->tenants()->attach($tenant->id);

    $class = Turma::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Turma 6B',
        'serie' => '6º ano',
        'turma_letra' => 'B',
        'ano_letivo' => 2026,
        'capacidade' => 28,
        'ativo' => true,
    ]);

    $matematica = Disciplina::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Matemática',
        'sigla' => 'MAT',
        'ativo' => true,
    ]);

    $portugues = Disciplina::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Português',
        'sigla' => 'PORT',
        'ativo' => true,
    ]);

    $teacher = Teacher::factory()->create([
        'tenant_id' => $tenant->id,
        'ativo' => true,
    ]);

    $response = $this->actingAs($authUser)->put("/school/classes/{$class->id}/disciplinas", [
        'disciplinas' => [
            [
                'disciplina_id' => $matematica->id,
                'professor_id' => $teacher->id,
            ],
            [
                'disciplina_id' => $portugues->id,
                'professor_id' => null,
            ],
        ],
    ]);

    $response->assertRedirect(route('school.classes.disciplinas', $class, absolute: false));

    $rows = DB::connection('shared')
        ->table(turmaDisciplinasPivotTable())
        ->where('tenant_id', $tenant->id)
        ->where('turma_id', $class->id)
        ->get();

    expect($rows)->toHaveCount(2);

    $matematicaRow = $rows->firstWhere('disciplina_id', $matematica->id);
    $portuguesRow = $rows->firstWhere('disciplina_id', $portugues->id);

    expect($matematicaRow)->not->toBeNull()
        ->and($matematicaRow->professor_id)->toBe($teacher->id)
        ->and($portuguesRow)->not->toBeNull()
        ->and($portuguesRow->professor_id)->toBeNull();
});

it('replaces existing disciplinas when syncing again', function () {
    disableSchoolAuthMiddleware();

    $tenant = Tenant::factory()->create();
    $authUser = User::factory()->create();
    $authUser->tenants()->attach($tenant->id);

    $class = Turma::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Turma 7A',
        'serie' => '7º ano',
        'turma_letra' => 'A',
        'ano_letivo' => 2026,
        'ativo' => true,
    ]);

    $matematica = Disciplina::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Matemática',
        'ativo' => true,
    ]);

    $ciencias = Disciplina::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Ciências',
        'ativo' => true,
    ]);

    $this->actingAs($authUser)->put("/school/classes/{$class->id}/disciplinas", [
        'disciplinas' => [
            ['disciplina_id' => $matematica->id, 'professor_id' => null],
        ],
    ])->assertRedirect();

    $this->actingAs($authUser)->put("/school/classes/{$class->id}/disciplinas", [
        'disciplinas' => [
            ['disciplina_id' => $ciencias->id, 'professor_id' => null],
        ],
    ])->assertRedirect();

    $rows = DB::connection('shared')
        ->table(turmaDisciplinasPivotTable())
        ->where('tenant_id', $tenant->id)
        ->where('turma_id', $class->id)
        ->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->disciplina_id)->toBe($ciencias->id);
});

it('allows clearing all disciplinas from a class', function () {
    disableSchoolAuthMiddleware();

    $tenant = Tenant::factory()->create();
    $authUser = User::factory()->create();
    $authUser->tenants()->attach($tenant->id);

    $class = Turma::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Turma 8A',
        'serie' => '8º ano',
        'turma_letra' => 'A',
        'ano_letivo' => 2026,
        'ativo' => true,
    ]);

    $matematica = Disciplina::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Matemática',
        'ativo' => true,
    ]);

    $this->actingAs($authUser)->put("/school/classes/{$class->id}/disciplinas", [
        'disciplinas' => [
            ['disciplina_id' => $matematica->id, 'professor_id' => null],
        ],
    ])->assertRedirect();

    $this->actingAs($authUser)->put("/school/classes/{$class->id}/disciplinas", [
        'disciplinas' => [],
    ])->assertRedirect();

    $count = DB::connection('shared')
        ->table(turmaDisciplinasPivotTable())
        ->where('tenant_id', $tenant->id)
        ->where('turma_id', $class->id)
        ->count();

    expect($count)->toBe(0);
});

it('rejects duplicate disciplinas in the same sync payload', function () {
    disableSchoolAuthMiddleware();

    $tenant = Tenant::factory()->create();
    $authUser = User::factory()->create();
    $authUser->tenants()->attach($tenant->id);

    $class = Turma::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Turma 9A',
        'serie' => '9º ano',
        'turma_letra' => 'A',
        'ano_letivo' => 2026,
        'ativo' => true,
    ]);

    $matematica = Disciplina::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Matemática',
        'ativo' => true,
    ]);

    $response = $this->actingAs($authUser)->put("/school/classes/{$class->id}/disciplinas", [
        'disciplinas' => [
            ['disciplina_id' => $matematica->id, 'professor_id' => null],
            ['disciplina_id' => $matematica->id, 'professor_id' => null],
        ],
    ]);

    $response->assertSessionHasErrors(['disciplinas.0.disciplina_id']);
});

it('includes disciplinas on the class show page', function () {
    disableSchoolAuthMiddleware();

    $tenant = Tenant::factory()->create();
    $authUser = User::factory()->create();
    $authUser->tenants()->attach($tenant->id);

    $class = Turma::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Turma Show',
        'serie' => '5º ano',
        'turma_letra' => 'C',
        'ano_letivo' => 2026,
        'ativo' => true,
    ]);

    $matematica = Disciplina::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Matemática',
        'sigla' => 'MAT',
        'ativo' => true,
    ]);

    DB::connection('shared')->table(turmaDisciplinasPivotTable())->insert([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'tenant_id' => $tenant->id,
        'turma_id' => $class->id,
        'disciplina_id' => $matematica->id,
        'professor_id' => null,
        'created_at' => now(),
    ]);

    $response = $this->actingAs($authUser)->get("/school/classes/{$class->id}");

    $response->assertSuccessful();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('school/classes/Show')
        ->has('turma.disciplinas', 1)
        ->where('turma.disciplinas.0.id', $matematica->id)
        ->where('turma.disciplinas.0.nome', 'Matemática')
    );
});
