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
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

function disableBoletimAuthMiddleware(): void
{
    test()->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);
}

/**
 * @return array{tenant: Tenant, user: User, turma: Turma, aluno: Student, professor: Teacher, disciplina: Disciplina}
 */
function setupBoletimContext(): array
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
        'nome' => 'Aluno Boletim',
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

    $driver = DB::connection('shared')->getDriverName();
    $matriculasTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';
    $turmaDisciplinasTable = $driver === 'sqlite' ? 'turma_disciplinas' : 'escola.turma_disciplinas';

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

    if ($driver === 'sqlite') {
        $matriculaRow['matricula'] = $matriculaId;
        $matriculaRow['ativo'] = true;
    }

    DB::connection('shared')->table($matriculasTable)->insert($matriculaRow);

    DB::connection('shared')->table($turmaDisciplinasTable)->insert([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'turma_id' => $turma->id,
        'disciplina_id' => $disciplina->id,
        'professor_id' => $professor->id,
        'created_at' => now(),
    ]);

    return compact('tenant', 'user', 'turma', 'aluno', 'professor', 'disciplina');
}

it('renders boletim selection page', function () {
    disableBoletimAuthMiddleware();

    ['user' => $user] = setupBoletimContext();

    $response = $this->actingAs($user)->get('/school/boletins');

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('school/boletins/Index')
        ->has('turmas')
        ->where('boletim', null)
    );
});

it('builds boletim grid with averages for launched bimestres only', function () {
    disableBoletimAuthMiddleware();

    ['tenant' => $tenant, 'user' => $user, 'turma' => $turma, 'aluno' => $aluno, 'professor' => $professor, 'disciplina' => $disciplina] = setupBoletimContext();

    Nota::create([
        'tenant_id' => $tenant->id,
        'aluno_id' => $aluno->id,
        'professor_id' => $professor->id,
        'turma_id' => $turma->id,
        'disciplina' => 'Matemática',
        'disciplina_id' => $disciplina->id,
        'bimestre' => 1,
        'nota' => 8.0,
        'ano_letivo' => 2026,
    ]);

    Nota::create([
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

    $response = $this->actingAs($user)->get('/school/boletins?'.http_build_query([
        'turma_id' => $turma->id,
        'aluno_id' => $aluno->id,
    ]));

    $response->assertSuccessful();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('school/boletins/Index')
        ->has('boletim', fn (Assert $boletim) => $boletim
            ->where('aluno.id', $aluno->id)
            ->where('turma.id', $turma->id)
            ->has('disciplinas', 1)
            ->where('disciplinas.0.nome', 'Matemática')
            ->where('disciplinas.0.bimestres.1', 8)
            ->where('disciplinas.0.bimestres.2', 6)
            ->where('disciplinas.0.bimestres.3', null)
            ->where('disciplinas.0.bimestres.4', null)
            ->where('disciplinas.0.media', 7)
            ->where('media_geral', 7)
            ->etc()
        )
    );
});

it('returns 404 when student is not enrolled in selected class', function () {
    disableBoletimAuthMiddleware();

    ['tenant' => $tenant, 'user' => $user, 'turma' => $turma] = setupBoletimContext();

    $outroAluno = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Outro Aluno',
        'ativo' => true,
    ]);

    $response = $this->actingAs($user)->get('/school/boletins?'.http_build_query([
        'turma_id' => $turma->id,
        'aluno_id' => $outroAluno->id,
    ]));

    $response->assertNotFound();
});
