<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Aviso;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

it('creates an aviso on store', function () {
    $this->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);

    $tenant = Tenant::factory()->create();
    $authUser = User::factory()->create();
    $authUser->tenants()->attach($tenant->id);

    $payload = [
        'titulo' => 'Aviso importante',
        'conteudo' => 'Conteúdo do aviso',
        'prioridade' => 'alta',
        'publico_alvo' => 'todos',
        'publicado' => true,
    ];

    $response = $this->actingAs($authUser)->post('/school/avisos', $payload);

    $response->assertRedirect(route('school.avisos.index', absolute: false));

    $this->assertDatabaseHas('avisos', [
        'tenant_id' => $tenant->id,
        'titulo' => 'Aviso importante',
        'conteudo' => 'Conteúdo do aviso',
        'prioridade' => 'alta',
        'publico_alvo' => 'todos',
        'publicado' => true,
    ], 'shared');
});

it('creates an aviso with pdf attachment', function () {
    $this->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);

    $tenant = Tenant::factory()->create();
    $authUser = User::factory()->create();
    $authUser->tenants()->attach($tenant->id);

    $pdf = UploadedFile::fake()->create('documento.pdf', 1024, 'application/pdf');

    $payload = [
        'titulo' => 'Aviso com anexo',
        'conteudo' => 'Conteúdo do aviso com anexo PDF',
        'prioridade' => 'normal',
        'publico_alvo' => 'alunos',
        'anexo' => $pdf,
        'publicado' => false,
    ];

    $response = $this->actingAs($authUser)->post('/school/avisos', $payload);

    $response->assertRedirect(route('school.avisos.index', absolute: false));

    // Verificar que o aviso foi criado
    $aviso = Aviso::where('titulo', 'Aviso com anexo')->first();

    expect($aviso)->not->toBeNull();
    expect($aviso->anexo_url)->not->toBeNull();
    expect($aviso->anexo_url)->toContain('storage/avisos/anexos');

    // Verificar que o arquivo foi salvo
    $filePath = str_replace(asset('storage/'), '', $aviso->anexo_url);
    Storage::disk('public')->assertExists($filePath);
});

it('validates pdf file type on store', function () {
    $this->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);

    $tenant = Tenant::factory()->create();
    $authUser = User::factory()->create();
    $authUser->tenants()->attach($tenant->id);

    $invalidFile = UploadedFile::fake()->create('document.txt', 100, 'text/plain');

    $payload = [
        'titulo' => 'Aviso teste',
        'conteudo' => 'Conteúdo teste',
        'anexo' => $invalidFile,
    ];

    $response = $this->actingAs($authUser)->post('/school/avisos', $payload);

    $response->assertSessionHasErrors(['anexo']);
});

it('validates pdf file size on store', function () {
    $this->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);

    $tenant = Tenant::factory()->create();
    $authUser = User::factory()->create();
    $authUser->tenants()->attach($tenant->id);

    // Arquivo maior que 10MB (10240 KB)
    $largeFile = UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf');

    $payload = [
        'titulo' => 'Aviso teste',
        'conteudo' => 'Conteúdo teste',
        'anexo' => $largeFile,
    ];

    $response = $this->actingAs($authUser)->post('/school/avisos', $payload);

    $response->assertSessionHasErrors(['anexo']);
});

it('updates an aviso with new pdf attachment', function () {
    $this->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);

    $tenant = Tenant::factory()->create();
    $authUser = User::factory()->create();
    $authUser->tenants()->attach($tenant->id);

    // Criar aviso inicial sem anexo
    $aviso = Aviso::create([
        'tenant_id' => $tenant->id,
        'criado_por' => $authUser->id,
        'titulo' => 'Aviso para atualizar',
        'conteudo' => 'Conteúdo inicial',
        'prioridade' => 'normal',
        'publico_alvo' => 'todos',
        'publicado' => false,
    ]);

    $pdf = UploadedFile::fake()->create('novo-documento.pdf', 512, 'application/pdf');

    $payload = [
        'titulo' => 'Aviso atualizado',
        'conteudo' => 'Conteúdo atualizado',
        'prioridade' => 'alta',
        'publico_alvo' => 'professores',
        'anexo' => $pdf,
        'publicado' => true,
    ];

    $response = $this->actingAs($authUser)->patch("/school/avisos/{$aviso->id}", $payload);

    $response->assertRedirect(route('school.avisos.edit', $aviso, absolute: false));

    $aviso->refresh();

    expect($aviso->titulo)->toBe('Aviso atualizado');
    expect($aviso->anexo_url)->not->toBeNull();
    expect($aviso->anexo_url)->toContain('storage/avisos/anexos');

    // Verificar que o novo arquivo foi salvo
    $filePath = str_replace(asset('storage/'), '', $aviso->anexo_url);
    Storage::disk('public')->assertExists($filePath);
});

it('replaces old pdf when updating with new attachment', function () {
    $this->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);

    $tenant = Tenant::factory()->create();
    $authUser = User::factory()->create();
    $authUser->tenants()->attach($tenant->id);

    // Simular arquivo antigo
    $oldPdf = UploadedFile::fake()->create('old.pdf', 256, 'application/pdf');
    $oldPath = $oldPdf->store('avisos/anexos', 'public');
    $oldUrl = asset('storage/'.$oldPath);

    // Criar aviso com anexo antigo
    $aviso = Aviso::create([
        'tenant_id' => $tenant->id,
        'criado_por' => $authUser->id,
        'titulo' => 'Aviso com anexo antigo',
        'conteudo' => 'Conteúdo',
        'prioridade' => 'normal',
        'publico_alvo' => 'todos',
        'anexo_url' => $oldUrl,
        'publicado' => false,
    ]);

    Storage::disk('public')->assertExists($oldPath);

    // Atualizar com novo PDF
    $newPdf = UploadedFile::fake()->create('new.pdf', 512, 'application/pdf');

    $payload = [
        'titulo' => 'Aviso com anexo novo',
        'conteudo' => 'Conteúdo atualizado',
        'prioridade' => 'normal',
        'publico_alvo' => 'todos',
        'anexo' => $newPdf,
        'publicado' => false,
    ];

    $response = $this->actingAs($authUser)->patch("/school/avisos/{$aviso->id}", $payload);

    $response->assertRedirect(route('school.avisos.edit', $aviso, absolute: false));

    $aviso->refresh();

    expect($aviso->anexo_url)->not->toBe($oldUrl);

    // Verificar que o arquivo antigo foi deletado
    Storage::disk('public')->assertMissing($oldPath);

    // Verificar que o novo arquivo existe
    $newPath = str_replace(asset('storage/'), '', $aviso->anexo_url);
    Storage::disk('public')->assertExists($newPath);
});

it('deletes an aviso', function () {
    $this->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);

    $tenant = Tenant::factory()->create();
    $authUser = User::factory()->create();
    $authUser->tenants()->attach($tenant->id);

    $aviso = Aviso::create([
        'tenant_id' => $tenant->id,
        'criado_por' => $authUser->id,
        'titulo' => 'Aviso para deletar',
        'conteudo' => 'Conteúdo',
        'prioridade' => 'normal',
        'publico_alvo' => 'todos',
        'publicado' => false,
    ]);

    $response = $this->actingAs($authUser)->delete("/school/avisos/{$aviso->id}");

    $response->assertRedirect(route('school.avisos.index', absolute: false));

    $this->assertSoftDeleted('avisos', [
        'id' => $aviso->id,
    ], 'shared');
});
