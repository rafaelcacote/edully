<?php

use App\Http\Middleware\EnsureUserIsAdminGeral;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

function resolvePublicStoragePath(string $logoUrl): ?string
{
    $storageBaseUrl = Storage::disk('public')->url('');

    if (str_starts_with($logoUrl, $storageBaseUrl)) {
        return ltrim(substr($logoUrl, strlen($storageBaseUrl)), '/');
    }

    if (str_starts_with($logoUrl, '/storage/')) {
        return substr($logoUrl, strlen('/storage/'));
    }

    if (str_starts_with($logoUrl, 'storage/')) {
        return substr($logoUrl, strlen('storage/'));
    }

    return null;
}

beforeEach(function () {
    $this->withoutMiddleware([
        EnsureUserIsAdminGeral::class,
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);

    Storage::fake('public');
});

it('stores tenant logo and normalizes logo url', function () {
    $user = User::factory()->create();

    $payload = [
        'nome' => 'Escola Teste',
        'email' => 'escola@example.com',
        'logo' => UploadedFile::fake()->image('logo.jpg'),
    ];

    $response = $this->actingAs($user)->post('/admin/tenants', $payload);

    $response->assertRedirect(route('tenants.index', absolute: false));

    $tenant = Tenant::query()->where('email', 'escola@example.com')->firstOrFail();
    $storedLogoUrl = $tenant->getRawOriginal('logo_url');
    $storedPath = resolvePublicStoragePath($storedLogoUrl);

    expect($tenant->logo_url)->toStartWith('/storage/tenants/logos/')
        ->and($storedPath)->not->toBeNull();

    Storage::disk('public')->assertExists($storedPath);
});

it('updates tenant logo and removes old file', function () {
    $user = User::factory()->create();

    Storage::disk('public')->put('tenants/logos/old-logo.jpg', 'old');

    $tenant = Tenant::factory()->create([
        'nome' => 'Escola Modelo',
        'email' => 'modelo@example.com',
        'logo_url' => Storage::disk('public')->url('tenants/logos/old-logo.jpg'),
    ]);

    $payload = [
        'nome' => 'Escola Modelo',
        'email' => 'modelo@example.com',
        'logo' => UploadedFile::fake()->image('new-logo.jpg'),
    ];

    $response = $this->actingAs($user)->patch("/admin/tenants/{$tenant->id}", $payload);

    $response->assertRedirect(route('tenants.edit', $tenant, absolute: false));

    $tenant->refresh();

    $storedLogoUrl = $tenant->getRawOriginal('logo_url');
    $storedPath = resolvePublicStoragePath($storedLogoUrl);

    expect($tenant->logo_url)->toStartWith('/storage/tenants/logos/')
        ->and($storedPath)->not->toBeNull();

    Storage::disk('public')->assertExists($storedPath);
    Storage::disk('public')->assertMissing('tenants/logos/old-logo.jpg');
});
