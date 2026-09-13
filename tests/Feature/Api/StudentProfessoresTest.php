<?php

use App\Models\Disciplina;
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
 *     driver: string,
 *     professorTurmaTable: string,
 *     turmaDisciplinasTable: string,
 *     matriculasTable: string
 * }
 */
function setupApiStudentProfessoresContext(): array
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
        'nome' => 'Aluno Professores',
        'ativo' => true,
    ]);

    $driver = DB::connection('shared')->getDriverName();
    $pivotTable = $driver === 'sqlite' ? 'aluno_responsavel' : 'escola.aluno_responsavel';
    $matriculasTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';
    $professorTurmaTable = $driver === 'sqlite' ? 'professor_turma' : 'escola.professor_turma';
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

    $token = $user->createToken('mobile-app')->plainTextToken;

    return [
        'tenant' => $tenant,
        'user' => $user,
        'token' => $token,
        'responsavel' => $responsavel,
        'turma' => $turma,
        'aluno' => $aluno,
        'driver' => $driver,
        'professorTurmaTable' => $professorTurmaTable,
        'turmaDisciplinasTable' => $turmaDisciplinasTable,
        'matriculasTable' => $matriculasTable,
    ];
}

function linkTeacherToTurma(string $table, Teacher $teacher, Turma $turma, Tenant $tenant): void
{
    DB::connection('shared')->table($table)->insert([
        'id' => (string) Str::uuid(),
        'professor_id' => $teacher->id,
        'turma_id' => $turma->id,
        'tenant_id' => $tenant->id,
    ]);
}

it('requires authentication to list student professores', function () {
    $response = $this->getJson('/api/mobile/students/'.Str::uuid().'/professores');

    $response->assertUnauthorized();
});

it('forbids teachers from listing student professores via this endpoint', function () {
    ['tenant' => $tenant, 'aluno' => $aluno] = setupApiStudentProfessoresContext();

    $teacherUser = User::factory()->create(['ativo' => true]);
    Teacher::factory()->create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $teacherUser->id,
        'ativo' => true,
    ]);

    $token = $teacherUser->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/mobile/students/{$aluno->id}/professores");

    $response->assertForbidden();
});

it('returns 404 when student is not linked to responsavel', function () {
    ['tenant' => $tenant, 'token' => $token] = setupApiStudentProfessoresContext();

    $outroAluno = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Outro Aluno',
        'ativo' => true,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/mobile/students/{$outroAluno->id}/professores");

    $response->assertNotFound();
});

it('returns empty list when student has no linked teachers', function () {
    ['token' => $token, 'aluno' => $aluno] = setupApiStudentProfessoresContext();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/mobile/students/{$aluno->id}/professores");

    $response->assertSuccessful()
        ->assertJson([
            'professores' => [],
        ]);
});

it('lists teachers linked via professor_turma', function () {
    $ctx = setupApiStudentProfessoresContext();
    ['tenant' => $tenant, 'token' => $token, 'turma' => $turma, 'aluno' => $aluno] = $ctx;

    $usuario = User::factory()->create([
        'ativo' => true,
        'nome_completo' => 'Ana Professora',
        'avatar_url' => 'https://example.com/ana.jpg',
    ]);

    $professor = Teacher::factory()->create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $usuario->id,
        'ativo' => true,
        'especializacao' => 'Matemática',
    ]);

    linkTeacherToTurma($ctx['professorTurmaTable'], $professor, $turma, $tenant);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/mobile/students/{$aluno->id}/professores");

    $response->assertSuccessful()
        ->assertJsonCount(1, 'professores')
        ->assertJsonPath('professores.0.id', $professor->id)
        ->assertJsonPath('professores.0.usuario_id', $usuario->id)
        ->assertJsonPath('professores.0.nome_completo', 'Ana Professora')
        ->assertJsonPath('professores.0.avatar_url', 'https://example.com/ana.jpg')
        ->assertJsonPath('professores.0.foto_url', 'https://example.com/ana.jpg')
        ->assertJsonPath('professores.0.especializacao', 'Matemática')
        ->assertJsonPath('professores.0.turmas.0.id', $turma->id)
        ->assertJsonPath('professores.0.turmas.0.nome', 'Turma 5A');
});

