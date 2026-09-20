<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Aviso;
use App\Models\PushToken;
use App\Models\Responsavel;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

it('sends expo push to teachers when school publishes aviso for professores', function () {
    $this->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);

    Http::fake([
        'https://exp.host/--/api/v2/push/send' => Http::response(['data' => [['status' => 'ok']]], 200),
    ]);

    $tenant = Tenant::factory()->create(['nome' => 'Escola Push Aviso']);
    $admin = User::factory()->create(['ativo' => true]);
    $admin->tenants()->attach($tenant->id);

    $teacherUser = User::factory()->create(['ativo' => true, 'nome_completo' => 'Prof Destino']);
    Teacher::factory()->create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $teacherUser->id,
        'ativo' => true,
    ]);

    $parentUser = User::factory()->create(['ativo' => true, 'nome_completo' => 'Pai Não Deve Receber']);
    Responsavel::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $parentUser->id,
        'cpf' => $parentUser->cpf,
    ]);

    PushToken::create([
        'usuario_id' => $teacherUser->id,
        'push_token' => 'ExponentPushToken[teacher-aviso]',
        'platform' => 'android',
        'last_used_at' => now(),
    ]);

    PushToken::create([
        'usuario_id' => $parentUser->id,
        'push_token' => 'ExponentPushToken[parent-aviso]',
        'platform' => 'android',
        'last_used_at' => now(),
    ]);

    $response = $this->actingAs($admin)->post('/school/avisos', [
        'titulo' => 'Reunião pedagógica',
        'conteudo' => 'Conteúdo só para professores',
        'prioridade' => 'normal',
        'publico_alvo' => 'professores',
        'publicado' => true,
    ]);

    $response->assertRedirect(route('school.avisos.index', absolute: false));

    $aviso = Aviso::where('titulo', 'Reunião pedagógica')->first();
    expect($aviso)->not->toBeNull();

    Http::assertSent(function ($request) {
        if ($request->url() !== 'https://exp.host/--/api/v2/push/send') {
            return false;
        }

        $payload = $request->data();
        $messages = is_array($payload[0] ?? null) ? $payload : [$payload];

        $tokens = collect($messages)->pluck('to')->all();

        return in_array('ExponentPushToken[teacher-aviso]', $tokens, true)
            && ! in_array('ExponentPushToken[parent-aviso]', $tokens, true)
            && collect($messages)->contains(fn ($msg) => ($msg['data']['type'] ?? null) === 'aviso');
    });
});

it('sends expo push to responsaveis when school publishes aviso for responsaveis', function () {
    $this->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);

    Http::fake([
        'https://exp.host/--/api/v2/push/send' => Http::response(['data' => [['status' => 'ok']]], 200),
    ]);

    $tenant = Tenant::factory()->create(['nome' => 'Escola Push Pais']);
    $admin = User::factory()->create(['ativo' => true]);
    $admin->tenants()->attach($tenant->id);

    $teacherUser = User::factory()->create(['ativo' => true]);
    Teacher::factory()->create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $teacherUser->id,
        'ativo' => true,
    ]);

    $parentUser = User::factory()->create(['ativo' => true]);
    Responsavel::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $parentUser->id,
        'cpf' => $parentUser->cpf,
    ]);

    PushToken::create([
        'usuario_id' => $teacherUser->id,
        'push_token' => 'ExponentPushToken[teacher-skip]',
        'platform' => 'android',
        'last_used_at' => now(),
    ]);

    PushToken::create([
        'usuario_id' => $parentUser->id,
        'push_token' => 'ExponentPushToken[parent-only]',
        'platform' => 'android',
        'last_used_at' => now(),
    ]);

    $this->actingAs($admin)->post('/school/avisos', [
        'titulo' => 'Recesso escolar',
        'conteudo' => 'Avisamos os responsáveis',
        'prioridade' => 'alta',
        'publico_alvo' => 'responsaveis',
        'publicado' => true,
    ])->assertRedirect(route('school.avisos.index', absolute: false));

    Http::assertSent(function ($request) {
        if ($request->url() !== 'https://exp.host/--/api/v2/push/send') {
            return false;
        }

        $payload = $request->data();
        $messages = is_array($payload[0] ?? null) ? $payload : [$payload];
        $tokens = collect($messages)->pluck('to')->all();

        return in_array('ExponentPushToken[parent-only]', $tokens, true)
            && ! in_array('ExponentPushToken[teacher-skip]', $tokens, true);
    });
});

it('does not send push when aviso is saved as draft', function () {
    $this->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);

    Http::fake();

    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['ativo' => true]);
    $admin->tenants()->attach($tenant->id);

    $teacherUser = User::factory()->create(['ativo' => true]);
    Teacher::factory()->create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $teacherUser->id,
        'ativo' => true,
    ]);

    PushToken::create([
        'usuario_id' => $teacherUser->id,
        'push_token' => 'ExponentPushToken[draft-teacher]',
        'platform' => 'android',
        'last_used_at' => now(),
    ]);

    $this->actingAs($admin)->post('/school/avisos', [
        'titulo' => 'Rascunho',
        'conteudo' => 'Ainda não publicar',
        'prioridade' => 'normal',
        'publico_alvo' => 'professores',
        'publicado' => false,
    ])->assertRedirect(route('school.avisos.index', absolute: false));

    Http::assertNothingSent();
});
