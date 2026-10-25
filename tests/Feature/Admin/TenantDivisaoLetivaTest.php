<?php

use App\Enums\DivisaoLetiva;
use App\Http\Middleware\EnsureUserIsAdminGeral;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Nota;
use App\Models\Tenant;
use App\Models\Test;
use App\Models\User;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

beforeEach(function () {
    $this->withoutMiddleware([
        EnsureUserIsAdminGeral::class,
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);
});

it('stores a school as trimestral when chosen at registration', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/admin/tenants', [
        'nome' => 'Escola Trimestre',
        'email' => 'trimestre@example.com',
        'divisao_letiva' => 'trimestral',
    ]);

    $response->assertRedirect(route('tenants.index', absolute: false));

    $tenant = Tenant::query()->where('email', 'trimestre@example.com')->firstOrFail();

    expect($tenant->divisaoLetiva())->toBe(DivisaoLetiva::Trimestral)
        ->and($tenant->schoolSummary()['quantidade_periodos'])->toBe(3)
        ->and($tenant->schoolSummary()['unidade_periodo'])->toBe('trimestre');
});

it('defaults a new school to bimestral when the division is omitted', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/admin/tenants', [
        'nome' => 'Escola Padrao',
        'email' => 'padrao@example.com',
    ])->assertRedirect();

    expect(Tenant::query()->where('email', 'padrao@example.com')->firstOrFail()->divisaoLetiva())
        ->toBe(DivisaoLetiva::Bimestral);
});

it('switches a school to trimestral when no fourth period exists', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create([
        'nome' => 'Escola Modelo',
        'email' => 'modelo-divisao@example.com',
        'divisao_letiva' => DivisaoLetiva::Bimestral,
    ]);

    $response = $this->actingAs($user)->patch(route('tenants.update', $tenant), [
        'nome' => 'Escola Modelo',
        'email' => 'modelo-divisao@example.com',
        'divisao_letiva' => 'trimestral',
    ]);

    $response->assertRedirect(route('tenants.edit', $tenant, absolute: false));

    expect($tenant->fresh()->divisaoLetiva())->toBe(DivisaoLetiva::Trimestral);
});

it('blocks switching to trimestral when a fourth period grade exists', function () {
    $user = User::factory()->create();
    ['tenant' => $tenant, 'aluno' => $aluno, 'professor' => $professor, 'turma' => $turma, 'disciplina' => $disciplina] = setupNotaContext();
    $tenant->update(['email' => 'bloqueio-nota@example.com']);

    Nota::create([
        'tenant_id' => $tenant->id,
        'aluno_id' => $aluno->id,
        'professor_id' => $professor->id,
        'turma_id' => $turma->id,
        'disciplina' => 'Matemática',
        'disciplina_id' => $disciplina->id,
        'bimestre' => 4,
        'nota' => 8.0,
        'ano_letivo' => 2026,
    ]);

    $response = $this->actingAs($user)->patch(route('tenants.update', $tenant), [
        'nome' => $tenant->nome,
        'email' => 'bloqueio-nota@example.com',
        'divisao_letiva' => 'trimestral',
    ]);

    $response->assertSessionHasErrors('divisao_letiva');

    expect($tenant->fresh()->divisaoLetiva())->toBe(DivisaoLetiva::Bimestral);
});

it('blocks switching to trimestral when a fourth period test exists', function () {
    $user = User::factory()->create();
    ['tenant' => $tenant, 'professor' => $professor, 'turma' => $turma, 'disciplina' => $disciplina] = setupNotaContext();
    $tenant->update(['email' => 'bloqueio-prova@example.com']);

    Test::create([
        'tenant_id' => $tenant->id,
        'professor_id' => $professor->id,
        'turma_id' => $turma->id,
        'disciplina_id' => $disciplina->id,
        'titulo' => 'Prova do 4º bimestre',
        'data_prova' => now()->addWeek(),
        'bimestre' => 4,
    ]);

    $response = $this->actingAs($user)->patch(route('tenants.update', $tenant), [
        'nome' => $tenant->nome,
        'email' => 'bloqueio-prova@example.com',
        'divisao_letiva' => 'trimestral',
    ]);

    $response->assertSessionHasErrors('divisao_letiva');

    expect($tenant->fresh()->divisaoLetiva())->toBe(DivisaoLetiva::Bimestral);
});
