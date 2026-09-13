<?php

use App\Models\Message;
use App\Models\Responsavel;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('responsavel can list messages for their students', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $responsavel = Responsavel::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'cpf' => $user->cpf,
    ]);

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'João Silva',
        'ativo' => true,
    ]);

    DB::connection('shared')->table('aluno_responsavel')->insert([
        'id' => \Illuminate\Support\Str::uuid(),
        'aluno_id' => $student->id,
        'responsavel_id' => $responsavel->id,
        'tenant_id' => $tenant->id,
        'principal' => true,
    ]);

    $teacherUser = User::factory()->create([
        'ativo' => true,
        'nome_completo' => 'Professor Remetente',
        'avatar_url' => asset('storage/teachers/photos/remetente.jpg'),
    ]);
    $teacher = Teacher::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $teacherUser->id,
        'matricula' => 'PROF'.fake()->unique()->numberBetween(2024000, 2024999),
        'ativo' => true,
    ]);

    $message = Message::create([
        'tenant_id' => $tenant->id,
        'remetente_id' => $teacherUser->id,
        'aluno_id' => $student->id,
        'titulo' => 'Teste de Mensagem',
        'conteudo' => 'Conteúdo da mensagem',
        'tipo' => 'informativo',
        'prioridade' => 'normal',
        'lida' => false,
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/mobile/messages');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'messages' => [
                '*' => [
                    'id',
                    'titulo',
                    'conteudo',
                    'tipo',
                    'prioridade',
                    'lida',
                    'created_at',
                    'aluno',
                    'remetente' => [
                        'id',
                        'nome_completo',
                        'avatar_url',
                        'foto_url',
                    ],
                ],
            ],
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
            ],
        ]);

    expect($response->json('messages'))->toHaveCount(1);
    expect($response->json('messages.0.id'))->toBe($message->id);
    expect($response->json('messages.0.remetente.id'))->toBe($teacherUser->id);
    expect($response->json('messages.0.remetente.nome_completo'))->toBe('Professor Remetente');
    expect($response->json('messages.0.remetente.foto_url'))->toBe($teacherUser->avatar_url);
    expect($response->json('messages.0.remetente.avatar_url'))->toBe($teacherUser->avatar_url);
});

it('responsavel can filter messages by aluno_id', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $responsavel = Responsavel::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'cpf' => $user->cpf,
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

    DB::connection('shared')->table('aluno_responsavel')->insert([
        [
            'id' => \Illuminate\Support\Str::uuid(),
            'aluno_id' => $student1->id,
            'responsavel_id' => $responsavel->id,
            'tenant_id' => $tenant->id,
            'principal' => true,
        ],
        [
            'id' => \Illuminate\Support\Str::uuid(),
            'aluno_id' => $student2->id,
            'responsavel_id' => $responsavel->id,
            'tenant_id' => $tenant->id,
            'principal' => false,
        ],
    ]);

    $teacherUser = User::factory()->create(['ativo' => true]);
    $teacher = Teacher::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $teacherUser->id,
        'matricula' => 'PROF'.fake()->unique()->numberBetween(2024000, 2024999),
        'ativo' => true,
    ]);

    $message1 = Message::create([
        'tenant_id' => $tenant->id,
        'remetente_id' => $teacherUser->id,
        'aluno_id' => $student1->id,
        'titulo' => 'Mensagem 1',
        'conteudo' => 'Conteúdo 1',
        'lida' => false,
    ]);

    $message2 = Message::create([
        'tenant_id' => $tenant->id,
        'remetente_id' => $teacherUser->id,
        'aluno_id' => $student2->id,
        'titulo' => 'Mensagem 2',
        'conteudo' => 'Conteúdo 2',
        'lida' => false,
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/mobile/messages?aluno_id={$student1->id}");

    $response->assertSuccessful();
    expect($response->json('messages'))->toHaveCount(1);
    expect($response->json('messages.0.id'))->toBe($message1->id);
});

