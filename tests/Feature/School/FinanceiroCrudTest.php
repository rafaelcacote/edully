<?php

use App\Enums\StatusCobranca;
use App\Enums\TipoCobranca;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Cobranca;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Http\UploadedFile;
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
function financeiroPivotTables(): array
{
    $driver = DB::connection('shared')->getDriverName();

    return [
        'matriculas' => $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma',
    ];
}

/**
 * @return array{tenant: Tenant, user: User, turma: Turma, aluno: Student}
 */
function setupFinanceiroContext(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $user->tenants()->attach($tenant->id);

    $turma = Turma::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Turma 1A',
        'serie' => '1º ano',
        'turma_letra' => 'A',
        'ano_letivo' => 2026,
        'ativo' => true,
    ]);

    $aluno = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Aluno Financeiro',
        'ativo' => true,
    ]);

    $tables = financeiroPivotTables();
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

    if (DB::connection('shared')->getDriverName() === 'sqlite') {
        $matriculaRow['matricula'] = $matriculaId;
        $matriculaRow['ativo'] = true;
    }

    DB::connection('shared')->table($tables['matriculas'])->insert($matriculaRow);

    return compact('tenant', 'user', 'turma', 'aluno');
}

it('lists cobrancas for the school tenant', function () {
    ['tenant' => $tenant, 'user' => $user, 'aluno' => $aluno] = setupFinanceiroContext();

    Cobranca::create([
        'tenant_id' => $tenant->id,
        'aluno_id' => $aluno->id,
        'tipo' => TipoCobranca::Mensalidade,
        'titulo' => 'Mensalidade Mar/2026',
        'referencia' => '2026-03',
        'valor' => 350,
        'vencimento' => '2026-03-10',
        'status' => StatusCobranca::Pendente,
    ]);

    $otherTenant = Tenant::factory()->create();
    $otherAluno = Student::create([
        'tenant_id' => $otherTenant->id,
        'nome' => 'Outro Aluno',
        'ativo' => true,
    ]);

    Cobranca::create([
        'tenant_id' => $otherTenant->id,
        'aluno_id' => $otherAluno->id,
        'tipo' => TipoCobranca::Mensalidade,
        'titulo' => 'Mensalidade outra escola',
        'referencia' => '2026-03',
        'valor' => 400,
        'vencimento' => '2026-03-10',
        'status' => StatusCobranca::Pendente,
    ]);

    $response = $this->actingAs($user)->get('/school/cobrancas');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('school/financeiro/Index')
        ->has('cobrancas.data', 1)
        ->where('cobrancas.data.0.titulo', 'Mensalidade Mar/2026')
    );
});

it('generates mensalidades in batch for turma students', function () {
    ['user' => $user, 'turma' => $turma, 'aluno' => $aluno] = setupFinanceiroContext();

    $response = $this->actingAs($user)->post('/school/cobrancas/mensalidades', [
        'turma_id' => $turma->id,
        'ano' => 2026,
        'mes' => 3,
        'valor' => 450.50,
        'vencimento' => '2026-03-10',
        'pix_chave' => 'escola@pix.com',
        'boleto' => UploadedFile::fake()->create('boleto.pdf', 100, 'application/pdf'),
    ]);

    $response->assertRedirect();

    $cobranca = Cobranca::query()->first();
    expect($cobranca)->not->toBeNull();
    expect($cobranca->aluno_id)->toBe($aluno->id);
    expect($cobranca->tipo)->toBe(TipoCobranca::Mensalidade);
    expect($cobranca->referencia)->toBe('2026-03');
    expect($cobranca->titulo)->toBe('Mensalidade Mar/2026');
    expect((float) $cobranca->valor)->toBe(450.50);
    expect($cobranca->status)->toBe(StatusCobranca::Pendente);
    expect($cobranca->pix_chave)->toBe('escola@pix.com');
    expect($cobranca->boleto_url)->not->toBeNull();
});

