<?php

use App\Enums\StatusDocumento;
use App\Enums\TipoDocumento;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Documento;
use App\Models\PushToken;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

beforeEach(function () {
    Storage::fake('public');

    $this->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);

    Http::fake([
        'https://exp.host/--/api/v2/push/send' => Http::response(['data' => [['status' => 'ok']]], 200),
    ]);
});

/**
 * @return array{
 *     tenant: Tenant,
 *     admin: User,
 *     aluno: Student,
 *     turma: Turma,
 *     professorSelecionado: Teacher,
 *     professorNaoSelecionado: Teacher,
 *     professorOutraTurma: Teacher,
 *     documento: Documento
 * }
 */
function setupAtestadoTeachersContext(): array
{
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['ativo' => true]);
    $admin->tenants()->attach($tenant->id);

    $turma = Turma::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Turma 4B',
        'serie' => '4º ano',
        'turma_letra' => 'B',
        'ano_letivo' => 2026,
        'ativo' => true,
    ]);

    $outraTurma = Turma::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Turma 5A',
        'serie' => '5º ano',
        'turma_letra' => 'A',
        'ano_letivo' => 2026,
        'ativo' => true,
    ]);

    $aluno = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Lucas Atestado',
        'ativo' => true,
    ]);

    $driver = DB::connection('shared')->getDriverName();
    $matriculasTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';
    $professorTurmaTable = $driver === 'sqlite' ? 'professor_turma' : 'escola.professor_turma';

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

    $teacherUser1 = User::factory()->create(['ativo' => true, 'nome_completo' => 'Prof Selecionado']);
    $professorSelecionado = Teacher::factory()->create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $teacherUser1->id,
        'ativo' => true,
    ]);

    $teacherUser2 = User::factory()->create(['ativo' => true, 'nome_completo' => 'Prof Nao Selecionado']);
    $professorNaoSelecionado = Teacher::factory()->create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $teacherUser2->id,
        'ativo' => true,
    ]);

    $teacherUser3 = User::factory()->create(['ativo' => true, 'nome_completo' => 'Prof Outra Turma']);
    $professorOutraTurma = Teacher::factory()->create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $teacherUser3->id,
        'ativo' => true,
    ]);

    foreach ([$professorSelecionado, $professorNaoSelecionado] as $professor) {
        DB::connection('shared')->table($professorTurmaTable)->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'professor_id' => $professor->id,
            'turma_id' => $turma->id,
            'created_at' => now(),
        ]);
    }

    DB::connection('shared')->table($professorTurmaTable)->insert([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'professor_id' => $professorOutraTurma->id,
        'turma_id' => $outraTurma->id,
        'created_at' => now(),
    ]);

    PushToken::create([
        'usuario_id' => $teacherUser1->id,
        'push_token' => 'ExponentPushToken[teacher-selected]',
        'platform' => 'android',
        'last_used_at' => now(),
    ]);

    PushToken::create([
        'usuario_id' => $teacherUser2->id,
        'push_token' => 'ExponentPushToken[teacher-unselected]',
        'platform' => 'android',
        'last_used_at' => now(),
    ]);

    $documento = Documento::create([
        'tenant_id' => $tenant->id,
        'aluno_id' => $aluno->id,
        'criado_por' => $admin->id,
        'tipo' => TipoDocumento::Atestado,
        'status' => StatusDocumento::Enviado,
        'titulo' => 'Atestado médico',
        'data_inicio' => '2026-09-20',
        'data_fim' => '2026-09-22',
        'anexo_url' => 'https://example.com/atestado.pdf',
    ]);

    return compact(
        'tenant',
        'admin',
        'aluno',
        'turma',
        'professorSelecionado',
        'professorNaoSelecionado',
        'professorOutraTurma',
        'documento'
    );
}

it('lists turma teachers on atestado show page', function () {
    [
        'admin' => $admin,
        'documento' => $documento,
        'professorSelecionado' => $professorSelecionado,
        'professorOutraTurma' => $professorOutraTurma,
    ] = setupAtestadoTeachersContext();

    $response = $this->actingAs($admin)->get('/school/documentos/'.$documento->id);

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('school/documentos/Show')
        ->where('documento.pode_notificar_professores', true)
        ->has('professores', 2)
        ->where('professores.0.nome', fn ($nome) => in_array($nome, ['Prof Selecionado', 'Prof Nao Selecionado'], true))
    );

    $ids = collect($response->inertiaProps('professores'))->pluck('id')->all();
    expect($ids)->toContain($professorSelecionado->id);
    expect($ids)->not->toContain($professorOutraTurma->id);
});

it('notifies selected teachers about atestado via push', function () {
    [
        'admin' => $admin,
        'documento' => $documento,
        'aluno' => $aluno,
        'professorSelecionado' => $professorSelecionado,
        'professorNaoSelecionado' => $professorNaoSelecionado,
    ] = setupAtestadoTeachersContext();

    $response = $this->actingAs($admin)->post(
        '/school/documentos/'.$documento->id.'/notificar-professores',
        ['professor_ids' => [$professorSelecionado->id]]
    );

    $response->assertRedirect(route('school.documentos.show', $documento, absolute: false));

    $recado = \App\Models\Message::query()
        ->where('destinatario_id', $professorSelecionado->usuario_id)
        ->where('aluno_id', $aluno->id)
        ->first();

    expect($recado)->not->toBeNull();
    expect($recado->titulo)->toContain('Lucas Atestado');
    expect($recado->tipo)->toBe('aviso');
    expect($recado->prioridade)->toBe('alta');
    expect(\App\Models\Message::query()->where('destinatario_id', $professorNaoSelecionado->usuario_id)->exists())->toBeFalse();

    Http::assertSent(function ($request) {
        if ($request->url() !== 'https://exp.host/--/api/v2/push/send') {
            return false;
        }

        $payload = $request->data();
        $messages = is_array($payload[0] ?? null) ? $payload : [$payload];

        $hasSelected = collect($messages)->contains(function ($msg) {
            return ($msg['to'] ?? null) === 'ExponentPushToken[teacher-selected]'
                && ($msg['data']['type'] ?? null) === 'message';
        });

        $hasUnselected = collect($messages)->contains(
            fn ($msg) => ($msg['to'] ?? null) === 'ExponentPushToken[teacher-unselected]'
        );

        return $hasSelected && ! $hasUnselected;
    });
});

it('rejects notifying a teacher from another turma', function () {
    [
        'admin' => $admin,
        'documento' => $documento,
        'professorOutraTurma' => $professorOutraTurma,
    ] = setupAtestadoTeachersContext();

    $this->actingAs($admin)
        ->from(route('school.documentos.show', $documento, absolute: false))
        ->post('/school/documentos/'.$documento->id.'/notificar-professores', [
            'professor_ids' => [$professorOutraTurma->id],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('professor_ids');
});

it('does not allow notifying teachers for non-atestado documents', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['ativo' => true]);
    $admin->tenants()->attach($tenant->id);

    $aluno = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Aluno Pedido',
        'ativo' => true,
    ]);

    $documento = Documento::create([
        'tenant_id' => $tenant->id,
        'aluno_id' => $aluno->id,
        'criado_por' => $admin->id,
        'tipo' => TipoDocumento::PedidoDeclaracao,
        'status' => StatusDocumento::Enviado,
        'titulo' => 'Pedido de declaração',
    ]);

    $this->actingAs($admin)
        ->from(route('school.documentos.show', $documento, absolute: false))
        ->post('/school/documentos/'.$documento->id.'/notificar-professores', [
            'professor_ids' => [(string) Str::uuid()],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('documento');
});