it('responsavel can filter messages by lida status', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $responsavel = Responsavel::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'cpf' => $user->cpf,
    ]);

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'João Silva',
        'ativo' => true,
    ]);

    DB::connection('shared')->table('aluno_responsavel')->insert([
        'id' => \Illuminate\Support\Str::uuid(),
        'aluno_id' => $student->id,
        'responsavel_id' => $responsavel->id,
        'tenant_id' => $tenant->id,
        'principal' => true,
    ]);

    $teacherUser = User::factory()->create(['ativo' => true]);
    $teacher = Teacher::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $teacherUser->id,
        'matricula' => 'PROF'.fake()->unique()->numberBetween(2024000, 2024999),
        'ativo' => true,
    ]);

    Message::create([
        'tenant_id' => $tenant->id,
        'remetente_id' => $teacherUser->id,
        'aluno_id' => $student->id,
        'titulo' => 'Mensagem Lida',
        'conteudo' => 'Conteúdo',
        'lida' => true,
        'lida_em' => now(),
    ]);

    $message2 = Message::create([
        'tenant_id' => $tenant->id,
        'remetente_id' => $teacherUser->id,
        'aluno_id' => $student->id,
        'titulo' => 'Mensagem Não Lida',
        'conteudo' => 'Conteúdo',
        'lida' => false,
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/mobile/messages?lida=false');

    $response->assertSuccessful();
    expect($response->json('messages'))->toHaveCount(1);
    expect($response->json('messages.0.id'))->toBe($message2->id);
});

it('responsavel can view a specific message', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $responsavel = Responsavel::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'cpf' => $user->cpf,
    ]);

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'João Silva',
        'ativo' => true,
    ]);

    DB::connection('shared')->table('aluno_responsavel')->insert([
        'id' => \Illuminate\Support\Str::uuid(),
        'aluno_id' => $student->id,
        'responsavel_id' => $responsavel->id,
        'tenant_id' => $tenant->id,
        'principal' => true,
    ]);

    $teacherUser = User::factory()->create(['ativo' => true]);
    $teacher = Teacher::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $teacherUser->id,
        'matricula' => 'PROF'.fake()->unique()->numberBetween(2024000, 2024999),
        'ativo' => true,
    ]);

    $message = Message::create([
        'tenant_id' => $tenant->id,
        'remetente_id' => $teacherUser->id,
        'aluno_id' => $student->id,
        'titulo' => 'Teste de Mensagem',
        'conteudo' => 'Conteúdo da mensagem',
        'lida' => false,
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/mobile/messages/{$message->id}");

    $response->assertSuccessful()
        ->assertJsonStructure([
            'message' => [
                'id',
                'titulo',
                'conteudo',
                'lida',
                'created_at',
                'aluno',
                'remetente',
            ],
        ])
        ->assertJson([
            'message' => [
                'id' => $message->id,
                'titulo' => 'Teste de Mensagem',
            ],
        ]);
});

it('responsavel cannot view message for student they do not have access to', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $responsavel = Responsavel::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'cpf' => $user->cpf,
    ]);

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'João Silva',
        'ativo' => true,
    ]);

    // Não vincular o aluno ao responsável

    $teacherUser = User::factory()->create(['ativo' => true]);
    $teacher = Teacher::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $teacherUser->id,
        'matricula' => 'PROF'.fake()->unique()->numberBetween(2024000, 2024999),
        'ativo' => true,
    ]);

    $message = Message::create([
        'tenant_id' => $tenant->id,
        'remetente_id' => $teacherUser->id,
        'aluno_id' => $student->id,
        'titulo' => 'Teste de Mensagem',
        'conteudo' => 'Conteúdo da mensagem',
        'lida' => false,
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/mobile/messages/{$message->id}");

    $response->assertForbidden();
});

it('responsavel can mark message as read', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $responsavel = Responsavel::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'cpf' => $user->cpf,
    ]);

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'João Silva',
        'ativo' => true,
    ]);

    DB::connection('shared')->table('aluno_responsavel')->insert([
        'id' => \Illuminate\Support\Str::uuid(),
        'aluno_id' => $student->id,
        'responsavel_id' => $responsavel->id,
        'tenant_id' => $tenant->id,
        'principal' => true,
    ]);

    $teacherUser = User::factory()->create(['ativo' => true]);
    $teacher = Teacher::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $teacherUser->id,
        'matricula' => 'PROF'.fake()->unique()->numberBetween(2024000, 2024999),
        'ativo' => true,
    ]);

    $message = Message::create([
        'tenant_id' => $tenant->id,
        'remetente_id' => $teacherUser->id,
        'aluno_id' => $student->id,
        'titulo' => 'Teste de Mensagem',
        'conteudo' => 'Conteúdo da mensagem',
        'lida' => false,
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/mobile/messages/{$message->id}/read");

    $response->assertSuccessful();
    expect($message->fresh()->lida)->toBeTrue();
    expect($message->fresh()->lida_em)->not->toBeNull();
});

