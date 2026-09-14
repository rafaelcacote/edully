<?php

use App\Models\User;
use Database\Seeders\PermissionsAndRolesSeeder;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertStatus(200);
});

test('administrador geral dashboard loads saas planos and assinaturas stats', function () {
    $this->seed(PermissionsAndRolesSeeder::class);

    $user = User::factory()->create(['ativo' => true]);
    $user->assignRole('Administrador Geral');

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->where('dashboardType', 'admin_geral')
        ->has('stats.planos')
        ->has('stats.assinaturas')
    );
});
