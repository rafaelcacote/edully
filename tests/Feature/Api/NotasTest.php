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
function setupApiNotasContext(): array
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
        'nome' => 'Aluno Notas',
        'ativo' => true,
    ]);

    $driver = DB::connection('shared')->getDriverName();
    $pivotTable = $driver === 'sqlite' ? 'aluno_responsavel' : 'escola.aluno_responsavel';
    $matriculasTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';

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
        'nome' => 'Português',
        'sigla' => 'POR',
        'ativo' => true,
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    return compact('tenant', 'user', 'token', 'responsavel', 'turma', 'aluno', 'professor', 'disciplina');
}

it('requires authentication to list student notas', function () {
    $response = $this->getJson('/api/mobile/students/'.Str::uuid().'/notas');

    $response->assertUnauthorized();
});

it('forbids teachers from listing student notas via this endpoint', function () {
    ['tenant' => $tenant, 'aluno' => $aluno] = setupApiNotasContext();

    $teacherUser = User::factory()->create(['ativo' => true]);
    Teacher::factory()->create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $teacherUser->id,
        'ativo' => true,
    ]);

    $token = $teacherUser->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/mobile/students/{$aluno->id}/notas");

    $response->assertForbidden();
});

it('returns 404 when listing notas for unlinked student', function () {
    ['tenant' => $tenant, 'token' => $token] = setupApiNotasContext();

    $outroAluno = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Não vinculado',
        'ativo' => true,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/mobile/students/{$outroAluno->id}/notas");

    $response->assertNotFound();
});

it('lists notas for linked student with optional filters', function () {
    [
        'tenant' => $tenant,
        'token' => $token,
        'turma' => $turma,
        'aluno' => $aluno,
        'professor' => $professor,
        'disciplina' => $disciplina,
    ] = setupApiNotasContext();

    $nota1 = Nota::create([
        'tenant_id' => $tenant->id,
        'aluno_id' => $aluno->id,
        'professor_id' => $professor->id,
        'turma_id' => $turma->id,
        'disciplina' => 'Português',
        'disciplina_id' => $disciplina->id,
        'bimestre' => 1,
        'nota' => 9.5,
        'comportamento' => 'bom',
        'observacoes' => 'Ótimo desempenho',
        'ano_letivo' => 2026,
    ]);

    Nota::create([
        'tenant_id' => $tenant->id,
        'aluno_id' => $aluno->id,
        'professor_id' => $professor->id,
        'turma_id' => $turma->id,
        'disciplina' => 'Português',
        'disciplina_id' => $disciplina->id,
        'bimestre' => 2,
        'nota' => 7.0,
        'ano_letivo' => 2026,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/mobile/students/{$aluno->id}/notas");

    $response->assertSuccessful()
        ->assertJsonStructure([
            'notas' => [
                '*' => [
                    'id',
                    'bimestre',
                    'nota',
                    'comportamento',
                    'observacoes',
                    'ano_letivo',
                    'disciplina' => ['id', 'nome', 'sigla'],
                    'turma' => ['id', 'nome', 'serie', 'turma_letra', 'ano_letivo'],
                    'professor' => ['id', 'nome_completo'],
                ],
            ],
        ]);

    expect($response->json('notas'))->toHaveCount(2);

    $filtered = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/mobile/students/{$aluno->id}/notas?".http_build_query([
            'turma_id' => $turma->id,
            'bimestre' => 1,
            'ano_letivo' => 2026,
            'disciplina_id' => $disciplina->id,
        ]));

    $filtered->assertSuccessful();
    expect($filtered->json('notas'))->toHaveCount(1);
    expect($filtered->json('notas.0.id'))->toBe($nota1->id);
    expect($filtered->json('notas.0.nota'))->toBe(9.5);
    expect($filtered->json('notas.0.disciplina.sigla'))->toBe('POR');
});

it('rejects invalid bimestre filter', function () {
    ['token' => $token, 'aluno' => $aluno] = setupApiNotasContext();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/mobile/students/{$aluno->id}/notas?bimestre=5");

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['bimestre']);
});