it('teacher can create message for a specific student', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $teacher = Teacher::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'matricula' => 'PROF'.fake()->unique()->numberBetween(2024000, 2024999),
        'ativo' => true,
    ]);

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'João Silva',
        'ativo' => true,
    ]);

    // Criar turma e vincular aluno
    $turma = Turma::create([
        'tenant_id' => $tenant->id,
        'professor_id' => $teacher->id,
        'nome' => 'Turma A',
        'serie' => '1º Ano',
        'ano_letivo' => 2024,
        'ativo' => true,
    ]);

    $matriculaId = \Illuminate\Support\Str::uuid();
    DB::connection('shared')->table('matriculas_turma')->insert([
        'id' => $matriculaId,
        'matricula' => $matriculaId,
        'aluno_id' => $student->id,
        'turma_id' => $turma->id,
        'tenant_id' => $tenant->id,
        'status' => 'ativo',
        'data_matricula' => now(),
    ]);

    DB::connection('shared')->table('professor_turma')->insert([
        'id' => \Illuminate\Support\Str::uuid(),
        'professor_id' => $teacher->id,
        'turma_id' => $turma->id,
        'tenant_id' => $tenant->id,
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/mobile/messages', [
            'aluno_id' => $student->id,
            'titulo' => 'Nova Mensagem',
            'conteudo' => 'Conteúdo da nova mensagem',
            'tipo' => 'informativo',
            'prioridade' => 'normal',
        ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'message' => [
                'id',
                'titulo',
                'conteudo',
                'tipo',
                'prioridade',
                'lida',
            ],
        ])
        ->assertJson([
            'message' => [
                'titulo' => 'Nova Mensagem',
                'conteudo' => 'Conteúdo da nova mensagem',
                'lida' => false,
            ],
        ]);

    expect(Message::where('titulo', 'Nova Mensagem')->exists())->toBeTrue();
});

it('teacher can create message for all students in a turma', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $teacher = Teacher::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'matricula' => 'PROF'.fake()->unique()->numberBetween(2024000, 2024999),
        'ativo' => true,
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

    $turma = Turma::create([
        'tenant_id' => $tenant->id,
        'professor_id' => $teacher->id,
        'nome' => 'Turma A',
        'serie' => '1º Ano',
        'ano_letivo' => 2024,
        'ativo' => true,
    ]);

    $matriculaId1 = \Illuminate\Support\Str::uuid();
    $matriculaId2 = \Illuminate\Support\Str::uuid();
    DB::connection('shared')->table('matriculas_turma')->insert([
        ['id' => $matriculaId1, 'matricula' => $matriculaId1, 'aluno_id' => $student1->id, 'turma_id' => $turma->id, 'tenant_id' => $tenant->id, 'status' => 'ativo', 'data_matricula' => now()],
        ['id' => $matriculaId2, 'matricula' => $matriculaId2, 'aluno_id' => $student2->id, 'turma_id' => $turma->id, 'tenant_id' => $tenant->id, 'status' => 'ativo', 'data_matricula' => now()],
    ]);

    DB::connection('shared')->table('professor_turma')->insert([
        'id' => \Illuminate\Support\Str::uuid(),
        'professor_id' => $teacher->id,
        'turma_id' => $turma->id,
        'tenant_id' => $tenant->id,
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/mobile/messages', [
            'turma_id' => $turma->id,
            'titulo' => 'Mensagem para Turma',
            'conteudo' => 'Conteúdo da mensagem',
            'tipo' => 'aviso',
            'prioridade' => 'alta',
        ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'message',
            'messages',
            'count',
        ]);

    expect($response->json('count'))->toBe(2);
    expect(Message::where('titulo', 'Mensagem para Turma')->count())->toBe(2);
});

it('teacher cannot create message for student they do not have access to', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $teacher = Teacher::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'matricula' => 'PROF'.fake()->unique()->numberBetween(2024000, 2024999),
        'ativo' => true,
    ]);

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'João Silva',
        'ativo' => true,
    ]);

    // Não vincular aluno à turma do professor

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/mobile/messages', [
            'aluno_id' => $student->id,
            'titulo' => 'Nova Mensagem',
            'conteudo' => 'Conteúdo da nova mensagem',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['aluno_id']);
});

