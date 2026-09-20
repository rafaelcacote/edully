<?php

use App\Enums\PublicoEventoFinanceiro;
use App\Enums\StatusCobranca;
use App\Enums\StatusEventoFinanceiro;
use App\Enums\TipoCobranca;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Cobranca;
use App\Models\EventoFinanceiro;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
});

/**
 * @return array{matriculas: string}
 */
function eventosFinanceiroPivotTables(): array
{
    $driver = DB::connection('shared')->getDriverName();

    return [
        'matriculas' => $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma',
    ];
}

/**
 * @return array{tenant: Tenant, user: User, turma: Turma, aluno: Student, aluno2: Student}
 */
function setupEventoFinanceiroContext(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $user->tenants()->attach($tenant->id);

    $turma = Turma::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Turma Evento',
        'serie' => '2º ano',
        'turma_letra' => 'B',
        'ano_letivo' => 2026,
        'ativo' => true,
    ]);

    $aluno = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Aluno Evento 1',
        'ativo' => true,
    ]);

    $aluno2 = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Aluno Evento 2',
        'ativo' => true,
    ]);

    $tables = eventosFinanceiroPivotTables();

    foreach ([$aluno, $aluno2] as $student) {
        $matriculaId = (string) Str::uuid();
        $matriculaRow = [
            'id' => $matriculaId,
            'tenant_id' => $tenant->id,
            'aluno_id' => $student->id,
            'turma_id' => $turma->id,
            'data_matricula' => now()->toDateString(),
            'status' => 'ativo',
            'created_at' => now(),
        ];

        if (DB::connection('shared')->getDriverName() === 'sqlite') {
            $matriculaRow['matricula'] = $matriculaId;
            $matriculaRow['ativo'] = true;
        }

        DB::connection('shared')->table($tables['matriculas'])->insert($matriculaRow);
    }

    return compact('tenant', 'user', 'turma', 'aluno', 'aluno2');
}

it('lists eventos financeiros for the school tenant', function () {
    ['tenant' => $tenant, 'user' => $user] = setupEventoFinanceiroContext();

    EventoFinanceiro::create([
        'tenant_id' => $tenant->id,
        'titulo' => 'Festa junina',
        'valor' => 50,
        'vencimento' => '2026-06-20',
        'publico' => PublicoEventoFinanceiro::TodosAtivos,
        'status' => StatusEventoFinanceiro::Rascunho,
    ]);

    $otherTenant = Tenant::factory()->create();
    EventoFinanceiro::create([
        'tenant_id' => $otherTenant->id,
        'titulo' => 'Evento outra escola',
        'valor' => 80,
        'vencimento' => '2026-06-20',
        'publico' => PublicoEventoFinanceiro::TodosAtivos,
        'status' => StatusEventoFinanceiro::Rascunho,
    ]);

    $response = $this->actingAs($user)->get('/school/cobrancas/eventos');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('school/financeiro/eventos/Index')
        ->has('eventos.data', 1)
        ->where('eventos.data.0.titulo', 'Festa junina')
    );
});

it('creates an evento as rascunho', function () {
    ['user' => $user, 'turma' => $turma] = setupEventoFinanceiroContext();

    $response = $this->actingAs($user)->post('/school/cobrancas/eventos', [
        'titulo' => 'Uniforme escolar',
        'descricao' => 'Kit completo',
        'valor' => 120,
        'vencimento' => '2026-07-15',
        'publico' => 'turma',
        'turma_id' => $turma->id,
        'pix_chave' => 'escola@pix.com',
    ]);

    $evento = EventoFinanceiro::query()->first();
    expect($evento)->not->toBeNull();

    $response->assertRedirect(route('school.eventos-financeiros.show', $evento, absolute: false));
    expect($evento->status)->toBe(StatusEventoFinanceiro::Rascunho);
    expect($evento->publico)->toBe(PublicoEventoFinanceiro::Turma);
    expect($evento->turma_id)->toBe($turma->id);
    expect(Cobranca::query()->count())->toBe(0);
});