it('skips duplicate mensalidades for the same referencia', function () {
    ['tenant' => $tenant, 'user' => $user, 'turma' => $turma, 'aluno' => $aluno] = setupFinanceiroContext();

    Cobranca::create([
        'tenant_id' => $tenant->id,
        'aluno_id' => $aluno->id,
        'tipo' => TipoCobranca::Mensalidade,
        'titulo' => 'Mensalidade Mar/2026',
        'referencia' => '2026-03',
        'valor' => 300,
        'vencimento' => '2026-03-10',
        'status' => StatusCobranca::Pendente,
    ]);

    $response = $this->actingAs($user)->post('/school/cobrancas/mensalidades', [
        'turma_id' => $turma->id,
        'ano' => 2026,
        'mes' => 3,
        'valor' => 450,
        'vencimento' => '2026-03-15',
    ]);

    $response->assertRedirect();
    expect(Cobranca::query()->count())->toBe(1);
});

it('marks cobranca as paid', function () {
    ['tenant' => $tenant, 'user' => $user, 'aluno' => $aluno] = setupFinanceiroContext();

    $cobranca = Cobranca::create([
        'tenant_id' => $tenant->id,
        'aluno_id' => $aluno->id,
        'tipo' => TipoCobranca::Mensalidade,
        'titulo' => 'Mensalidade Abr/2026',
        'referencia' => '2026-04',
        'valor' => 350,
        'vencimento' => '2026-04-10',
        'status' => StatusCobranca::Pendente,
    ]);

    $response = $this->actingAs($user)->patch('/school/cobrancas/'.$cobranca->id.'/pagar', [
        'pago_em' => '2026-04-08',
        'pago_observacao' => 'Pago via PIX',
    ]);

    $response->assertRedirect(route('school.cobrancas.show', $cobranca, absolute: false));

    $cobranca->refresh();
    expect($cobranca->status)->toBe(StatusCobranca::Pago);
    expect($cobranca->pago_observacao)->toBe('Pago via PIX');
    expect($cobranca->pago_em)->not->toBeNull();
});

it('updates cobranca pix and boleto', function () {
    ['tenant' => $tenant, 'user' => $user, 'aluno' => $aluno] = setupFinanceiroContext();

    $cobranca = Cobranca::create([
        'tenant_id' => $tenant->id,
        'aluno_id' => $aluno->id,
        'tipo' => TipoCobranca::Mensalidade,
        'titulo' => 'Mensalidade Mai/2026',
        'referencia' => '2026-05',
        'valor' => 350,
        'vencimento' => '2026-05-10',
        'status' => StatusCobranca::Pendente,
    ]);

    $response = $this->actingAs($user)->patch('/school/cobrancas/'.$cobranca->id, [
        'titulo' => 'Mensalidade Mai/2026 atualizada',
        'valor' => 380,
        'vencimento' => '2026-05-12',
        'pix_chave' => 'nova-chave@pix.com',
        'pix_copia_cola' => '00020126580014br.gov.bcb.pix',
        'boleto' => UploadedFile::fake()->create('segunda-via.pdf', 120, 'application/pdf'),
    ]);

    $response->assertRedirect(route('school.cobrancas.show', $cobranca, absolute: false));

    $cobranca->refresh();
    expect($cobranca->titulo)->toBe('Mensalidade Mai/2026 atualizada');
    expect((float) $cobranca->valor)->toBe(380.0);
    expect($cobranca->pix_chave)->toBe('nova-chave@pix.com');
    expect($cobranca->boleto_url)->not->toBeNull();
});

it('returns 404 when showing cobranca from another tenant', function () {
    ['user' => $user] = setupFinanceiroContext();

    $otherTenant = Tenant::factory()->create();
    $otherAluno = Student::create([
        'tenant_id' => $otherTenant->id,
        'nome' => 'Aluno Outro Tenant',
        'ativo' => true,
    ]);

    $cobranca = Cobranca::create([
        'tenant_id' => $otherTenant->id,
        'aluno_id' => $otherAluno->id,
        'tipo' => TipoCobranca::Mensalidade,
        'titulo' => 'Mensalidade isolada',
        'referencia' => '2026-06',
        'valor' => 200,
        'vencimento' => '2026-06-10',
        'status' => StatusCobranca::Pendente,
    ]);

    $this->actingAs($user)
        ->get('/school/cobrancas/'.$cobranca->id)
        ->assertNotFound();
});
