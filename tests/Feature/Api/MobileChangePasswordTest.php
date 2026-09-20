<?php

use App\Models\Responsavel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('usuario autenticado pode alterar a senha', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'password_hash' => Hash::make('SenhaAntiga1!'),
        'ativo' => true,
    ]);

    Responsavel::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'cpf' => $user->cpf,
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withToken($token)->putJson('/api/mobile/me/password', [
        'current_password' => 'SenhaAntiga1!',
        'password' => 'SenhaNova123!',
        'password_confirmation' => 'SenhaNova123!',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['message']);

    $user->refresh();
    expect(Hash::check('SenhaNova123!', $user->password_hash))->toBeTrue();
});

test('alterar senha rejeita senha atual incorreta', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'password_hash' => Hash::make('SenhaAntiga1!'),
        'ativo' => true,
    ]);

    Responsavel::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'cpf' => $user->cpf,
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withToken($token)->putJson('/api/mobile/me/password', [
        'current_password' => 'senha-errada',
        'password' => 'SenhaNova123!',
        'password_confirmation' => 'SenhaNova123!',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['current_password']);
});
