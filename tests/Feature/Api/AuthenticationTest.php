<?php

use App\Models\Responsavel;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;

it('teacher can login successfully', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'cpf' => '12345678909',
        'password_hash' => bcrypt('password'),
        'ativo' => true,
    ]);

    Teacher::factory()->create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'ativo' => true,
    ]);

    $response = $this->postJson('/api/mobile/login', [
        'cpf' => '12345678909',
        'password' => 'password',
    ]);

    $response->assertSuccessful()
        ->assertJsonStructure([
            'token',
            'user' => [
                'id',
                'nome_completo',
                'email',
                'cpf',
                'telefone',
                'avatar_url',
                'type',
            ],
        ])
        ->assertJson([
            'user' => [
                'type' => 'teacher',
            ],
        ]);
});

it('responsavel can login successfully', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'cpf' => '98765432100',
        'password_hash' => bcrypt('password'),
        'ativo' => true,
    ]);

    Responsavel::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'cpf' => $user->cpf,
    ]);

    $response = $this->postJson('/api/mobile/login', [
        'cpf' => '98765432100',
        'password' => 'password',
    ]);

    $response->assertSuccessful()
        ->assertJsonStructure([
            'token',
            'user' => [
                'id',
                'nome_completo',
                'email',
                'cpf',
                'telefone',
                'avatar_url',
                'type',
            ],
        ])
        ->assertJson([
            'user' => [
                'type' => 'responsavel',
            ],
        ]);
});

it('cannot login with invalid credentials', function () {
    $user = User::factory()->create([
        'cpf' => '12345678909',
        'password_hash' => bcrypt('password'),
    ]);

    $response = $this->postJson('/api/mobile/login', [
        'cpf' => '12345678909',
        'password' => 'wrong-password',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['cpf']);
});

it('cannot login if user is not teacher or responsavel', function () {
    $user = User::factory()->create([
        'cpf' => '12345678909',
        'password_hash' => bcrypt('password'),
        'ativo' => true,
    ]);

    $response = $this->postJson('/api/mobile/login', [
        'cpf' => '12345678909',
        'password' => 'password',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['cpf']);
});

it('cannot login if user is inactive', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'cpf' => '12345678909',
        'password_hash' => bcrypt('password'),
        'ativo' => false,
    ]);

    Teacher::factory()->create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'ativo' => true,
    ]);

    $response = $this->postJson('/api/mobile/login', [
        'cpf' => '12345678909',
        'password' => 'password',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['cpf']);
});

it('cannot login if teacher is inactive', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'cpf' => '12345678909',
        'password_hash' => bcrypt('password'),
        'ativo' => true,
    ]);

    Teacher::factory()->create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'ativo' => false,
    ]);

    $response = $this->postJson('/api/mobile/login', [
        'cpf' => '12345678909',
        'password' => 'password',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['cpf']);
});

it('validates login request', function () {
    $response = $this->postJson('/api/mobile/login', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['cpf', 'password']);
});

it('authenticated user can get their information', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'cpf' => '12345678909',
        'ativo' => true,
    ]);

    Teacher::factory()->create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'ativo' => true,
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/mobile/me');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'user' => [
                'id',
                'nome_completo',
                'email',
                'cpf',
                'telefone',
                'avatar_url',
                'type',
            ],
        ])
        ->assertJson([
            'user' => [
                'type' => 'teacher',
            ],
        ]);
});

it('authenticated user can logout', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'cpf' => '12345678909',
        'ativo' => true,
    ]);

    Teacher::factory()->create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'ativo' => true,
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/mobile/logout');

    $response->assertSuccessful()
        ->assertJson([
            'message' => 'Logout realizado com sucesso.',
        ]);

    // Verificar se o token foi deletado
    expect($user->tokens()->count())->toBe(0);
});

it('requires authentication for protected routes', function () {
    $response = $this->getJson('/api/mobile/me');

    $response->assertUnauthorized();
});
