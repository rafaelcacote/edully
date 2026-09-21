<?php

use App\Enums\StatusCobranca;
use App\Enums\TipoCobranca;
use App\Models\Cobranca;
use App\Models\Responsavel;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @return array{tenant: Tenant, user: User, token: string, aluno: Student}
 */
function setupApiCobrancasContext(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $responsavel = Responsavel::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'cpf' => $user->cpf,
    ]);

    $aluno = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Aluno Cobrancas',
        'ativo' => true,
    ]);

    $driver = DB::connection('shared')->getDriverName();
    $pivotTable = $driver === 'sqlite' ? 'aluno_responsavel' : 'escola.aluno_responsavel';

    DB::connection('shared')->table($pivotTable)->insert([
        'id' => (string) Str::uuid(),
        'aluno_id' => $aluno->id,
        'responsavel_id' => $responsavel->id,
        'tenant_id' => $tenant->id,
        'principal' => true,
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    return compact('tenant', 'user', 'token', 'aluno');
}

it('requires authentication to list cobrancas', function () {
    $response = $this->getJson('/api/mobile/students/'.(string) Str::uuid().'/cobrancas');

    $response->assertUnauthorized();
});

it('forbids teachers from listing cobrancas', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    Teacher::factory()->create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'ativo' => true,
    ]);

    $aluno = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Aluno Professor',
        'ativo' => true,
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/mobile/students/{$aluno->id}/cobrancas")
        ->assertForbidden();
});

