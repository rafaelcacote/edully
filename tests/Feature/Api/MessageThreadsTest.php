<?php

use App\Models\Message;
use App\Models\Responsavel;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * @return array{
 *     tenant: Tenant,
 *     parent: User,
 *     responsavel: Responsavel,
 *     teacherUser: User,
 *     teacher: Teacher,
 *     aluno: Student,
 *     turma: Turma
 * }
 */
function setupMessageThreadContext(): array
{
    $tenant = Tenant::factory()->create();

    $parent = User::factory()->create(['ativo' => true, 'nome_completo' => 'Pai Thread']);
    $responsavel = Responsavel::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $parent->id,
        'cpf' => $parent->cpf,
    ]);

    $teacherUser = User::factory()->create(['ativo' => true, 'nome_completo' => 'Profa Thread']);
    $teacher = Teacher::factory()->create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $teacherUser->id,
        'ativo' => true,
    ]);

    $turma = Turma::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Turma Thread',
        'serie' => '5º ano',
        'turma_letra' => 'A',
        'ano_letivo' => 2026,
        'ativo' => true,
    ]);

    $aluno = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Aluno Thread',
        'ativo' => true,
    ]);

    $driver = DB::connection('shared')->getDriverName();
    $alunoResponsavelTable = $driver === 'sqlite' ? 'aluno_responsavel' : 'escola.aluno_responsavel';
    $matriculasTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';
    $professorTurmaTable = $driver === 'sqlite' ? 'professor_turma' : 'escola.professor_turma';

    DB::connection('shared')->table($alunoResponsavelTable)->insert([
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

    DB::connection('shared')->table($professorTurmaTable)->insert([
        'id' => (string) Str::uuid(),
        'professor_id' => $teacher->id,
        'turma_id' => $turma->id,
        'tenant_id' => $tenant->id,
    ]);

    return [
        'tenant' => $tenant,
        'parent' => $parent,
        'responsavel' => $responsavel,
        'teacherUser' => $teacherUser,
        'teacher' => $teacher,
        'aluno' => $aluno,
        'turma' => $turma,
    ];
}

it('opens a conversation when parent messages teacher and teacher can reply in same thread', function () {
    $ctx = setupMessageThreadContext();

    Sanctum::actingAs($ctx['parent']);
    $create = $this->postJson('/api/mobile/messages', [
        'aluno_id' => $ctx['aluno']->id,
        'professor_id' => $ctx['teacher']->id,
        'titulo' => 'Dúvida da prova',
        'conteudo' => 'Qual a data?',
    ]);

    $create->assertCreated()
        ->assertJsonPath('message.titulo', 'Dúvida da prova');

    $conversaId = $create->json('message.conversa_id');
    $mensagemId = $create->json('message.id');
    expect($conversaId)->not->toBeNull();

    Sanctum::actingAs($ctx['teacherUser']);
    $reply = $this->postJson('/api/mobile/messages', [
        'mensagem_pai_id' => $mensagemId,
        'conteudo' => 'A prova é na sexta.',
    ]);

    $reply->assertCreated()
        ->assertJsonPath('message.conversa_id', $conversaId)
        ->assertJsonPath('message.mensagem_pai_id', $mensagemId)
        ->assertJsonPath('message.destinatario.id', $ctx['parent']->id)
        ->assertJsonPath('message.titulo', 'Re: Dúvida da prova');

    Sanctum::actingAs($ctx['parent']);
    $history = $this->getJson("/api/mobile/messages/conversas/{$conversaId}");

    $history->assertSuccessful()
        ->assertJsonPath('conversa_id', $conversaId)
        ->assertJsonCount(2, 'messages');

    expect($history->json('messages.0.conteudo'))->toBe('Qual a data?');
    expect($history->json('messages.1.conteudo'))->toBe('A prova é na sexta.');
});

it('opens a conversation when teacher messages student and parent can reply in same thread', function () {
    $ctx = setupMessageThreadContext();

    Sanctum::actingAs($ctx['teacherUser']);
    $create = $this->postJson('/api/mobile/messages', [
        'aluno_id' => $ctx['aluno']->id,
        'titulo' => 'Reunião de pais',
        'conteudo' => 'Podemos conversar amanhã?',
    ]);

    $create->assertCreated();
    $conversaId = $create->json('message.conversa_id');
    $mensagemId = $create->json('message.id');

    Sanctum::actingAs($ctx['parent']);
    $reply = $this->postJson('/api/mobile/messages', [
        'mensagem_pai_id' => $mensagemId,
        'conteudo' => 'Sim, estou disponível.',
    ]);

    $reply->assertCreated()
        ->assertJsonPath('message.conversa_id', $conversaId)
        ->assertJsonPath('message.destinatario.id', $ctx['teacherUser']->id);

    Sanctum::actingAs($ctx['teacherUser']);
    $list = $this->getJson('/api/mobile/messages');

    $list->assertSuccessful()
        ->assertJsonCount(1, 'conversas')
        ->assertJsonPath('conversas.0.conversa_id', $conversaId)
        ->assertJsonPath('conversas.0.messages_count', 2)
        ->assertJsonPath('conversas.0.unread_count', 1);
});

it('allows reply using conversa_id without mensagem_pai_id', function () {
    $ctx = setupMessageThreadContext();

    Sanctum::actingAs($ctx['parent']);
    $create = $this->postJson('/api/mobile/messages', [
        'aluno_id' => $ctx['aluno']->id,
        'professor_id' => $ctx['teacher']->id,
        'titulo' => 'Olá',
        'conteudo' => 'Primeira',
    ]);

    $conversaId = $create->json('message.conversa_id');

    Sanctum::actingAs($ctx['teacherUser']);
    $reply = $this->postJson('/api/mobile/messages', [
        'conversa_id' => $conversaId,
        'titulo' => 'Resposta',
        'conteudo' => 'Segunda',
    ]);

    $reply->assertCreated()
        ->assertJsonPath('message.conversa_id', $conversaId);
});

it('forbids unrelated teacher from accessing conversation', function () {
    $ctx = setupMessageThreadContext();

    Sanctum::actingAs($ctx['parent']);
    $create = $this->postJson('/api/mobile/messages', [
        'aluno_id' => $ctx['aluno']->id,
        'professor_id' => $ctx['teacher']->id,
        'titulo' => 'Privado',
        'conteudo' => 'Só para a professora',
    ]);

    $conversaId = $create->json('message.conversa_id');

    $otherTeacherUser = User::factory()->create(['ativo' => true]);
    Teacher::factory()->create([
        'tenant_id' => $ctx['tenant']->id,
        'usuario_id' => $otherTeacherUser->id,
        'ativo' => true,
    ]);

    Sanctum::actingAs($otherTeacherUser);
    $this->getJson("/api/mobile/messages/conversas/{$conversaId}")
        ->assertForbidden();

    $this->postJson('/api/mobile/messages', [
        'conversa_id' => $conversaId,
        'conteudo' => 'Tentativa',
    ])->assertForbidden();
});

it('forbids unrelated parent from accessing conversation', function () {
    $ctx = setupMessageThreadContext();

    Sanctum::actingAs($ctx['teacherUser']);
    $create = $this->postJson('/api/mobile/messages', [
        'aluno_id' => $ctx['aluno']->id,
        'titulo' => 'Recado',
        'conteudo' => 'Para o responsável',
    ]);

    $conversaId = $create->json('message.conversa_id');

    $otherParent = User::factory()->create(['ativo' => true]);
    Responsavel::create([
        'tenant_id' => $ctx['tenant']->id,
        'usuario_id' => $otherParent->id,
        'cpf' => $otherParent->cpf,
    ]);

    Sanctum::actingAs($otherParent);
    $this->getJson("/api/mobile/messages/conversas/{$conversaId}")
        ->assertForbidden();
});

it('groups messages of the same conversation in the index', function () {
    $ctx = setupMessageThreadContext();

    Sanctum::actingAs($ctx['parent']);
    $first = $this->postJson('/api/mobile/messages', [
        'aluno_id' => $ctx['aluno']->id,
        'professor_id' => $ctx['teacher']->id,
        'titulo' => 'Thread',
        'conteudo' => '1',
    ]);

    $conversaId = $first->json('message.conversa_id');

    Sanctum::actingAs($ctx['teacherUser']);
    $this->postJson('/api/mobile/messages', [
        'mensagem_pai_id' => $first->json('message.id'),
        'conteudo' => '2',
    ])->assertCreated();

    Sanctum::actingAs($ctx['parent']);
    $list = $this->getJson('/api/mobile/messages');

    $list->assertSuccessful()
        ->assertJsonCount(1, 'conversas')
        ->assertJsonCount(1, 'messages')
        ->assertJsonPath('conversas.0.conversa_id', $conversaId)
        ->assertJsonPath('conversas.0.ultima_mensagem.conteudo', '2');
});

it('marks conversation as read for the recipient', function () {
    $ctx = setupMessageThreadContext();

    Sanctum::actingAs($ctx['parent']);
    $create = $this->postJson('/api/mobile/messages', [
        'aluno_id' => $ctx['aluno']->id,
        'professor_id' => $ctx['teacher']->id,
        'titulo' => 'Oi',
        'conteudo' => 'Mensagem',
    ]);

    $conversaId = $create->json('message.conversa_id');

    Sanctum::actingAs($ctx['teacherUser']);
    $this->patchJson("/api/mobile/messages/conversas/{$conversaId}/read")
        ->assertSuccessful()
        ->assertJsonPath('updated', 1);

    expect(Message::find($create->json('message.id'))->lida)->toBeTrue();
});
