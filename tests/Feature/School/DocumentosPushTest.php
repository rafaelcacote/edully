<?php

use App\Enums\StatusDocumento;
use App\Enums\TipoDocumento;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Documento;
use App\Models\PushToken;
use App\Models\Responsavel;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

beforeEach(function () {
    Storage::fake('public');

    $this->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);

    Http::fake([
        'https://exp.host/--/api/v2/push/send' => Http::response(['data' => [['status' => 'ok']]], 200),
    ]);
});

it('sends expo push when school sends documento_escola', function () {
    $tenant = Tenant::factory()->create(['nome' => 'Escola Docs']);
    $admin = User::factory()->create(['ativo' => true]);
    $admin->tenants()->attach($tenant->id);

    $parentUser = User::factory()->create(['ativo' => true, 'nome_completo' => 'Pai Destino']);
    $responsavel = Responsavel::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $parentUser->id,
        'cpf' => $parentUser->cpf,
    ]);

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Filho',
        'ativo' => true,
    ]);

    $driver = DB::connection('shared')->getDriverName();
    $pivotTable = $driver === 'sqlite' ? 'aluno_responsavel' : 'escola.aluno_responsavel';
    DB::connection('shared')->table($pivotTable)->insert([
        'id' => Str::uuid(),
        'aluno_id' => $student->id,
        'responsavel_id' => $responsavel->id,
        'tenant_id' => $tenant->id,
        'principal' => true,
    ]);

    PushToken::create([
        'usuario_id' => $parentUser->id,
        'push_token' => 'ExponentPushToken[parent-doc]',
        'platform' => 'android',
        'last_used_at' => now(),
    ]);

    $response = $this->actingAs($admin)->post('/school/documentos', [
        'aluno_id' => $student->id,
        'titulo' => 'Boletim anual',
        'anexo' => UploadedFile::fake()->create('boletim.pdf', 100, 'application/pdf'),
    ]);

    $response->assertRedirect(route('school.documentos.index', absolute: false));

    Http::assertSent(function ($request) {
        if ($request->url() !== 'https://exp.host/--/api/v2/push/send') {
            return false;
        }

        $payload = $request->data();
        $messages = is_array($payload[0] ?? null) ? $payload : [$payload];

        return collect($messages)->contains(function ($msg) {
            return ($msg['to'] ?? null) === 'ExponentPushToken[parent-doc]'
                && ($msg['data']['type'] ?? null) === 'documento';
        });
    });
});

it('sends expo push when school updates documento status', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['ativo' => true]);
    $admin->tenants()->attach($tenant->id);

    $parentUser = User::factory()->create(['ativo' => true]);
    $responsavel = Responsavel::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $parentUser->id,
        'cpf' => $parentUser->cpf,
    ]);

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Filho',
        'ativo' => true,
    ]);

    $driver = DB::connection('shared')->getDriverName();
    $pivotTable = $driver === 'sqlite' ? 'aluno_responsavel' : 'escola.aluno_responsavel';
    DB::connection('shared')->table($pivotTable)->insert([
        'id' => Str::uuid(),
        'aluno_id' => $student->id,
        'responsavel_id' => $responsavel->id,
        'tenant_id' => $tenant->id,
        'principal' => true,
    ]);

    PushToken::create([
        'usuario_id' => $parentUser->id,
        'push_token' => 'ExponentPushToken[parent-status]',
        'platform' => 'ios',
        'last_used_at' => now(),
    ]);

    $documento = Documento::create([
        'tenant_id' => $tenant->id,
        'aluno_id' => $student->id,
        'criado_por' => $parentUser->id,
        'tipo' => TipoDocumento::Atestado,
        'status' => StatusDocumento::Enviado,
        'titulo' => 'Atestado médico',
        'data_inicio' => '2026-09-10',
        'data_fim' => '2026-09-12',
        'anexo_url' => 'https://example.com/a.pdf',
    ]);

    $this->actingAs($admin)->post('/school/documentos/'.$documento->id.'/status', [
        'status' => 'aprovado',
    ])->assertRedirect();

    Http::assertSent(function ($request) {
        if ($request->url() !== 'https://exp.host/--/api/v2/push/send') {
            return false;
        }

        $payload = $request->data();
        $messages = is_array($payload[0] ?? null) ? $payload : [$payload];

        return collect($messages)->contains(function ($msg) {
            return ($msg['to'] ?? null) === 'ExponentPushToken[parent-status]'
                && ($msg['data']['type'] ?? null) === 'documento'
                && ($msg['data']['status'] ?? null) === 'aprovado';
        });
    });
});
