<?php

use App\Models\Disciplina;
use App\Models\Responsavel;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\Test;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('teacher can list their tests', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
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
        'nome' => '1º Ano A',
        'serie' => '1º Ano',
        'ano_letivo' => 2024,
        'ativo' => true,
    ]);

    // Vincular disciplina ao professor
    $driver = DB::connection('shared')->getDriverName();
    $pivotTable = $driver === 'sqlite' ? 'professor_disciplinas' : 'escola.professor_disciplinas';
    DB::connection('shared')->table($pivotTable)->insert([
        'id' => \Illuminate\Support\Str::uuid(),
        'professor_id' => $teacher->id,
        'disciplina_id' => $disciplina->id,
        'tenant_id' => $tenant->id,
    ]);

    $test = Test::create([
        'tenant_id' => $tenant->id,
        'professor_id' => $teacher->id,
        'turma_id' => $turma->id,
        'disciplina_id' => $disciplina->id,
        'titulo' => 'Prova de Matemática',
        'descricao' => 'Prova sobre equações',
        'data_prova' => now()->addDays(7),
        'horario' => '08:00',
        'sala' => '101',
        'duracao_minutos' => 90,
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/mobile/tests');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'tests' => [
                '*' => [
                    'id',
                    'titulo',
                    'descricao',
                    'data_prova',
                    'data_prova_formatted',
                    'horario',
                    'sala',
                    'duracao_minutos',
                    'disciplina',
                    'turma',
                    'professor',
                    'created_at',
                ],
            ],
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
            ],
        ]);

    expect($response->json('tests'))->toHaveCount(1);
    expect($response->json('tests.0.id'))->toBe($test->id);
});

it('responsavel can list tests for their students classes', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $responsavel = Responsavel::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'cpf' => $user->cpf,
    ]);

    $teacherUser = User::factory()->create(['ativo' => true]);
    $teacher = Teacher::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $teacherUser->id,
        'matricula' => 'PROF'.fake()->unique()->numberBetween(2024000, 2024999),
        'ativo' => true,
    ]);

    $disciplina = Disciplina::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Português',
        'sigla' => 'POR',
        'ativo' => true,
    ]);

    $turma = Turma::create([
        'tenant_id' => $tenant->id,
        'professor_id' => $teacher->id,
        'nome' => '2º Ano B',
        'serie' => '2º Ano',
        'ano_letivo' => 2024,
        'ativo' => true,
    ]);

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Maria Silva',
        'ativo' => true,
    ]);

    // Vincular aluno ao responsável
    $driver = DB::connection('shared')->getDriverName();
    $pivotTable = $driver === 'sqlite' ? 'aluno_responsavel' : 'escola.aluno_responsavel';
    DB::connection('shared')->table($pivotTable)->insert([
        'id' => \Illuminate\Support\Str::uuid(),
        'aluno_id' => $student->id,
        'responsavel_id' => $responsavel->id,
        'tenant_id' => $tenant->id,
        'principal' => true,
    ]);

    // Matricular aluno na turma
    $matriculasTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';
    DB::connection('shared')->table($matriculasTable)->insert([
        'id' => \Illuminate\Support\Str::uuid(),
        'aluno_id' => $student->id,
        'turma_id' => $turma->id,
        'tenant_id' => $tenant->id,
        'status' => 'ativo',
        'data_matricula' => now(),
    ]);

    $test = Test::create([
        'tenant_id' => $tenant->id,
        'professor_id' => $teacher->id,
        'turma_id' => $turma->id,
        'disciplina_id' => $disciplina->id,
        'titulo' => 'Prova de Português',
        'descricao' => 'Prova sobre gramática',
        'data_prova' => now()->addDays(5),
        'horario' => '10:00',
        'sala' => '202',
        'duracao_minutos' => 60,
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/mobile/tests');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'tests',
            'meta',
        ]);

    expect($response->json('tests'))->toHaveCount(1);
    expect($response->json('tests.0.id'))->toBe($test->id);
});

