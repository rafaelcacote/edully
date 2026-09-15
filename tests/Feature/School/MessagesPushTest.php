<?php

use App\Http\Middleware\HandleInertiaRequests;
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
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

function setupSchoolMessagePushContext(): array
{
    $tenant = Tenant::factory()->create();

    $parentUser = User::factory()->create(['ativo' => true, 'nome_completo' => 'Pai Web Push']);
    $teacherUser = User::factory()->create(['ativo' => true, 'nome_completo' => 'Prof Web Push']);
    $teacherUser->tenants()->attach($tenant->id);

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
        'nome' => 'Turma Web Push',
        'serie' => '5º ano',
        'turma_letra' => 'A',
        'ano_letivo' => 2026,
        'ativo' => true,
    ]);

    $aluno = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Aluno Web Push',
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

    return compact('tenant', 'parentUser', 'teacherUser', 'responsavel', 'teacher', 'turma', 'aluno');
}

it('sends expo push to parents when school web creates a recado for a student', function () {
    $this->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);

    Http::fake([
        'exp.host/*' => Http::response(['data' => [['status' => 'ok']]], 200),
    ]);

    $ctx = setupSchoolMessagePushContext();

    PushToken::create([
        'usuario_id' => $ctx['parentUser']->id,
        'push_token' => 'ExponentPushToken[web-parent-device]',
        'platform' => 'android',
        'last_used_at' => now(),
    ]);

    $response = $this->actingAs($ctx['teacherUser'])->post('/school/messages', [
        'aluno_id' => $ctx['aluno']->id,
        'titulo' => 'Recado via web',
        'conteudo' => 'Mensagem enviada pelo painel da escola.',
        'tipo' => 'outro',
        'prioridade' => 'normal',
    ]);

    $response->assertRedirect(route('school.messages.index', absolute: false));

    $this->assertDatabaseHas('mensagens', [
        'tenant_id' => $ctx['tenant']->id,
        'remetente_id' => $ctx['teacherUser']->id,
        'aluno_id' => $ctx['aluno']->id,
        'titulo' => 'Recado via web',
    ], 'shared');

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), 'exp.host')) {
            return false;
        }

        $payload = $request->data();
        $first = is_array($payload[0] ?? null) ? $payload[0] : $payload;

        return ($first['to'] ?? null) === 'ExponentPushToken[web-parent-device]'
            && ($first['data']['type'] ?? null) === 'message';
    });
});

it('sends expo push to parents when school web creates a recado for a turma', function () {
    $this->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);

    Http::fake([
        'exp.host/*' => Http::response(['data' => [['status' => 'ok']]], 200),
    ]);

    $ctx = setupSchoolMessagePushContext();

    PushToken::create([
        'usuario_id' => $ctx['parentUser']->id,
        'push_token' => 'ExponentPushToken[web-turma-parent]',
        'platform' => 'ios',
        'last_used_at' => now(),
    ]);

    $response = $this->actingAs($ctx['teacherUser'])->post('/school/messages', [
        'turma_id' => $ctx['turma']->id,
        'titulo' => 'Recado da turma',
        'conteudo' => 'Aviso para todos os alunos.',
        'tipo' => 'outro',
        'prioridade' => 'alta',
    ]);

    $response->assertRedirect(route('school.messages.index', absolute: false));

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), 'exp.host')) {
            return false;
        }

        $payload = $request->data();
        $first = is_array($payload[0] ?? null) ? $payload[0] : $payload;

        return ($first['to'] ?? null) === 'ExponentPushToken[web-turma-parent]';
    });
});