it('lists teachers linked via turma_disciplinas', function () {
    $ctx = setupApiStudentProfessoresContext();
    ['tenant' => $tenant, 'token' => $token, 'turma' => $turma, 'aluno' => $aluno] = $ctx;

    $usuario = User::factory()->create([
        'ativo' => true,
        'nome_completo' => 'Bruno Disciplina',
    ]);

    $professor = Teacher::factory()->create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $usuario->id,
        'ativo' => true,
    ]);

    $disciplina = Disciplina::create([
        'tenant_id' => $tenant->id,
        'nome' => 'História',
        'sigla' => 'HIS',
        'ativo' => true,
    ]);

    DB::connection('shared')->table($ctx['turmaDisciplinasTable'])->insert([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'turma_id' => $turma->id,
        'disciplina_id' => $disciplina->id,
        'professor_id' => $professor->id,
        'created_at' => now(),
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/mobile/students/{$aluno->id}/professores");

    $response->assertSuccessful()
        ->assertJsonCount(1, 'professores')
        ->assertJsonPath('professores.0.id', $professor->id)
        ->assertJsonPath('professores.0.nome_completo', 'Bruno Disciplina')
        ->assertJsonPath('professores.0.turmas.0.id', $turma->id)
        ->assertJsonPath('professores.0.disciplinas.0.id', $disciplina->id)
        ->assertJsonPath('professores.0.disciplinas.0.nome', 'História')
        ->assertJsonPath('professores.0.disciplinas.0.sigla', 'HIS');
});

it('deduplicates teachers present in both professor_turma and turma_disciplinas', function () {
    $ctx = setupApiStudentProfessoresContext();
    ['tenant' => $tenant, 'token' => $token, 'turma' => $turma, 'aluno' => $aluno] = $ctx;

    $professor = Teacher::factory()->create([
        'tenant_id' => $tenant->id,
        'ativo' => true,
    ]);

    linkTeacherToTurma($ctx['professorTurmaTable'], $professor, $turma, $tenant);

    $disciplina = Disciplina::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Ciências',
        'sigla' => 'CIE',
        'ativo' => true,
    ]);

    DB::connection('shared')->table($ctx['turmaDisciplinasTable'])->insert([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'turma_id' => $turma->id,
        'disciplina_id' => $disciplina->id,
        'professor_id' => $professor->id,
        'created_at' => now(),
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/mobile/students/{$aluno->id}/professores");

    $response->assertSuccessful()
        ->assertJsonCount(1, 'professores')
        ->assertJsonPath('professores.0.id', $professor->id);
});

it('excludes inactive teachers', function () {
    $ctx = setupApiStudentProfessoresContext();
    ['tenant' => $tenant, 'token' => $token, 'turma' => $turma, 'aluno' => $aluno] = $ctx;

    $ativo = Teacher::factory()->create([
        'tenant_id' => $tenant->id,
        'ativo' => true,
    ]);
    $inativo = Teacher::factory()->inactive()->create([
        'tenant_id' => $tenant->id,
    ]);

    linkTeacherToTurma($ctx['professorTurmaTable'], $ativo, $turma, $tenant);
    linkTeacherToTurma($ctx['professorTurmaTable'], $inativo, $turma, $tenant);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/mobile/students/{$aluno->id}/professores");

    $response->assertSuccessful()
        ->assertJsonCount(1, 'professores')
        ->assertJsonPath('professores.0.id', $ativo->id);
});

it('does not list teachers from another student turma', function () {
    $ctx = setupApiStudentProfessoresContext();
    ['tenant' => $tenant, 'token' => $token, 'aluno' => $aluno] = $ctx;

    $outraTurma = Turma::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Turma 6B',
        'serie' => '6º ano',
        'turma_letra' => 'B',
        'ano_letivo' => 2026,
        'ativo' => true,
    ]);

    $professorOutraTurma = Teacher::factory()->create([
        'tenant_id' => $tenant->id,
        'ativo' => true,
    ]);

    linkTeacherToTurma($ctx['professorTurmaTable'], $professorOutraTurma, $outraTurma, $tenant);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/mobile/students/{$aluno->id}/professores");

    $response->assertSuccessful()
        ->assertJson([
            'professores' => [],
        ]);
});
