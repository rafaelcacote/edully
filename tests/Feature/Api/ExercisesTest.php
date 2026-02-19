<?php

use App\Models\Disciplina;
use App\Models\Exercise;
use App\Models\Responsavel;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('teacher can list their exercises', function () {
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

    $exercise = Exercise::create([
        'tenant_id' => $tenant->id,
        'professor_id' => $teacher->id,
        'turma_id' => $turma->id,
        'disciplina_id' => $disciplina->id,
        'disciplina' => $disciplina->nome,
        'titulo' => 'Exercício de Matemática',
        'descricao' => 'Resolver os exercícios da página 10',
        'data_entrega' => now()->addDays(7),
        'tipo_exercicio' => 'exercicio_caderno',
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/mobile/exercises');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'exercises' => [
                '*' => [
                    'id',
                    'titulo',
                    'descricao',
                    'data_entrega',
                    'data_entrega_formatted',
                    'tipo_exercicio',
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

    expect($response->json('exercises'))->toHaveCount(1);
    expect($response->json('exercises.0.id'))->toBe($exercise->id);
});

it('responsavel can list exercises for their students classes', function () {
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

    $exercise = Exercise::create([
        'tenant_id' => $tenant->id,
        'professor_id' => $teacher->id,
        'turma_id' => $turma->id,
        'disciplina_id' => $disciplina->id,
        'disciplina' => $disciplina->nome,
        'titulo' => 'Exercício de Português',
        'descricao' => 'Ler o capítulo 5',
        'data_entrega' => now()->addDays(5),
        'tipo_exercicio' => 'exercicio_livro',
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/mobile/exercises');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'exercises',
            'meta',
        ]);

    expect($response->json('exercises'))->toHaveCount(1);
    expect($response->json('exercises.0.id'))->toBe($exercise->id);
});

it('teacher can create an exercise', function () {
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
        ->postJson('/api/mobile/exercises', [
            'disciplina_id' => $disciplina->id,
            'titulo' => 'Novo Exercício',
            'descricao' => 'Descrição do exercício',
            'data_entrega' => now()->addDays(10)->format('Y-m-d'),
            'turma_id' => $turma->id,
            'tipo_exercicio' => 'trabalho',
        ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'message',
            'exercise' => [
                'id',
                'titulo',
                'descricao',
                'data_entrega',
                'tipo_exercicio',
            ],
        ]);

    expect($response->json('exercise.titulo'))->toBe('Novo Exercício');
    expect(Exercise::count())->toBe(1);
});

it('teacher can update their exercise', function () {
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

    $exercise = Exercise::create([
        'tenant_id' => $tenant->id,
        'professor_id' => $teacher->id,
        'turma_id' => $turma->id,
        'disciplina_id' => $disciplina->id,
        'disciplina' => $disciplina->nome,
        'titulo' => 'Exercício Original',
        'descricao' => 'Descrição original',
        'data_entrega' => now()->addDays(7),
        'tipo_exercicio' => 'exercicio_caderno',
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson("/api/mobile/exercises/{$exercise->id}", [
            'titulo' => 'Exercício Atualizado',
            'descricao' => 'Nova descrição',
            'data_entrega' => now()->addDays(14)->format('Y-m-d'),
        ]);

    $response->assertSuccessful()
        ->assertJson([
            'message' => 'Exercício atualizado com sucesso.',
        ]);

    expect($response->json('exercise.titulo'))->toBe('Exercício Atualizado');
    $exercise->refresh();
    expect($exercise->titulo)->toBe('Exercício Atualizado');
});

it('teacher can delete their exercise', function () {
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

    $exercise = Exercise::create([
        'tenant_id' => $tenant->id,
        'professor_id' => $teacher->id,
        'turma_id' => $turma->id,
        'disciplina_id' => $disciplina->id,
        'disciplina' => $disciplina->nome,
        'titulo' => 'Exercício para Deletar',
        'descricao' => 'Descrição',
        'data_entrega' => now()->addDays(7),
        'tipo_exercicio' => 'exercicio_caderno',
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/mobile/exercises/{$exercise->id}");

    $response->assertSuccessful()
        ->assertJson([
            'message' => 'Exercício removido com sucesso.',
        ]);

    expect(Exercise::find($exercise->id))->toBeNull();
});

it('responsavel can view exercise details', function () {
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

    $exercise = Exercise::create([
        'tenant_id' => $tenant->id,
        'professor_id' => $teacher->id,
        'turma_id' => $turma->id,
        'disciplina_id' => $disciplina->id,
        'disciplina' => $disciplina->nome,
        'titulo' => 'Exercício de Artes',
        'descricao' => 'Criar uma pintura',
        'data_entrega' => now()->addDays(3),
        'tipo_exercicio' => 'trabalho',
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/mobile/exercises/{$exercise->id}");

    $response->assertSuccessful()
        ->assertJsonStructure([
            'exercise' => [
                'id',
                'titulo',
                'descricao',
                'data_entrega',
                'tipo_exercicio',
                'disciplina',
                'turma',
                'professor',
            ],
        ]);

    expect($response->json('exercise.id'))->toBe($exercise->id);
});

it('requires authentication to access exercises', function () {
    $response = $this->getJson('/api/mobile/exercises');

    $response->assertUnauthorized();
});

it('validates exercise creation request', function () {
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
        ->postJson('/api/mobile/exercises', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['disciplina_id', 'titulo', 'data_entrega', 'turma_id', 'tipo_exercicio']);
});
