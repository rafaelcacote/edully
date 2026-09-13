<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Disciplina;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Spatie\Permission\Models\Role;

function disableTeacherPhotoAuthMiddleware(): void
{
    test()->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);
}

/**
 * @return array{tenant: Tenant, authUser: User, disciplina: Disciplina}
 */
function setupTeacherPhotoContext(): array
{
    Role::findOrCreate('Professor', 'web');

    $tenant = Tenant::factory()->create();
    $authUser = User::factory()->create();
    $authUser->tenants()->attach($tenant->id);

    $disciplina = Disciplina::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Matemática',
        'sigla' => 'MAT',
        'ativo' => true,
    ]);

    return compact('tenant', 'authUser', 'disciplina');
}

it('exposes teacher foto_url on show from usuario avatar_url', function () {
    disableTeacherPhotoAuthMiddleware();

    ['tenant' => $tenant, 'authUser' => $authUser] = setupTeacherPhotoContext();

    $fotoUrl = asset('storage/teachers/photos/ana.jpg');

    $usuario = User::factory()->create([
        'nome_completo' => 'Ana Professora',
        'avatar_url' => $fotoUrl,
    ]);
    $usuario->tenants()->attach($tenant->id);

    $teacher = Teacher::factory()->create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $usuario->id,
        'matricula' => 'PROF2026001',
        'ativo' => true,
    ]);

    $showResponse = $this->actingAs($authUser)->get("/school/teachers/{$teacher->id}");

    $showResponse->assertSuccessful();
    $showResponse->assertInertia(fn ($page) => $page
        ->component('school/teachers/Show')
        ->where('teacher.foto_url', $fotoUrl)
        ->where('teacher.nome_completo', 'Ana Professora')
    );
});

it('updates teacher photo via method spoofing', function () {
    disableTeacherPhotoAuthMiddleware();
    Storage::fake('public');

    ['tenant' => $tenant, 'authUser' => $authUser] = setupTeacherPhotoContext();

    $usuario = User::factory()->create([
        'nome_completo' => 'Bruno Professor',
        'cpf' => '98765432100',
        'avatar_url' => null,
    ]);
    $usuario->tenants()->attach($tenant->id);

    $teacher = Teacher::factory()->create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $usuario->id,
        'matricula' => 'PROF2026002',
        'ativo' => true,
    ]);

    $foto = UploadedFile::fake()->image('nova-foto.png', 180, 180);

    $response = $this->actingAs($authUser)->post("/school/teachers/{$teacher->id}", [
        '_method' => 'patch',
        'nome_completo' => 'Bruno Professor',
        'cpf' => '98765432100',
        'matricula' => 'PROF2026002',
        'disciplinas' => json_encode([]),
        'ativo' => '1',
        'foto' => $foto,
    ]);

    $response->assertRedirect(route('school.teachers.edit', $teacher, absolute: false));
    $response->assertSessionDoesntHaveErrors();

    $usuario->refresh();
    expect($usuario->avatar_url)->not->toBeNull();
    expect($usuario->avatar_url)->toContain('storage/teachers/photos');

    $filePath = str_replace(asset('storage/'), '', $usuario->avatar_url);
    Storage::disk('public')->assertExists($filePath);
});

it('returns teacher photo on mobile login and me', function () {
    $tenant = Tenant::factory()->create();
    $fotoUrl = asset('storage/teachers/photos/prof-mobile.jpg');

    $user = User::factory()->create([
        'cpf' => '11122233344',
        'password_hash' => bcrypt('password'),
        'ativo' => true,
        'avatar_url' => $fotoUrl,
    ]);

    Teacher::factory()->create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'ativo' => true,
    ]);

    $loginResponse = $this->postJson('/api/mobile/login', [
        'cpf' => '11122233344',
        'password' => 'password',
    ]);

    $loginResponse->assertSuccessful()
        ->assertJsonPath('user.avatar_url', $fotoUrl)
        ->assertJsonPath('user.foto_url', $fotoUrl)
        ->assertJsonPath('user.type', 'teacher');

    $token = $loginResponse->json('token');

    $meResponse = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/mobile/me');

    $meResponse->assertSuccessful()
        ->assertJsonPath('user.avatar_url', $fotoUrl)
        ->assertJsonPath('user.foto_url', $fotoUrl);
});

it('allows teacher to update photo via mobile api', function () {
    Storage::fake('public');

    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'cpf' => '55566677788',
        'password_hash' => bcrypt('password'),
        'ativo' => true,
        'avatar_url' => null,
    ]);

    Teacher::factory()->create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'ativo' => true,
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;
    $foto = UploadedFile::fake()->image('perfil.jpg', 200, 200);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->post('/api/mobile/me/foto', [
            'foto' => $foto,
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('user.type', 'teacher');

    $user->refresh();
    expect($user->avatar_url)->not->toBeNull();
    expect($user->avatar_url)->toContain('storage/teachers/photos');
    expect($response->json('user.avatar_url'))->toBe($user->avatar_url);
    expect($response->json('user.foto_url'))->toBe($user->avatar_url);

    $filePath = str_replace(asset('storage/'), '', $user->avatar_url);
    Storage::disk('public')->assertExists($filePath);
});

it('denies responsavel from updating photo via mobile api', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'cpf' => '99988877766',
        'password_hash' => bcrypt('password'),
        'ativo' => true,
    ]);

    \App\Models\Responsavel::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'cpf' => $user->cpf,
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;
    $foto = UploadedFile::fake()->image('perfil.jpg', 200, 200);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->post('/api/mobile/me/foto', [
            'foto' => $foto,
        ]);

    $response->assertForbidden();
});