it('teacher can create a test', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $teacher = Teacher::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'matricula' => 'PROF'.fake()->unique()->numberBetween(2024000, 2024999),
        'ativo' => true,
    ]);

    $disciplina = Disciplina::create([
        'tenant_id' => $tenant->id,
        'nome' => 'História',
        'sigla' => 'HIS',
        'ativo' => true,
    ]);

    $turma = Turma::create([
        'tenant_id' => $tenant->id,
        'professor_id' => $teacher->id,
        'nome' => '3º Ano A',
        'serie' => '3º Ano',
        'ano_letivo' => 2024,
        'ativo' => true,
    ]);

    // Vincular disciplina ao professor
    $driver = DB::connection('shared')->getDriverName();
    $pivotTable = $driver === 'sqlite' ? 'professor_disciplinas' : 'escola.professor_disciplinas';
    DB::connection('shared')->table($pivotTable)->insert([
        'id' => \Illuminate\Support\Str::uuid(),
        'professor_id' => $teacher->id,
        'disciplina_id' => $disciplina->id,
        'tenant_id' => $tenant->id,
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/mobile/tests', [
            'disciplina_id' => $disciplina->id,
            'titulo' => 'Nova Prova',
            'descricao' => 'Descrição da prova',
            'data_prova' => now()->addDays(10)->format('Y-m-d'),
            'horario' => '14:00',
            'sala' => '301',
            'duracao_minutos' => 120,
            'turma_id' => $turma->id,
        ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'message',
            'test' => [
                'id',
                'titulo',
                'descricao',
                'data_prova',
                'horario',
                'sala',
                'duracao_minutos',
            ],
        ]);

    expect($response->json('test.titulo'))->toBe('Nova Prova');
    expect(Test::count())->toBe(1);
});

it('teacher can update their test', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $teacher = Teacher::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'matricula' => 'PROF'.fake()->unique()->numberBetween(2024000, 2024999),
        'ativo' => true,
    ]);

    $disciplina = Disciplina::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Geografia',
        'sigla' => 'GEO',
        'ativo' => true,
    ]);

    $turma = Turma::create([
        'tenant_id' => $tenant->id,
        'professor_id' => $teacher->id,
        'nome' => '4º Ano A',
        'serie' => '4º Ano',
        'ano_letivo' => 2024,
        'ativo' => true,
    ]);

    // Vincular disciplina ao professor
    $driver = DB::connection('shared')->getDriverName();
    $pivotTable = $driver === 'sqlite' ? 'professor_disciplinas' : 'escola.professor_disciplinas';
    DB::connection('shared')->table($pivotTable)->insert([
        'id' => \Illuminate\Support\Str::uuid(),
        'professor_id' => $teacher->id,
        'disciplina_id' => $disciplina->id,
        'tenant_id' => $tenant->id,
    ]);

    $test = Test::create([
        'tenant_id' => $tenant->id,
        'professor_id' => $teacher->id,
        'turma_id' => $turma->id,
        'disciplina_id' => $disciplina->id,
        'titulo' => 'Prova Original',
        'descricao' => 'Descrição original',
        'data_prova' => now()->addDays(7),
        'horario' => '08:00',
        'sala' => '101',
        'duracao_minutos' => 90,
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson("/api/mobile/tests/{$test->id}", [
            'titulo' => 'Prova Atualizada',
            'descricao' => 'Nova descrição',
            'data_prova' => now()->addDays(14)->format('Y-m-d'),
            'horario' => '10:00',
            'sala' => '202',
        ]);

    $response->assertSuccessful()
        ->assertJson([
            'message' => 'Prova atualizada com sucesso.',
        ]);

    expect($response->json('test.titulo'))->toBe('Prova Atualizada');
    $test->refresh();
    expect($test->titulo)->toBe('Prova Atualizada');
});

