<?php

use App\Models\Disciplina;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionsAndRolesSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

test('professor dashboard includes linked disciplinas and school name', function () {
    $this->seed(PermissionsAndRolesSeeder::class);

    $tenant = Tenant::factory()->create(['nome' => 'Escola Horizonte']);
    $user = User::factory()->create([
        'nome_completo' => 'Ana Professora',
        'ativo' => true,
    ]);
    $user->tenants()->attach($tenant->id);
    $user->assignRole('Professor');

    $teacher = Teacher::factory()->create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'ativo' => true,
    ]);

    $matematica = Disciplina::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Matemática',
        'sigla' => 'MAT',
        'ativo' => true,
    ]);
    $portugues = Disciplina::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Português',
        'sigla' => 'PORT',
        'ativo' => true,
    ]);

    $pivotTable = DB::connection('shared')->getDriverName() === 'sqlite'
        ? 'professor_disciplinas'
        : 'escola.professor_disciplinas';

    DB::connection('shared')->table($pivotTable)->insert([
        [
            'id' => Str::uuid()->toString(),
            'tenant_id' => $tenant->id,
            'professor_id' => $teacher->id,
            'disciplina_id' => $matematica->id,
            'created_at' => now(),
        ],
        [
            'id' => Str::uuid()->toString(),
            'tenant_id' => $tenant->id,
            'professor_id' => $teacher->id,
            'disciplina_id' => $portugues->id,
            'created_at' => now(),
        ],
    ]);

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->where('dashboardType', 'professor')
        ->where('tenant.nome', 'Escola Horizonte')
        ->has('disciplinas', 2)
        ->where('disciplinas.0.nome', 'Matemática')
        ->where('disciplinas.0.sigla', 'MAT')
        ->where('disciplinas.1.nome', 'Português')
        ->where('disciplinas.1.sigla', 'PORT')
    );
});
