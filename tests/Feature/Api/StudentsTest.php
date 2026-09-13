<?php

use App\Models\Responsavel;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @return array{tenant: Tenant, user: User, token: string, responsavel: Responsavel, aluno: Student, fotoUrl: string}
 */
function setupApiStudentsContext(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $responsavel = Responsavel::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'cpf' => $user->cpf,
    ]);

    $fotoUrl = asset('storage/students/photos/aluno-api.jpg');

    $aluno = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Aluno Com Foto',
        'nome_social' => 'Aluno',
        'foto_url' => $fotoUrl,
        'ativo' => true,
    ]);

    $turma = Turma::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Turma 5A',
        'serie' => '5º ano',
        'turma_letra' => 'A',
        'ano_letivo' => 2026,
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

    $token = $user->createToken('mobile-app')->plainTextToken;

    return compact('tenant', 'user', 'token', 'responsavel', 'aluno', 'fotoUrl');
}

it('returns student foto_url on students index', function () {
    ['token' => $token, 'aluno' => $aluno, 'fotoUrl' => $fotoUrl] = setupApiStudentsContext();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/mobile/students');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'students' => [
                '*' => [
                    'id',
                    'nome',
                    'nome_social',
                    'foto_url',
                    'data_nascimento',
                    'is_principal',
                    'school',
                    'turmas',
                ],
            ],
        ]);

    expect($response->json('students.0.id'))->toBe($aluno->id);
    expect($response->json('students.0.foto_url'))->toBe($fotoUrl);
});

it('returns student foto_url on students show', function () {
    ['token' => $token, 'aluno' => $aluno, 'fotoUrl' => $fotoUrl] = setupApiStudentsContext();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/mobile/students/{$aluno->id}");

    $response->assertSuccessful()
        ->assertJsonPath('student.id', $aluno->id)
        ->assertJsonPath('student.foto_url', $fotoUrl)
        ->assertJsonStructure([
            'student' => [
                'id',
                'nome',
                'nome_social',
                'foto_url',
                'data_nascimento',
                'informacoes_medicas',
                'is_principal',
                'school',
                'turmas',
            ],
        ]);
});

it('returns null foto_url when student has no photo', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $responsavel = Responsavel::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'cpf' => $user->cpf,
    ]);

    $aluno = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Aluno Sem Foto',
        'foto_url' => null,
        'ativo' => true,
    ]);

    $driver = DB::connection('shared')->getDriverName();
    $pivotTable = $driver === 'sqlite' ? 'aluno_responsavel' : 'escola.aluno_responsavel';

    DB::connection('shared')->table($pivotTable)->insert([
        'id' => (string) Str::uuid(),
        'aluno_id' => $aluno->id,
        'responsavel_id' => $responsavel->id,
        'tenant_id' => $tenant->id,
        'principal' => true,
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/mobile/students/{$aluno->id}");

    $response->assertSuccessful()
        ->assertJsonPath('student.foto_url', null);
});