it('teacher can list messages they sent', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $teacher = Teacher::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'matricula' => 'PROF'.fake()->unique()->numberBetween(2024000, 2024999),
        'ativo' => true,
    ]);

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'João Silva',
        'ativo' => true,
    ]);

    $message = Message::create([
        'tenant_id' => $tenant->id,
        'remetente_id' => $user->id,
        'aluno_id' => $student->id,
        'titulo' => 'Mensagem do Professor',
        'conteudo' => 'Conteúdo',
        'lida' => false,
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/mobile/messages');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'messages',
            'meta',
        ]);

    expect($response->json('messages'))->toHaveCount(1);
    expect($response->json('messages.0.id'))->toBe($message->id);
});

it('requires authentication to access messages', function () {
    $response = $this->getJson('/api/mobile/messages');

    $response->assertUnauthorized();
});

it('validates message creation request', function () {
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
        ->postJson('/api/mobile/messages', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['titulo', 'conteudo']);
});

it('responsavel cannot create messages without aluno and professor', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    Responsavel::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'cpf' => $user->cpf,
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/mobile/messages', [
            'titulo' => 'Teste',
            'conteudo' => 'Conteúdo',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['aluno_id', 'professor_id']);
});

it('responsavel can create message to teacher of linked student', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true, 'nome_completo' => 'Pai Responsável']);
    $responsavel = Responsavel::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'cpf' => $user->cpf,
    ]);

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'João Silva',
        'ativo' => true,
    ]);

    $turma = Turma::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Turma A',
        'serie' => '1º Ano',
        'ano_letivo' => 2026,
        'ativo' => true,
    ]);

    $driver = DB::connection('shared')->getDriverName();
    $alunoResponsavelTable = $driver === 'sqlite' ? 'aluno_responsavel' : 'escola.aluno_responsavel';
    $matriculasTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';
    $professorTurmaTable = $driver === 'sqlite' ? 'professor_turma' : 'escola.professor_turma';

    DB::connection('shared')->table($alunoResponsavelTable)->insert([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'aluno_id' => $student->id,
        'responsavel_id' => $responsavel->id,
        'tenant_id' => $tenant->id,
        'principal' => true,
    ]);

    $matriculaId = (string) \Illuminate\Support\Str::uuid();
    $matriculaRow = [
        'id' => $matriculaId,
        'tenant_id' => $tenant->id,
        'aluno_id' => $student->id,
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

    $teacherUser = User::factory()->create([
        'ativo' => true,
        'nome_completo' => 'Professora Destino',
    ]);
    $teacher = Teacher::factory()->create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $teacherUser->id,
        'ativo' => true,
    ]);

    DB::connection('shared')->table($professorTurmaTable)->insert([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'professor_id' => $teacher->id,
        'turma_id' => $turma->id,
        'tenant_id' => $tenant->id,
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/mobile/messages', [
            'aluno_id' => $student->id,
            'professor_id' => $teacher->id,
            'titulo' => 'Dúvida sobre prova',
            'conteudo' => 'Gostaria de saber a data da prova.',
            'tipo' => 'informativo',
            'prioridade' => 'normal',
        ]);

    $response->assertCreated()
        ->assertJsonPath('message.titulo', 'Dúvida sobre prova')
        ->assertJsonPath('message.lida', false)
        ->assertJsonPath('message.remetente.id', $user->id)
        ->assertJsonPath('message.destinatario.id', $teacherUser->id)
        ->assertJsonPath('message.aluno.id', $student->id);

    $stored = Message::where('titulo', 'Dúvida sobre prova')->first();
    expect($stored)->not->toBeNull();
    expect($stored->remetente_id)->toBe($user->id);
    expect($stored->destinatario_id)->toBe($teacherUser->id);
    expect($stored->aluno_id)->toBe($student->id);
});

it('responsavel cannot message teacher not linked to student turma', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $responsavel = Responsavel::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'cpf' => $user->cpf,
    ]);

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'João Silva',
        'ativo' => true,
    ]);

    $turma = Turma::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Turma A',
        'serie' => '1º Ano',
        'ano_letivo' => 2026,
        'ativo' => true,
    ]);

    $driver = DB::connection('shared')->getDriverName();
    $alunoResponsavelTable = $driver === 'sqlite' ? 'aluno_responsavel' : 'escola.aluno_responsavel';
    $matriculasTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';

    DB::connection('shared')->table($alunoResponsavelTable)->insert([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'aluno_id' => $student->id,
        'responsavel_id' => $responsavel->id,
        'tenant_id' => $tenant->id,
        'principal' => true,
    ]);

    $matriculaId = (string) \Illuminate\Support\Str::uuid();
    $matriculaRow = [
        'id' => $matriculaId,
        'tenant_id' => $tenant->id,
        'aluno_id' => $student->id,
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

    $otherTeacher = Teacher::factory()->create([
        'tenant_id' => $tenant->id,
        'ativo' => true,
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/mobile/messages', [
            'aluno_id' => $student->id,
            'professor_id' => $otherTeacher->id,
            'titulo' => 'Teste',
            'conteudo' => 'Conteúdo',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['professor_id']);
});

