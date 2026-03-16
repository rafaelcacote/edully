<?php

use App\Models\Aviso;
use App\Models\Responsavel;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('responsavel can list published avisos from their students tenant', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $responsavel = Responsavel::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'cpf' => $user->cpf,
    ]);

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'João Silva',
        'ativo' => true,
    ]);

    $driver = DB::connection('shared')->getDriverName();
    $pivotTable = $driver === 'sqlite' ? 'aluno_responsavel' : 'escola.aluno_responsavel';
    DB::connection('shared')->table($pivotTable)->insert([
        'id' => \Illuminate\Support\Str::uuid(),
        'aluno_id' => $student->id,
        'responsavel_id' => $responsavel->id,
        'tenant_id' => $tenant->id,
        'principal' => true,
    ]);

    $aviso = Aviso::create([
        'tenant_id' => $tenant->id,
        'titulo' => 'Aviso importante',
        'conteudo' => 'Conteúdo do aviso para todos',
        'prioridade' => 'alta',
        'publico_alvo' => 'todos',
        'publicado' => true,
        'publicado_em' => now(),
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/mobile/avisos');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'avisos' => [
                '*' => [
                    'id',
                    'titulo',
                    'conteudo',
                    'prioridade',
                    'publico_alvo',
                    'anexo_url',
                    'publicado',
                    'publicado_em',
                    'expira_em',
                    'created_at',
                    'updated_at',
                    'tenant',
                ],
            ],
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
            ],
        ]);

    expect($response->json('avisos'))->toHaveCount(1);
    expect($response->json('avisos.0.id'))->toBe($aviso->id);
    expect($response->json('avisos.0.titulo'))->toBe('Aviso importante');
});

it('responsavel does not see unpublished or expired avisos', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $responsavel = Responsavel::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'cpf' => $user->cpf,
    ]);

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Maria',
        'ativo' => true,
    ]);

    $driver = DB::connection('shared')->getDriverName();
    $pivotTable = $driver === 'sqlite' ? 'aluno_responsavel' : 'escola.aluno_responsavel';
    DB::connection('shared')->table($pivotTable)->insert([
        'id' => \Illuminate\Support\Str::uuid(),
        'aluno_id' => $student->id,
        'responsavel_id' => $responsavel->id,
        'tenant_id' => $tenant->id,
        'principal' => true,
    ]);

    Aviso::create([
        'tenant_id' => $tenant->id,
        'titulo' => 'Não publicado',
        'conteudo' => 'Não deve aparecer',
        'prioridade' => 'normal',
        'publico_alvo' => 'todos',
        'publicado' => false,
    ]);

    Aviso::create([
        'tenant_id' => $tenant->id,
        'titulo' => 'Expirado',
        'conteudo' => 'Já expirou',
        'prioridade' => 'normal',
        'publico_alvo' => 'todos',
        'publicado' => true,
        'publicado_em' => now()->subDays(2),
        'expira_em' => now()->subDay(),
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/mobile/avisos');

    $response->assertSuccessful();
    expect($response->json('avisos'))->toHaveCount(0);
});

it('responsavel can show a single aviso', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $responsavel = Responsavel::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'cpf' => $user->cpf,
    ]);

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'João',
        'ativo' => true,
    ]);

    $driver = DB::connection('shared')->getDriverName();
    $pivotTable = $driver === 'sqlite' ? 'aluno_responsavel' : 'escola.aluno_responsavel';
    DB::connection('shared')->table($pivotTable)->insert([
        'id' => \Illuminate\Support\Str::uuid(),
        'aluno_id' => $student->id,
        'responsavel_id' => $responsavel->id,
        'tenant_id' => $tenant->id,
        'principal' => true,
    ]);

    $aviso = Aviso::create([
        'tenant_id' => $tenant->id,
        'titulo' => 'Aviso único',
        'conteudo' => 'Conteúdo completo',
        'prioridade' => 'normal',
        'publico_alvo' => 'todos',
        'publicado' => true,
        'publicado_em' => now(),
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/mobile/avisos/{$aviso->id}");

    $response->assertSuccessful()
        ->assertJsonPath('aviso.id', $aviso->id)
        ->assertJsonPath('aviso.titulo', 'Aviso único')
        ->assertJsonPath('aviso.conteudo', 'Conteúdo completo');
});

it('teacher can list published avisos from their tenant', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $teacher = Teacher::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'matricula' => 'PROF'.fake()->unique()->numberBetween(2024000, 2024999),
        'ativo' => true,
    ]);

    $aviso = Aviso::create([
        'tenant_id' => $tenant->id,
        'titulo' => 'Aviso para professores',
        'conteudo' => 'Conteúdo',
        'prioridade' => 'normal',
        'publico_alvo' => 'todos',
        'publicado' => true,
        'publicado_em' => now(),
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/mobile/avisos');

    $response->assertSuccessful();
    expect($response->json('avisos'))->toHaveCount(1);
    expect($response->json('avisos.0.id'))->toBe($aviso->id);
});

it('returns 403 when user is not responsavel or teacher', function () {
    $user = User::factory()->create(['ativo' => true]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/mobile/avisos');

    $response->assertForbidden()
        ->assertJsonPath('message', 'Acesso negado. Apenas responsáveis e professores podem acessar os avisos.');
});

it('show returns 404 for aviso from another tenant', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $responsavel = Responsavel::create([
        'tenant_id' => $tenant1->id,
        'usuario_id' => $user->id,
        'cpf' => $user->cpf,
    ]);

    $student = Student::create([
        'tenant_id' => $tenant1->id,
        'nome' => 'Aluno',
        'ativo' => true,
    ]);

    $driver = DB::connection('shared')->getDriverName();
    $pivotTable = $driver === 'sqlite' ? 'aluno_responsavel' : 'escola.aluno_responsavel';
    DB::connection('shared')->table($pivotTable)->insert([
        'id' => \Illuminate\Support\Str::uuid(),
        'aluno_id' => $student->id,
        'responsavel_id' => $responsavel->id,
        'tenant_id' => $tenant1->id,
        'principal' => true,
    ]);

    $avisoOutroTenant = Aviso::create([
        'tenant_id' => $tenant2->id,
        'titulo' => 'Aviso outra escola',
        'conteudo' => 'Não deve ver',
        'prioridade' => 'normal',
        'publico_alvo' => 'todos',
        'publicado' => true,
        'publicado_em' => now(),
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/mobile/avisos/{$avisoOutroTenant->id}");

    $response->assertNotFound();
});

it('requires authentication', function () {
    $response = $this->getJson('/api/mobile/avisos');
    $response->assertUnauthorized();
});