it('returns 404 when student is not linked to responsavel', function () {
    ['token' => $token] = setupApiCobrancasContext();

    $otherTenant = Tenant::factory()->create();
    $otherAluno = Student::create([
        'tenant_id' => $otherTenant->id,
        'nome' => 'Outro Aluno',
        'ativo' => true,
    ]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/mobile/students/{$otherAluno->id}/cobrancas")
        ->assertNotFound();
});

it('lists cobrancas for linked student with filters and meta', function () {
    ['tenant' => $tenant, 'token' => $token, 'aluno' => $aluno] = setupApiCobrancasContext();

    $pendente = Cobranca::create([
        'tenant_id' => $tenant->id,
        'aluno_id' => $aluno->id,
        'tipo' => TipoCobranca::Mensalidade,
        'titulo' => 'Mensalidade Mar/2026',
        'referencia' => '2026-03',
        'valor' => 350.5,
        'vencimento' => '2026-12-10',
        'status' => StatusCobranca::Pendente,
        'boleto_url' => 'https://example.com/boleto.pdf',
        'pix_chave' => 'escola@pix.com',
        'pix_copia_cola' => '00020126580014br.gov.bcb.pix',
    ]);

    Cobranca::create([
        'tenant_id' => $tenant->id,
        'aluno_id' => $aluno->id,
        'tipo' => TipoCobranca::Evento,
        'titulo' => 'Festa junina',
        'valor' => 50,
        'vencimento' => '2026-06-20',
        'status' => StatusCobranca::Pago,
        'pago_em' => now(),
    ]);

    Cobranca::create([
        'tenant_id' => $tenant->id,
        'aluno_id' => $aluno->id,
        'tipo' => TipoCobranca::Mensalidade,
        'titulo' => 'Mensalidade Jan/2025',
        'referencia' => '2025-01',
        'valor' => 300,
        'vencimento' => '2025-01-10',
        'status' => StatusCobranca::Pendente,
    ]);

    $otherTenant = Tenant::factory()->create();
    $otherAluno = Student::create([
        'tenant_id' => $otherTenant->id,
        'nome' => 'Isolado',
        'ativo' => true,
    ]);
    Cobranca::create([
        'tenant_id' => $otherTenant->id,
        'aluno_id' => $otherAluno->id,
        'tipo' => TipoCobranca::Mensalidade,
        'titulo' => 'Outra escola',
        'referencia' => '2026-03',
        'valor' => 100,
        'vencimento' => '2026-12-10',
        'status' => StatusCobranca::Pendente,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/mobile/students/{$aluno->id}/cobrancas");

    $response->assertSuccessful()
        ->assertJsonStructure([
            'cobrancas' => [
                '*' => [
                    'id',
                    'aluno_id',
                    'tipo',
                    'tipo_label',
                    'titulo',
                    'valor',
                    'vencimento',
                    'status',
                    'status_exibicao',
                    'status_label',
                    'esta_atrasada',
                    'boleto_url',
                    'pix_copia_cola',
                    'pix_chave',
                ],
            ],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);

    expect($response->json('cobrancas'))->toHaveCount(3);
    expect($response->json('meta.total'))->toBe(3);

    $filteredStatus = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/mobile/students/{$aluno->id}/cobrancas?status=pendente&tipo=mensalidade&ano=2026");

    $filteredStatus->assertSuccessful();
    expect($filteredStatus->json('cobrancas'))->toHaveCount(1);
    expect($filteredStatus->json('cobrancas.0.id'))->toBe($pendente->id);
    expect($filteredStatus->json('cobrancas.0.valor'))->toBe(350.5);
    expect($filteredStatus->json('cobrancas.0.boleto_url'))->toBe('https://example.com/boleto.pdf');
});

it('filters atrasadas cobrancas', function () {
    ['tenant' => $tenant, 'token' => $token, 'aluno' => $aluno] = setupApiCobrancasContext();

    $atrasada = Cobranca::create([
        'tenant_id' => $tenant->id,
        'aluno_id' => $aluno->id,
        'tipo' => TipoCobranca::Mensalidade,
        'titulo' => 'Mensalidade atrasada',
        'referencia' => '2026-01',
        'valor' => 200,
        'vencimento' => now()->subDays(10)->toDateString(),
        'status' => StatusCobranca::Pendente,
    ]);

    Cobranca::create([
        'tenant_id' => $tenant->id,
        'aluno_id' => $aluno->id,
        'tipo' => TipoCobranca::Mensalidade,
        'titulo' => 'Mensalidade futura',
        'referencia' => '2026-12',
        'valor' => 200,
        'vencimento' => now()->addDays(20)->toDateString(),
        'status' => StatusCobranca::Pendente,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/mobile/students/{$aluno->id}/cobrancas?status=atrasado");

    $response->assertSuccessful();
    expect($response->json('cobrancas'))->toHaveCount(1);
    expect($response->json('cobrancas.0.id'))->toBe($atrasada->id);
    expect($response->json('cobrancas.0.esta_atrasada'))->toBeTrue();
    expect($response->json('cobrancas.0.status_exibicao'))->toBe('atrasado');
});

it('shows cobranca detail for linked student', function () {
    ['tenant' => $tenant, 'token' => $token, 'aluno' => $aluno] = setupApiCobrancasContext();

    $cobranca = Cobranca::create([
        'tenant_id' => $tenant->id,
        'aluno_id' => $aluno->id,
        'tipo' => TipoCobranca::Mensalidade,
        'titulo' => 'Mensalidade Mai/2026',
        'referencia' => '2026-05',
        'valor' => 400,
        'vencimento' => '2026-12-15',
        'status' => StatusCobranca::Pendente,
        'boleto_url' => 'https://example.com/mai.pdf',
        'pix_chave' => 'chave@pix.com',
        'pix_copia_cola' => 'pix-copia-cola-teste',
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/mobile/students/{$aluno->id}/cobrancas/{$cobranca->id}");

    $response->assertSuccessful()
        ->assertJsonPath('cobranca.id', $cobranca->id)
        ->assertJsonPath('cobranca.titulo', 'Mensalidade Mai/2026')
        ->assertJsonPath('cobranca.boleto_url', 'https://example.com/mai.pdf')
        ->assertJsonPath('cobranca.pix_chave', 'chave@pix.com')
        ->assertJsonPath('cobranca.pix_copia_cola', 'pix-copia-cola-teste')
        ->assertJsonPath('cobranca.valor', 400);
});

it('returns 404 when showing cobranca of another student', function () {
    ['tenant' => $tenant, 'token' => $token, 'aluno' => $aluno] = setupApiCobrancasContext();

    $otherAluno = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Outro Filho',
        'ativo' => true,
    ]);

    $cobranca = Cobranca::create([
        'tenant_id' => $tenant->id,
        'aluno_id' => $otherAluno->id,
        'tipo' => TipoCobranca::Mensalidade,
        'titulo' => 'Cobrança de outro aluno',
        'referencia' => '2026-08',
        'valor' => 150,
        'vencimento' => '2026-12-01',
        'status' => StatusCobranca::Pendente,
    ]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/mobile/students/{$aluno->id}/cobrancas/{$cobranca->id}")
        ->assertNotFound();
});