it('teacher can list messages received from responsavel', function () {
    $tenant = Tenant::factory()->create();
    $teacherUser = User::factory()->create(['ativo' => true]);
    Teacher::factory()->create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $teacherUser->id,
        'ativo' => true,
    ]);

    $parentUser = User::factory()->create(['ativo' => true]);
    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'João Silva',
        'ativo' => true,
    ]);

    $message = Message::create([
        'tenant_id' => $tenant->id,
        'remetente_id' => $parentUser->id,
        'destinatario_id' => $teacherUser->id,
        'aluno_id' => $student->id,
        'titulo' => 'Mensagem do pai',
        'conteudo' => 'Olá professora',
        'lida' => false,
    ]);

    $token = $teacherUser->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/mobile/messages');

    $response->assertSuccessful();
    expect($response->json('messages'))->toHaveCount(1);
    expect($response->json('messages.0.id'))->toBe($message->id);
    expect($response->json('messages.0.destinatario.id'))->toBe($teacherUser->id);
});

it('teacher can mark received message as read', function () {
    $tenant = Tenant::factory()->create();
    $teacherUser = User::factory()->create(['ativo' => true]);
    Teacher::factory()->create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $teacherUser->id,
        'ativo' => true,
    ]);

    $parentUser = User::factory()->create(['ativo' => true]);
    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'João Silva',
        'ativo' => true,
    ]);

    $message = Message::create([
        'tenant_id' => $tenant->id,
        'remetente_id' => $parentUser->id,
        'destinatario_id' => $teacherUser->id,
        'aluno_id' => $student->id,
        'titulo' => 'Mensagem do pai',
        'conteudo' => 'Olá professora',
        'lida' => false,
    ]);

    $token = $teacherUser->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/mobile/messages/{$message->id}/read");

    $response->assertSuccessful();
    expect($message->fresh()->lida)->toBeTrue();
    expect($message->fresh()->lida_em)->not->toBeNull();
});

it('responsavel cannot mark as read a message sent to a teacher', function () {
    $tenant = Tenant::factory()->create();
    $parentUser = User::factory()->create(['ativo' => true]);
    $responsavel = Responsavel::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $parentUser->id,
        'cpf' => $parentUser->cpf,
    ]);

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'João Silva',
        'ativo' => true,
    ]);

    $driver = DB::connection('shared')->getDriverName();
    $alunoResponsavelTable = $driver === 'sqlite' ? 'aluno_responsavel' : 'escola.aluno_responsavel';

    DB::connection('shared')->table($alunoResponsavelTable)->insert([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'aluno_id' => $student->id,
        'responsavel_id' => $responsavel->id,
        'tenant_id' => $tenant->id,
        'principal' => true,
    ]);

    $teacherUser = User::factory()->create(['ativo' => true]);

    $message = Message::create([
        'tenant_id' => $tenant->id,
        'remetente_id' => $parentUser->id,
        'destinatario_id' => $teacherUser->id,
        'aluno_id' => $student->id,
        'titulo' => 'Mensagem do pai',
        'conteudo' => 'Olá professora',
        'lida' => false,
    ]);

    $token = $parentUser->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/mobile/messages/{$message->id}/read");

    $response->assertForbidden();
});

it('teacher cannot mark message as read', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $teacher = Teacher::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'matricula' => 'PROF'.fake()->unique()->numberBetween(2024000, 2024999),
        'ativo' => true,
    ]);

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'João Silva',
        'ativo' => true,
    ]);

    $message = Message::create([
        'tenant_id' => $tenant->id,
        'remetente_id' => $user->id,
        'aluno_id' => $student->id,
        'titulo' => 'Teste',
        'conteudo' => 'Conteúdo',
        'lida' => false,
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/mobile/messages/{$message->id}/read");

    $response->assertForbidden();
});

