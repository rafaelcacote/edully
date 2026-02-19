<?php

use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('teacher can list students from their turma', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $teacher = Teacher::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'matricula' => 'PROF'.fake()->unique()->numberBetween(2024000, 2024999),
        'ativo' => true,
    ]);

    $turma = Turma::create([
        'tenant_id' => $tenant->id,
        'professor_id' => $teacher->id,
        'nome' => '3º Ano A',
        'serie' => '3º Ano',
        'turma_letra' => 'A',
        'ano_letivo' => 2024,
        'ativo' => true,
    ]);

    // Vincular professor à turma através da tabela pivot
    $driver = DB::connection('shared')->getDriverName();
    $pivotTable = $driver === 'sqlite' ? 'professor_turma' : 'escola.professor_turma';
    DB::connection('shared')->table($pivotTable)->insert([
        'id' => \Illuminate\Support\Str::uuid(),
        'professor_id' => $teacher->id,
        'turma_id' => $turma->id,
        'tenant_id' => $tenant->id,
    ]);

    $student1 = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'João Silva',
        'ativo' => true,
    ]);

    $student2 = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Maria Santos',
        'ativo' => true,
    ]);

    // Matricular alunos na turma
    $matriculasTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';
    DB::connection('shared')->table($matriculasTable)->insert([
        [
            'id' => \Illuminate\Support\Str::uuid(),
            'tenant_id' => $tenant->id,
            'aluno_id' => $student1->id,
            'turma_id' => $turma->id,
            'matricula' => \Illuminate\Support\Str::uuid(),
            'data_matricula' => now()->format('Y-m-d'),
            'status' => 'ativo',
            'ativo' => true,
        ],
        [
            'id' => \Illuminate\Support\Str::uuid(),
            'tenant_id' => $tenant->id,
            'aluno_id' => $student2->id,
            'turma_id' => $turma->id,
            'matricula' => \Illuminate\Support\Str::uuid(),
            'data_matricula' => now()->format('Y-m-d'),
            'status' => 'ativo',
            'ativo' => true,
        ],
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/mobile/teacher/turmas/{$turma->id}/alunos");

    $response->assertSuccessful()
        ->assertJsonStructure([
            'turma' => [
                'id',
                'nome',
                'serie',
                'turma_letra',
                'ano_letivo',
            ],
            'alunos' => [
                '*' => [
                    'id',
                    'nome',
                    'nome_social',
                    'foto_url',
                    'data_nascimento',
                    'data_matricula',
                ],
            ],
        ]);

    expect($response->json('alunos'))->toHaveCount(2);
    expect($response->json('turma.id'))->toBe($turma->id);
});

it('teacher cannot access students from turma they do not teach', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $teacher = Teacher::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'matricula' => 'PROF'.fake()->unique()->numberBetween(2024000, 2024999),
        'ativo' => true,
    ]);

    $otherTeacher = Teacher::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => User::factory()->create(['ativo' => true])->id,
        'matricula' => 'PROF'.fake()->unique()->numberBetween(2024000, 2024999),
        'ativo' => true,
    ]);

    $turma = Turma::create([
        'tenant_id' => $tenant->id,
        'professor_id' => $otherTeacher->id,
        'nome' => '3º Ano B',
        'serie' => '3º Ano',
        'turma_letra' => 'B',
        'ano_letivo' => 2024,
        'ativo' => true,
    ]);

    // Vincular outro professor à turma
    $driver = DB::connection('shared')->getDriverName();
    $pivotTable = $driver === 'sqlite' ? 'professor_turma' : 'escola.professor_turma';
    DB::connection('shared')->table($pivotTable)->insert([
        'id' => \Illuminate\Support\Str::uuid(),
        'professor_id' => $otherTeacher->id,
        'turma_id' => $turma->id,
        'tenant_id' => $tenant->id,
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/mobile/teacher/turmas/{$turma->id}/alunos");

    $response->assertNotFound()
        ->assertJson([
            'message' => 'Turma não encontrada ou você não tem permissão para acessá-la.',
        ]);
});

it('returns 404 when turma does not exist', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $teacher = Teacher::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'matricula' => 'PROF'.fake()->unique()->numberBetween(2024000, 2024999),
        'ativo' => true,
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;
    $nonExistentId = \Illuminate\Support\Str::uuid();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/mobile/teacher/turmas/{$nonExistentId}/alunos");

    $response->assertNotFound();
});