it('teacher can delete their test', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $teacher = Teacher::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'matricula' => 'PROF'.fake()->unique()->numberBetween(2024000, 2024999),
        'ativo' => true,
    ]);

    $disciplina = Disciplina::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Ciências',
        'sigla' => 'CIE',
        'ativo' => true,
    ]);

    $turma = Turma::create([
        'tenant_id' => $tenant->id,
        'professor_id' => $teacher->id,
        'nome' => '5º Ano A',
        'serie' => '5º Ano',
        'ano_letivo' => 2024,
        'ativo' => true,
    ]);

    // Vincular disciplina ao professor
    $driver = DB::connection('shared')->getDriverName();
    $pivotTable = $driver === 'sqlite' ? 'professor_disciplinas' : 'escola.professor_disciplinas';
    DB::connection('shared')->table($pivotTable)->insert([
        'id' => \Illuminate\Support\Str::uuid(),
        'professor_id' => $teacher->id,
        'disciplina_id' => $disciplina->id,
        'tenant_id' => $tenant->id,
    ]);

    $test = Test::create([
        'tenant_id' => $tenant->id,
        'professor_id' => $teacher->id,
        'turma_id' => $turma->id,
        'disciplina_id' => $disciplina->id,
        'titulo' => 'Prova para Deletar',
        'descricao' => 'Descrição',
        'data_prova' => now()->addDays(7),
        'horario' => '08:00',
        'sala' => '101',
        'duracao_minutos' => 90,
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/mobile/tests/{$test->id}");

    $response->assertSuccessful()
        ->assertJson([
            'message' => 'Prova removida com sucesso.',
        ]);

    expect(Test::find($test->id))->toBeNull();
});

it('responsavel can view test details', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $responsavel = Responsavel::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'cpf' => $user->cpf,
    ]);

    $teacherUser = User::factory()->create(['ativo' => true]);
    $teacher = Teacher::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $teacherUser->id,
        'matricula' => 'PROF'.fake()->unique()->numberBetween(2024000, 2024999),
        'ativo' => true,
    ]);

    $disciplina = Disciplina::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Artes',
        'sigla' => 'ART',
        'ativo' => true,
    ]);

    $turma = Turma::create([
        'tenant_id' => $tenant->id,
        'professor_id' => $teacher->id,
        'nome' => '6º Ano A',
        'serie' => '6º Ano',
        'ano_letivo' => 2024,
        'ativo' => true,
    ]);

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Pedro Silva',
        'ativo' => true,
    ]);

    // Vincular aluno ao responsável
    $driver = DB::connection('shared')->getDriverName();
    $pivotTable = $driver === 'sqlite' ? 'aluno_responsavel' : 'escola.aluno_responsavel';
    DB::connection('shared')->table($pivotTable)->insert([
        'id' => \Illuminate\Support\Str::uuid(),
        'aluno_id' => $student->id,
        'responsavel_id' => $responsavel->id,
        'tenant_id' => $tenant->id,
        'principal' => true,
    ]);

    // Matricular aluno na turma
    $matriculasTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';
    DB::connection('shared')->table($matriculasTable)->insert([
        'id' => \Illuminate\Support\Str::uuid(),
        'aluno_id' => $student->id,
        'turma_id' => $turma->id,
        'tenant_id' => $tenant->id,
        'status' => 'ativo',
        'data_matricula' => now(),
    ]);

    $test = Test::create([
        'tenant_id' => $tenant->id,
        'professor_id' => $teacher->id,
        'turma_id' => $turma->id,
        'disciplina_id' => $disciplina->id,
        'titulo' => 'Prova de Artes',
        'descricao' => 'Prova sobre pintura',
        'data_prova' => now()->addDays(3),
        'horario' => '14:00',
        'sala' => '303',
        'duracao_minutos' => 60,
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/mobile/tests/{$test->id}");

    $response->assertSuccessful()
        ->assertJsonStructure([
            'test' => [
                'id',
                'titulo',
                'descricao',
                'data_prova',
                'horario',
                'sala',
                'duracao_minutos',
                'disciplina',
                'turma',
                'professor',
            ],
        ]);

    expect($response->json('test.id'))->toBe($test->id);
});

it('requires authentication to access tests', function () {
    $response = $this->getJson('/api/mobile/tests');

    $response->assertUnauthorized();
});

it('validates test creation request', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $teacher = Teacher::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'matricula' => 'PROF'.fake()->unique()->numberBetween(2024000, 2024999),
        'ativo' => true,
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/mobile/tests', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['disciplina_id', 'titulo', 'data_prova', 'turma_id']);
});
