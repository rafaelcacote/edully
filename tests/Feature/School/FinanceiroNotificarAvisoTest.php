<?php

use App\Enums\PublicoEventoFinanceiro;
use App\Enums\StatusCobranca;
use App\Enums\StatusEventoFinanceiro;
use App\Enums\TipoCobranca;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Aviso;
use App\Models\Cobranca;
use App\Models\EventoFinanceiro;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

beforeEach(function () {
    Storage::fake('public');
    Http::fake([
        'https://exp.host/--/api/v2/push/send' => Http::response(['data' => [['status' => 'ok']]], 200),
    ]);

    $this->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);
});

it('notifies responsaveis via aviso from a cobranca', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['ativo' => true]);
    $admin->tenants()->attach($tenant->id);

    $aluno = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Maria Silva',
        'ativo' => true,
    ]);

    $cobranca = Cobranca::create([
        'tenant_id' => $tenant->id,
        'aluno_id' => $aluno->id,
        'tipo' => TipoCobranca::Mensalidade,
        'titulo' => 'Mensalidade Mar/2026',
        'referencia' => '2026-03',
        'valor' => 350,
        'vencimento' => '2026-12-10',
        'status' => StatusCobranca::Pendente,
        'boleto_url' => 'https://example.com/boleto.pdf',
        'pix_chave' => 'escola@pix.com',
    ]);

    $response = $this->actingAs($admin)->post('/school/cobrancas/'.$cobranca->id.'/notificar');

    $response->assertRedirect(route('school.cobrancas.show', $cobranca, absolute: false));

    $aviso = Aviso::query()->first();
    expect($aviso)->not->toBeNull();
    expect($aviso->tenant_id)->toBe($tenant->id);
    expect($aviso->criado_por)->toBe($admin->id);
    expect($aviso->publico_alvo)->toBe('responsaveis');
    expect($aviso->publicado)->toBeTrue();
    expect($aviso->titulo)->toContain('Mensalidade Mar/2026');
    expect($aviso->conteudo)->toContain('Maria Silva');
    expect($aviso->conteudo)->toContain('app Edully');
    expect($aviso->conteudo)->toContain('R$ 350,00');
    expect($aviso->anexo_url)->toBe('https://example.com/boleto.pdf');
    expect($aviso->prioridade)->toBe('normal');
});

it('uses alta prioridade when notifying an overdue cobranca', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['ativo' => true]);
    $admin->tenants()->attach($tenant->id);

    $aluno = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'João',
        'ativo' => true,
    ]);

    $cobranca = Cobranca::create([
        'tenant_id' => $tenant->id,
        'aluno_id' => $aluno->id,
        'tipo' => TipoCobranca::Mensalidade,
        'titulo' => 'Mensalidade Jan/2026',
        'referencia' => '2026-01',
        'valor' => 300,
        'vencimento' => now()->subDays(5)->toDateString(),
        'status' => StatusCobranca::Pendente,
    ]);

    $this->actingAs($admin)->post('/school/cobrancas/'.$cobranca->id.'/notificar')
        ->assertRedirect();

    expect(Aviso::query()->first()->prioridade)->toBe('alta');
});

it('does not notify cancelled cobranca', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['ativo' => true]);
    $admin->tenants()->attach($tenant->id);

    $aluno = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Ana',
        'ativo' => true,
    ]);

    $cobranca = Cobranca::create([
        'tenant_id' => $tenant->id,
        'aluno_id' => $aluno->id,
        'tipo' => TipoCobranca::Mensalidade,
        'titulo' => 'Mensalidade cancelada',
        'referencia' => '2026-02',
        'valor' => 200,
        'vencimento' => '2026-02-10',
        'status' => StatusCobranca::Cancelado,
    ]);

    $this->actingAs($admin)->post('/school/cobrancas/'.$cobranca->id.'/notificar')
        ->assertRedirect();

    expect(Aviso::query()->count())->toBe(0);
});

it('notifies responsaveis via aviso from a published evento', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['ativo' => true]);
    $admin->tenants()->attach($tenant->id);

    $evento = EventoFinanceiro::create([
        'tenant_id' => $tenant->id,
        'titulo' => 'Festa junina',
        'descricao' => 'Contribuição da festa',
        'valor' => 50,
        'vencimento' => '2026-06-20',
        'publico' => PublicoEventoFinanceiro::TodosAtivos,
        'status' => StatusEventoFinanceiro::Publicado,
        'publicado_em' => now(),
    ]);

    $response = $this->actingAs($admin)->post('/school/cobrancas/eventos/'.$evento->id.'/notificar');

    $response->assertRedirect(route('school.eventos-financeiros.show', $evento, absolute: false));

    $aviso = Aviso::query()->first();
    expect($aviso)->not->toBeNull();
    expect($aviso->publico_alvo)->toBe('responsaveis');
    expect($aviso->publicado)->toBeTrue();
    expect($aviso->titulo)->toContain('Festa junina');
    expect($aviso->conteudo)->toContain('app Edully');
    expect($aviso->conteudo)->toContain('R$ 50,00');
});

it('does not notify draft evento', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['ativo' => true]);
    $admin->tenants()->attach($tenant->id);

    $evento = EventoFinanceiro::create([
        'tenant_id' => $tenant->id,
        'titulo' => 'Rascunho',
        'valor' => 10,
        'vencimento' => '2026-07-01',
        'publico' => PublicoEventoFinanceiro::TodosAtivos,
        'status' => StatusEventoFinanceiro::Rascunho,
    ]);

    $this->actingAs($admin)->post('/school/cobrancas/eventos/'.$evento->id.'/notificar')
        ->assertRedirect();

    expect(Aviso::query()->count())->toBe(0);
});
