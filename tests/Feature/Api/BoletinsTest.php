<?php

use App\Models\Disciplina;
use App\Models\Nota;
use App\Models\Responsavel;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @return array{
 *     tenant: Tenant,
 *     user: User,
 *     token: string,
 *     responsavel: Responsavel,
 *     turma: Turma,
 *     aluno: Student,
 *     professor: Teacher,
 *     disciplina: Disciplina
 * }
 */
function setupApiBoletimContext(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $responsavel = Responsavel::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'cpf' => $user->cpf,
    ]);

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
        'nome' => 'Aluno Mobile',
        'ativo' => true,
    ]);

    $driver = DB::connection('shared')->getDriverName();
    $pivotTable = $driver === 'sqlite' ? 'aluno_responsavel' : 'escola.aluno_responsavel';
    $matriculasTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';
    $turmaDisciplinasTable = $driver === 'sqlite' ? 'turma_disciplinas' : 'escola.turma_disciplinas';

    DB::connection('shared')->table($pivotTable)->insert([
        'id' => (string) Str::uuid(),
        'aluno_id' => $aluno->id,
        'responsavel_id' => $responsavel->id,
        'tenant_id' => $tenant->id,
        'principal' => true,
    ]);

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

    DB::connection('shared')->table($turmaDisciplinasTable)->insert([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'turma_id' => $turma->id,
        'disciplina_id' => $disciplina->id,
        'professor_id' => $professor->id,
        'created_at' => now(),
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    return compact('tenant', 'user', 'token', 'responsavel', 'turma', 'aluno', 'professor', 'disciplina');
}

it('requires authentication to view boletim', function () {
    $response = $this->getJson('/api/mobile/students/'.Str::uuid().'/boletim?turma_id='.Str::uuid());

    $response->assertUnauthorized();
});

it('requires turma_id to view boletim', function () {
    ['token' => $token, 'aluno' => $aluno] = setupApiBoletimContext();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/mobile/students/{$aluno->id}/boletim");

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['turma_id']);
});

it('forbids teachers from viewing student boletim via this endpoint', function () {
    ['tenant' => $tenant, 'turma' => $turma, 'aluno' => $aluno] = setupApiBoletimContext();

    $teacherUser = User::factory()->create(['ativo' => true]);
    Teacher::factory()->create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $teacherUser->id,
        'ativo' => true,
    ]);

    $token = $teacherUser->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/mobile/students/{$aluno->id}/boletim?turma_id={$turma->id}");

    $response->assertForbidden();
});

it('returns 404 when student is not linked to responsavel', function () {
    ['tenant' => $tenant, 'token' => $token, 'turma' => $turma] = setupApiBoletimContext();

    $outroAluno = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Outro Aluno',
        'ativo' => true,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/mobile/students/{$outroAluno->id}/boletim?turma_id={$turma->id}");

    $response->assertNotFound();
});

it('returns boletim with averages for launched bimestres', function () {
    [
        'tenant' => $tenant,
        'token' => $token,
        'turma' => $turma,
        'aluno' => $aluno,
        'professor' => $professor,
        'disciplina' => $disciplina,
    ] = setupApiBoletimContext();

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

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/mobile/students/{$aluno->id}/boletim?turma_id={$turma->id}");

    $response->assertSuccessful()
        ->assertJsonPath('boletim.aluno.id', $aluno->id)
        ->assertJsonPath('boletim.turma.id', $turma->id)
        ->assertJsonPath('boletim.disciplinas.0.nome', 'Matemática')
        ->assertJsonPath('boletim.disciplinas.0.bimestres.1', 8)
        ->assertJsonPath('boletim.disciplinas.0.bimestres.2', 6)
        ->assertJsonPath('boletim.disciplinas.0.bimestres.3', null)
        ->assertJsonPath('boletim.disciplinas.0.bimestres.4', null)
        ->assertJsonPath('boletim.disciplinas.0.media', 7)
        ->assertJsonPath('boletim.media_geral', 7);
});

it('returns 404 when linked student is not enrolled in turma', function () {
    ['tenant' => $tenant, 'token' => $token, 'aluno' => $aluno] = setupApiBoletimContext();

    $outraTurma = Turma::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Turma 6B',
        'serie' => '6º ano',
        'turma_letra' => 'B',
        'ano_letivo' => 2026,
        'ativo' => true,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/mobile/students/{$aluno->id}/boletim?turma_id={$outraTurma->id}");

    $response->assertNotFound();
});