it('returns empty list when turma has no students', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $teacher = Teacher::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'matricula' => 'PROF'.fake()->unique()->numberBetween(2024000, 2024999),
        'ativo' => true,
    ]);

    $turma = Turma::create([
        'tenant_id' => $tenant->id,
        'professor_id' => $teacher->id,
        'nome' => '3º Ano C',
        'serie' => '3º Ano',
        'turma_letra' => 'C',
        'ano_letivo' => 2024,
        'ativo' => true,
    ]);

    // Vincular professor à turma
    $driver = DB::connection('shared')->getDriverName();
    $pivotTable = $driver === 'sqlite' ? 'professor_turma' : 'escola.professor_turma';
    DB::connection('shared')->table($pivotTable)->insert([
        'id' => \Illuminate\Support\Str::uuid(),
        'professor_id' => $teacher->id,
        'turma_id' => $turma->id,
        'tenant_id' => $tenant->id,
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/mobile/teacher/turmas/{$turma->id}/alunos");

    $response->assertSuccessful()
        ->assertJson([
            'turma' => [
                'id' => $turma->id,
                'nome' => $turma->nome,
            ],
            'alunos' => [],
        ]);

    expect($response->json('alunos'))->toHaveCount(0);
});

it('requires authentication to access turma students', function () {
    $tenant = Tenant::factory()->create();
    $turma = Turma::create([
        'tenant_id' => $tenant->id,
        'nome' => '3º Ano A',
        'serie' => '3º Ano',
        'turma_letra' => 'A',
        'ano_letivo' => 2024,
        'ativo' => true,
    ]);

    $response = $this->getJson("/api/mobile/teacher/turmas/{$turma->id}/alunos");

    $response->assertUnauthorized();
});

it('only returns active students', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $teacher = Teacher::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'matricula' => 'PROF'.fake()->unique()->numberBetween(2024000, 2024999),
        'ativo' => true,
    ]);

    $turma = Turma::create([
        'tenant_id' => $tenant->id,
        'professor_id' => $teacher->id,
        'nome' => '3º Ano D',
        'serie' => '3º Ano',
        'turma_letra' => 'D',
        'ano_letivo' => 2024,
        'ativo' => true,
    ]);

    // Vincular professor à turma
    $driver = DB::connection('shared')->getDriverName();
    $pivotTable = $driver === 'sqlite' ? 'professor_turma' : 'escola.professor_turma';
    DB::connection('shared')->table($pivotTable)->insert([
        'id' => \Illuminate\Support\Str::uuid(),
        'professor_id' => $teacher->id,
        'turma_id' => $turma->id,
        'tenant_id' => $tenant->id,
    ]);

    $activeStudent = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Aluno Ativo',
        'ativo' => true,
    ]);

    $inactiveStudent = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Aluno Inativo',
        'ativo' => false,
    ]);

    // Matricular alunos na turma
    $matriculasTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';
    DB::connection('shared')->table($matriculasTable)->insert([
        [
            'id' => \Illuminate\Support\Str::uuid(),
            'tenant_id' => $tenant->id,
            'aluno_id' => $activeStudent->id,
            'turma_id' => $turma->id,
            'matricula' => \Illuminate\Support\Str::uuid(),
            'data_matricula' => now()->format('Y-m-d'),
            'status' => 'ativo',
            'ativo' => true,
        ],
        [
            'id' => \Illuminate\Support\Str::uuid(),
            'tenant_id' => $tenant->id,
            'aluno_id' => $inactiveStudent->id,
            'turma_id' => $turma->id,
            'matricula' => \Illuminate\Support\Str::uuid(),
            'data_matricula' => now()->format('Y-m-d'),
            'status' => 'ativo',
            'ativo' => true,
        ],
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/mobile/teacher/turmas/{$turma->id}/alunos");

    $response->assertSuccessful();
    expect($response->json('alunos'))->toHaveCount(1);
    expect($response->json('alunos.0.id'))->toBe($activeStudent->id);
});

it('denies access to non-teacher users', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $turma = Turma::create([
        'tenant_id' => $tenant->id,
        'nome' => '3º Ano A',
        'serie' => '3º Ano',
        'turma_letra' => 'A',
        'ano_letivo' => 2024,
        'ativo' => true,
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/mobile/teacher/turmas/{$turma->id}/alunos");

    $response->assertForbidden()
        ->assertJson([
            'message' => 'Acesso negado. Apenas professores podem acessar esta funcionalidade.',
        ]);
});