it('responsavel can delete message for their student', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $responsavel = Responsavel::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'cpf' => $user->cpf,
    ]);

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'João Silva',
        'ativo' => true,
    ]);

    DB::connection('shared')->table('aluno_responsavel')->insert([
        'id' => \Illuminate\Support\Str::uuid(),
        'aluno_id' => $student->id,
        'responsavel_id' => $responsavel->id,
        'tenant_id' => $tenant->id,
        'principal' => true,
    ]);

    $teacherUser = User::factory()->create(['ativo' => true]);
    $teacher = Teacher::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $teacherUser->id,
        'matricula' => 'PROF'.fake()->unique()->numberBetween(2024000, 2024999),
        'ativo' => true,
    ]);

    $message = Message::create([
        'tenant_id' => $tenant->id,
        'remetente_id' => $teacherUser->id,
        'aluno_id' => $student->id,
        'titulo' => 'Teste de Mensagem',
        'conteudo' => 'Conteúdo da mensagem',
        'lida' => false,
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/mobile/messages/{$message->id}");

    $response->assertSuccessful()
        ->assertJson([
            'message' => 'Recado removido com sucesso.',
        ]);

    expect(Message::find($message->id))->toBeNull();
});

it('responsavel cannot delete message for student they do not have access to', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $responsavel = Responsavel::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'cpf' => $user->cpf,
    ]);

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'João Silva',
        'ativo' => true,
    ]);

    // Não vincular o aluno ao responsável

    $teacherUser = User::factory()->create(['ativo' => true]);
    $teacher = Teacher::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $teacherUser->id,
        'matricula' => 'PROF'.fake()->unique()->numberBetween(2024000, 2024999),
        'ativo' => true,
    ]);

    $message = Message::create([
        'tenant_id' => $tenant->id,
        'remetente_id' => $teacherUser->id,
        'aluno_id' => $student->id,
        'titulo' => 'Teste de Mensagem',
        'conteudo' => 'Conteúdo da mensagem',
        'lida' => false,
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/mobile/messages/{$message->id}");

    $response->assertForbidden();
    expect(Message::find($message->id))->not->toBeNull();
});

it('teacher can delete message they sent', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $teacher = Teacher::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'matricula' => 'PROF'.fake()->unique()->numberBetween(2024000, 2024999),
        'ativo' => true,
    ]);

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'João Silva',
        'ativo' => true,
    ]);

    $message = Message::create([
        'tenant_id' => $tenant->id,
        'remetente_id' => $user->id,
        'aluno_id' => $student->id,
        'titulo' => 'Mensagem do Professor',
        'conteudo' => 'Conteúdo',
        'lida' => false,
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/mobile/messages/{$message->id}");

    $response->assertSuccessful()
        ->assertJson([
            'message' => 'Recado removido com sucesso.',
        ]);

    expect(Message::find($message->id))->toBeNull();
});

it('teacher cannot delete message they did not send', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $teacher = Teacher::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'matricula' => 'PROF'.fake()->unique()->numberBetween(2024000, 2024999),
        'ativo' => true,
    ]);

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'João Silva',
        'ativo' => true,
    ]);

    $otherTeacherUser = User::factory()->create(['ativo' => true]);
    $otherTeacher = Teacher::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $otherTeacherUser->id,
        'matricula' => 'PROF'.fake()->unique()->numberBetween(2024000, 2024999),
        'ativo' => true,
    ]);

    $message = Message::create([
        'tenant_id' => $tenant->id,
        'remetente_id' => $otherTeacherUser->id,
        'aluno_id' => $student->id,
        'titulo' => 'Mensagem de Outro Professor',
        'conteudo' => 'Conteúdo',
        'lida' => false,
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/mobile/messages/{$message->id}");

    $response->assertForbidden();
    expect(Message::find($message->id))->not->toBeNull();
});

it('requires authentication to delete message', function () {
    $tenant = Tenant::factory()->create();
    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'João Silva',
        'ativo' => true,
    ]);

    $teacherUser = User::factory()->create(['ativo' => true]);
    $teacher = Teacher::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $teacherUser->id,
        'matricula' => 'PROF'.fake()->unique()->numberBetween(2024000, 2024999),
        'ativo' => true,
    ]);

    $message = Message::create([
        'tenant_id' => $tenant->id,
        'remetente_id' => $teacherUser->id,
        'aluno_id' => $student->id,
        'titulo' => 'Teste',
        'conteudo' => 'Conteúdo',
        'lida' => false,
    ]);

    $response = $this->deleteJson("/api/mobile/messages/{$message->id}");

    $response->assertUnauthorized();
});
