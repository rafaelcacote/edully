<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Message;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

function disableMessagesIndexAuthMiddleware(): void
{
    test()->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);
}

/**
 * @return array{tenant: Tenant, user: User, turma: Turma, alunos: list<Student>}
 */
function setupMessagesIndexContext(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $user->tenants()->attach($tenant->id);

    $turma = Turma::create([
        'tenant_id' => $tenant->id,
        'nome' => '5º Ano A',
        'serie' => '5º ano',
        'turma_letra' => 'A',
        'ano_letivo' => 2026,
        'ativo' => true,
    ]);

    $alunos = collect([
        Student::create([
            'tenant_id' => $tenant->id,
            'nome' => 'Aluno Um',
            'ativo' => true,
        ]),
        Student::create([
            'tenant_id' => $tenant->id,
            'nome' => 'Aluno Dois',
            'ativo' => true,
        ]),
        Student::create([
            'tenant_id' => $tenant->id,
            'nome' => 'Aluno Três',
            'ativo' => true,
        ]),
    ]);

    $driver = DB::connection('shared')->getDriverName();
    $matriculasTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';

    foreach ($alunos as $aluno) {
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
    }

    return [
        'tenant' => $tenant,
        'user' => $user,
        'turma' => $turma,
        'alunos' => $alunos->all(),
    ];
}

it('groups turma-wide messages into a single listing row', function () {
    disableMessagesIndexAuthMiddleware();

    ['tenant' => $tenant, 'user' => $user, 'turma' => $turma, 'alunos' => $alunos] = setupMessagesIndexContext();

    $createdAt = now();

    foreach ($alunos as $aluno) {
        Message::create([
            'tenant_id' => $tenant->id,
            'remetente_id' => $user->id,
            'aluno_id' => $aluno->id,
            'turma_id' => $turma->id,
            'conversa_id' => (string) Str::uuid(),
            'titulo' => 'Recado da turma',
            'conteudo' => 'Mensagem para todos',
            'tipo' => 'outro',
            'prioridade' => 'normal',
            'lida' => false,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    Message::create([
        'tenant_id' => $tenant->id,
        'remetente_id' => $user->id,
        'aluno_id' => $alunos[0]->id,
        'conversa_id' => (string) Str::uuid(),
        'titulo' => 'Recado individual',
        'conteudo' => 'Só para um aluno',
        'tipo' => 'outro',
        'prioridade' => 'normal',
        'lida' => false,
    ]);

    $response = $this->actingAs($user)->get('/school/messages');

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('school/messages/Index')
        ->has('messages.data', 2)
        ->where('messages.data', function ($messages) use ($turma, $alunos) {
            $rows = collect($messages);
            $turmaRow = $rows->firstWhere('destinatario_tipo', 'turma');
            $alunoRow = $rows->firstWhere('destinatario_tipo', 'aluno');

            return $turmaRow !== null
                && $turmaRow['turma']['id'] === $turma->id
                && $turmaRow['turma']['nome'] === $turma->nome
                && $turmaRow['aluno'] === null
                && $turmaRow['titulo'] === 'Recado da turma'
                && $alunoRow !== null
                && $alunoRow['aluno']['id'] === $alunos[0]->id
                && $alunoRow['turma'] === null;
        })
    );
});

it('lists individual turma fan-out rows when filtering by aluno', function () {
    disableMessagesIndexAuthMiddleware();

    ['tenant' => $tenant, 'user' => $user, 'turma' => $turma, 'alunos' => $alunos] = setupMessagesIndexContext();

    $createdAt = now();

    foreach ($alunos as $aluno) {
        Message::create([
            'tenant_id' => $tenant->id,
            'remetente_id' => $user->id,
            'aluno_id' => $aluno->id,
            'turma_id' => $turma->id,
            'conversa_id' => (string) Str::uuid(),
            'titulo' => 'Recado da turma',
            'conteudo' => 'Mensagem para todos',
            'tipo' => 'outro',
            'prioridade' => 'normal',
            'lida' => false,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    $response = $this->actingAs($user)->get('/school/messages?aluno_id='.$alunos[1]->id);

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('school/messages/Index')
        ->has('messages.data', 1)
        ->where('messages.data.0.destinatario_tipo', 'aluno')
        ->where('messages.data.0.aluno.id', $alunos[1]->id)
        ->where('messages.data.0.turma.id', $turma->id)
    );
});