it('publishes evento for turma and generates cobrancas', function () {
    ['tenant' => $tenant, 'user' => $user, 'turma' => $turma, 'aluno' => $aluno, 'aluno2' => $aluno2] = setupEventoFinanceiroContext();

    $evento = EventoFinanceiro::create([
        'tenant_id' => $tenant->id,
        'titulo' => 'Passeio pedagógico',
        'descricao' => 'Museu',
        'valor' => 75.5,
        'vencimento' => '2026-08-10',
        'publico' => PublicoEventoFinanceiro::Turma,
        'turma_id' => $turma->id,
        'status' => StatusEventoFinanceiro::Rascunho,
        'pix_chave' => 'pix@escola.com',
    ]);

    $response = $this->actingAs($user)->post('/school/cobrancas/eventos/'.$evento->id.'/publicar');

    $response->assertRedirect(route('school.eventos-financeiros.show', $evento, absolute: false));

    $evento->refresh();
    expect($evento->status)->toBe(StatusEventoFinanceiro::Publicado);
    expect($evento->publicado_em)->not->toBeNull();

    $cobrancas = Cobranca::query()->orderBy('aluno_id')->get();
    expect($cobrancas)->toHaveCount(2);
    expect($cobrancas->pluck('aluno_id')->map(fn ($id) => (string) $id)->sort()->values()->all())
        ->toEqual(collect([$aluno->id, $aluno2->id])->sort()->values()->all());
    expect($cobrancas->every(fn (Cobranca $c) => $c->tipo === TipoCobranca::Evento))->toBeTrue();
    expect($cobrancas->every(fn (Cobranca $c) => $c->status === StatusCobranca::Pendente))->toBeTrue();
    expect($cobrancas->every(fn (Cobranca $c) => $c->evento_financeiro_id === $evento->id))->toBeTrue();
    expect((float) $cobrancas->first()->valor)->toBe(75.5);
});

it('publishes evento for selected alunos only', function () {
    ['tenant' => $tenant, 'user' => $user, 'aluno' => $aluno, 'aluno2' => $aluno2] = setupEventoFinanceiroContext();

    $evento = EventoFinanceiro::create([
        'tenant_id' => $tenant->id,
        'titulo' => 'Camiseta da turma',
        'valor' => 40,
        'vencimento' => '2026-09-01',
        'publico' => PublicoEventoFinanceiro::Alunos,
        'status' => StatusEventoFinanceiro::Rascunho,
    ]);
    $evento->syncAlunosSelecionados([$aluno->id]);

    $this->actingAs($user)->post('/school/cobrancas/eventos/'.$evento->id.'/publicar')
        ->assertRedirect();

    expect(Cobranca::query()->count())->toBe(1);
    expect(Cobranca::query()->first()->aluno_id)->toBe($aluno->id);
    expect(Cobranca::query()->where('aluno_id', $aluno2->id)->exists())->toBeFalse();
});

it('creates and publishes evento in one step', function () {
    ['user' => $user, 'turma' => $turma] = setupEventoFinanceiroContext();

    $response = $this->actingAs($user)->post('/school/cobrancas/eventos', [
        'titulo' => 'Material didático',
        'valor' => 90,
        'vencimento' => '2026-10-05',
        'publico' => 'turma',
        'turma_id' => $turma->id,
        'publicar_agora' => true,
    ]);

    $evento = EventoFinanceiro::query()->first();
    expect($evento)->not->toBeNull();
    $response->assertRedirect(route('school.eventos-financeiros.show', $evento, absolute: false));

    expect($evento->status)->toBe(StatusEventoFinanceiro::Publicado);
    expect(Cobranca::query()->count())->toBe(2);
});

it('returns 404 when accessing evento from another tenant', function () {
    ['user' => $user] = setupEventoFinanceiroContext();

    $otherTenant = Tenant::factory()->create();
    $evento = EventoFinanceiro::create([
        'tenant_id' => $otherTenant->id,
        'titulo' => 'Evento isolado',
        'valor' => 10,
        'vencimento' => '2026-11-01',
        'publico' => PublicoEventoFinanceiro::TodosAtivos,
        'status' => StatusEventoFinanceiro::Rascunho,
    ]);

    $this->actingAs($user)
        ->get('/school/cobrancas/eventos/'.$evento->id)
        ->assertNotFound();
});

it('does not allow publishing an already published evento', function () {
    ['tenant' => $tenant, 'user' => $user, 'turma' => $turma] = setupEventoFinanceiroContext();

    $evento = EventoFinanceiro::create([
        'tenant_id' => $tenant->id,
        'titulo' => 'Já publicado',
        'valor' => 20,
        'vencimento' => '2026-12-01',
        'publico' => PublicoEventoFinanceiro::Turma,
        'turma_id' => $turma->id,
        'status' => StatusEventoFinanceiro::Publicado,
        'publicado_em' => now(),
    ]);

    $this->actingAs($user)
        ->post('/school/cobrancas/eventos/'.$evento->id.'/publicar')
        ->assertSessionHasErrors('status');
});
