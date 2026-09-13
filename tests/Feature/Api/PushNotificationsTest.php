<?php

use App\Models\PushToken;
use App\Models\Responsavel;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

function setupPushMessageContext(): array
{
    $tenant = Tenant::factory()->create();

    $parentUser = User::factory()->create(['ativo' => true, 'nome_completo' => 'Pai Push']);
    $teacherUser = User::factory()->create(['ativo' => true, 'nome_completo' => 'Prof Push']);

    $responsavel = Responsavel::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $parentUser->id,
        'cpf' => $parentUser->cpf,
    ]);

    $teacher = Teacher::factory()->create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $teacherUser->id,
        'ativo' => true,
    ]);

    $turma = Turma::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Turma Push',
        'serie' => '5º ano',
        'turma_letra' => 'A',
        'ano_letivo' => 2026,
        'ativo' => true,
    ]);

    $aluno = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Aluno Push',
        'ativo' => true,
    ]);

    $driver = DB::connection('shared')->getDriverName();
    $pivotTable = $driver === 'sqlite' ? 'aluno_responsavel' : 'escola.aluno_responsavel';
    $matriculasTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';
    $professorTurmaTable = $driver === 'sqlite' ? 'professor_turma' : 'escola.professor_turma';

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

    DB::connection('shared')->table($professorTurmaTable)->insert([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'professor_id' => $teacher->id,
        'turma_id' => $turma->id,
        'created_at' => now(),
    ]);

    $parentToken = $parentUser->createToken('mobile')->plainTextToken;
    $teacherToken = $teacherUser->createToken('mobile')->plainTextToken;

    return compact(
        'tenant',
        'parentUser',
        'teacherUser',
        'responsavel',
        'teacher',
        'turma',
        'aluno',
        'parentToken',
        'teacherToken'
    );
}

it('registers and removes push tokens for authenticated user', function () {
    $ctx = setupPushMessageContext();

    $register = $this->withHeader('Authorization', "Bearer {$ctx['parentToken']}")
        ->postJson('/api/mobile/push-tokens', [
            'push_token' => 'ExponentPushToken[parent-device]',
            'platform' => 'android',
        ]);

    $register->assertCreated();

    expect(
        PushToken::query()
            ->where('usuario_id', $ctx['parentUser']->id)
            ->where('push_token', 'ExponentPushToken[parent-device]')
            ->exists()
    )->toBeTrue();

    $remove = $this->withHeader('Authorization', "Bearer {$ctx['parentToken']}")
        ->deleteJson('/api/mobile/push-tokens', [
            'push_token' => 'ExponentPushToken[parent-device]',
        ]);

    $remove->assertSuccessful();

    expect(
        PushToken::query()
            ->where('push_token', 'ExponentPushToken[parent-device]')
            ->exists()
    )->toBeFalse();
});

it('sends expo push when parent messages teacher', function () {
    Http::fake([
        'exp.host/*' => Http::response(['data' => [['status' => 'ok']]], 200),
    ]);

    $ctx = setupPushMessageContext();

    PushToken::create([
        'usuario_id' => $ctx['teacherUser']->id,
        'push_token' => 'ExponentPushToken[teacher-device]',
        'platform' => 'android',
        'last_used_at' => now(),
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$ctx['parentToken']}")
        ->postJson('/api/mobile/messages', [
            'aluno_id' => $ctx['aluno']->id,
            'professor_id' => $ctx['teacher']->id,
            'titulo' => 'Dúvida da prova',
            'conteudo' => 'Olá professora, tenho uma dúvida.',
        ]);

    $response->assertCreated();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), 'exp.host')) {
            return false;
        }

        $payload = $request->data();
        $first = is_array($payload[0] ?? null) ? $payload[0] : $payload;

        return ($first['to'] ?? null) === 'ExponentPushToken[teacher-device]'
            && ($first['data']['type'] ?? null) === 'message';
    });
});

it('sends expo push to parents when teacher messages a student', function () {
    Http::fake([
        'exp.host/*' => Http::response(['data' => [['status' => 'ok']]], 200),
    ]);

    $ctx = setupPushMessageContext();

    PushToken::create([
        'usuario_id' => $ctx['parentUser']->id,
        'push_token' => 'ExponentPushToken[parent-device-2]',
        'platform' => 'ios',
        'last_used_at' => now(),
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$ctx['teacherToken']}")
        ->postJson('/api/mobile/messages', [
            'aluno_id' => $ctx['aluno']->id,
            'titulo' => 'Recado importante',
            'conteudo' => 'Por favor, leia o material.',
        ]);

    $response->assertCreated();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), 'exp.host')) {
            return false;
        }

        $payload = $request->data();
        $first = is_array($payload[0] ?? null) ? $payload[0] : $payload;

        return ($first['to'] ?? null) === 'ExponentPushToken[parent-device-2]';
    });
});

it('sends expo push on conversation reply', function () {
    Http::fake([
        'exp.host/*' => Http::response(['data' => [['status' => 'ok']]], 200),
    ]);

    $ctx = setupPushMessageContext();

    PushToken::create([
        'usuario_id' => $ctx['parentUser']->id,
        'push_token' => 'ExponentPushToken[parent-reply]',
        'platform' => 'android',
        'last_used_at' => now(),
    ]);

    $create = $this->withHeader('Authorization', "Bearer {$ctx['parentToken']}")
        ->postJson('/api/mobile/messages', [
            'aluno_id' => $ctx['aluno']->id,
            'professor_id' => $ctx['teacher']->id,
            'titulo' => 'Primeira',
            'conteudo' => 'Oi professora',
        ]);

    $create->assertCreated();
    $conversaId = $create->json('message.conversa_id');
    expect($conversaId)->not->toBeEmpty();

    // Limpa o fake da criação (sem token do professor → sem HTTP) e prepara a reply
    Http::fake([
        'exp.host/*' => Http::response(['data' => [['status' => 'ok']]], 200),
    ]);

    $reply = $this->withHeader('Authorization', "Bearer {$ctx['teacherToken']}")
        ->postJson('/api/mobile/messages', [
            'conversa_id' => $conversaId,
            'conteudo' => 'Olá, recebi sua mensagem.',
        ]);

    $reply->assertCreated();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), 'exp.host')) {
            return false;
        }

        $payload = $request->data();
        $first = is_array($payload[0] ?? null) ? $payload[0] : $payload;

        return ($first['to'] ?? null) === 'ExponentPushToken[parent-reply]';
    });
});
