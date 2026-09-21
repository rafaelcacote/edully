<?php

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

test('mobile forgot password envia e-mail de reset', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'responsavel@example.com',
        'password_hash' => Hash::make('senha-antiga'),
        'ativo' => true,
    ]);

    $response = $this->postJson('/api/mobile/forgot-password', [
        'email' => $user->email,
    ]);

    $response->assertOk()
        ->assertJsonStructure(['message']);

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

test('mobile forgot password com e-mail inexistente nao revela cadastro', function () {
    Notification::fake();

    $response = $this->postJson('/api/mobile/forgot-password', [
        'email' => 'naoexiste@example.com',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['message']);

    Notification::assertNothingSent();
});

test('mobile reset password redefine a senha com token valido', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'professor@example.com',
        'password_hash' => Hash::make('senha-antiga'),
        'ativo' => true,
    ]);

    $this->postJson('/api/mobile/forgot-password', [
        'email' => $user->email,
    ])->assertOk();

    Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
        $response = $this->postJson('/api/mobile/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'NovaSenha123!',
            'password_confirmation' => 'NovaSenha123!',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message']);

        $user->refresh();
        expect(Hash::check('NovaSenha123!', $user->password_hash))->toBeTrue();

        return true;
    });
});

test('mobile reset password rejeita token invalido', function () {
    $user = User::factory()->create([
        'email' => 'aluno@example.com',
        'password_hash' => Hash::make('senha-antiga'),
        'ativo' => true,
    ]);

    $response = $this->postJson('/api/mobile/reset-password', [
        'token' => 'token-invalido',
        'email' => $user->email,
        'password' => 'NovaSenha123!',
        'password_confirmation' => 'NovaSenha123!',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});
