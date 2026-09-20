<?php

use App\Enums\StatusDocumento;
use App\Enums\TipoDocumento;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Documento;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
});

it('lists documentos for the school tenant', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['ativo' => true]);
    $admin->tenants()->attach($tenant->id);

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Maria',
        'ativo' => true,
    ]);

    Documento::create([
        'tenant_id' => $tenant->id,
        'aluno_id' => $student->id,
        'criado_por' => $admin->id,
        'tipo' => TipoDocumento::Atestado,
        'status' => StatusDocumento::Enviado,
        'titulo' => 'Atestado médico',
        'data_inicio' => '2026-09-10',
        'data_fim' => '2026-09-12',
    ]);

    $response = $this->actingAs($admin)->get('/school/documentos');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('school/documentos/Index')
        ->has('documentos.data', 1)
        ->where('documentos.data.0.titulo', 'Atestado médico')
        ->where('documentos.data.0.precisa_atencao', true)
    );
});

it('sends documento_escola to a student', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['ativo' => true]);
    $admin->tenants()->attach($tenant->id);

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'João',
        'ativo' => true,
    ]);

    $response = $this->actingAs($admin)->post('/school/documentos', [
        'aluno_id' => $student->id,
        'titulo' => 'Declaração de matrícula',
        'descricao' => 'Documento emitido pela secretaria',
        'categoria_declaracao' => 'matricula',
        'anexo' => UploadedFile::fake()->create('declaracao.pdf', 200, 'application/pdf'),
    ]);

    $response->assertRedirect(route('school.documentos.index', absolute: false));

    $documento = Documento::query()->first();
    expect($documento)->not->toBeNull();
    expect($documento->tipo)->toBe(TipoDocumento::DocumentoEscola);
    expect($documento->status)->toBe(StatusDocumento::Disponivel);
    expect($documento->anexo_url)->not->toBeNull();
});

it('updates atestado status and stores motivo_recusa', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['ativo' => true]);
    $admin->tenants()->attach($tenant->id);

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Ana',
        'ativo' => true,
    ]);

    $documento = Documento::create([
        'tenant_id' => $tenant->id,
        'aluno_id' => $student->id,
        'criado_por' => $admin->id,
        'tipo' => TipoDocumento::Atestado,
        'status' => StatusDocumento::Enviado,
        'titulo' => 'Atestado médico',
        'data_inicio' => '2026-09-10',
        'data_fim' => '2026-09-12',
        'anexo_url' => 'https://example.com/a.pdf',
    ]);

    $response = $this->actingAs($admin)->post('/school/documentos/'.$documento->id.'/status', [
        'status' => 'recusado',
        'motivo_recusa' => 'Documento ilegível',
    ]);

    $response->assertRedirect(route('school.documentos.show', $documento, absolute: false));

    $documento->refresh();
    expect($documento->status)->toBe(StatusDocumento::Recusado);
    expect($documento->motivo_recusa)->toBe('Documento ilegível');
});

it('rejects status update without status value', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['ativo' => true]);
    $admin->tenants()->attach($tenant->id);

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Ana',
        'ativo' => true,
    ]);

    $documento = Documento::create([
        'tenant_id' => $tenant->id,
        'aluno_id' => $student->id,
        'criado_por' => $admin->id,
        'tipo' => TipoDocumento::PedidoDeclaracao,
        'status' => StatusDocumento::Enviado,
        'titulo' => 'Pedido de declaração',
        'categoria_declaracao' => 'matricula',
    ]);

    $response = $this->actingAs($admin)->from(route('school.documentos.show', $documento, absolute: false))
        ->post('/school/documentos/'.$documento->id.'/status', []);

    $response->assertRedirect(route('school.documentos.show', $documento, absolute: false));
    $response->assertSessionHasErrors(['status' => 'Informe o novo status.']);
});

it('show page offers only school-updatable status options', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['ativo' => true]);
    $admin->tenants()->attach($tenant->id);

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Ana',
        'ativo' => true,
    ]);

    $documento = Documento::create([
        'tenant_id' => $tenant->id,
        'aluno_id' => $student->id,
        'criado_por' => $admin->id,
        'tipo' => TipoDocumento::PedidoDeclaracao,
        'status' => StatusDocumento::Enviado,
        'titulo' => 'Pedido de declaração',
        'categoria_declaracao' => 'matricula',
    ]);

    $response = $this->actingAs($admin)->get('/school/documentos/'.$documento->id);

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('school/documentos/Show')
        ->where('documento.status', 'enviado')
        ->has('statusOptions', 5)
        ->where('statusOptions.0.value', 'em_analise')
        ->where('statusOptions', fn ($options) => collect($options)->pluck('value')->doesntContain('enviado'))
    );
});

it('attaches resposta when marking pedido as atendido', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['ativo' => true]);
    $admin->tenants()->attach($tenant->id);

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Pedro',
        'ativo' => true,
    ]);

    $documento = Documento::create([
        'tenant_id' => $tenant->id,
        'aluno_id' => $student->id,
        'criado_por' => $admin->id,
        'tipo' => TipoDocumento::PedidoDeclaracao,
        'status' => StatusDocumento::EmAnalise,
        'titulo' => 'Declaração de frequência',
        'categoria_declaracao' => 'frequencia',
    ]);

    $response = $this->actingAs($admin)->post('/school/documentos/'.$documento->id.'/status', [
        'status' => 'atendido',
        'anexo_resposta' => UploadedFile::fake()->create('resposta.pdf', 300, 'application/pdf'),
    ]);

    $response->assertRedirect(route('school.documentos.show', $documento, absolute: false));

    $documento->refresh();
    expect($documento->status)->toBe(StatusDocumento::Atendido);
    expect($documento->anexo_resposta_url)->not->toBeNull();
});

it('does not list documentos from another tenant', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $admin = User::factory()->create(['ativo' => true]);
    $admin->tenants()->attach($tenant->id);

    $otherStudent = Student::create([
        'tenant_id' => $otherTenant->id,
        'nome' => 'Outro',
        'ativo' => true,
    ]);

    Documento::create([
        'tenant_id' => $otherTenant->id,
        'aluno_id' => $otherStudent->id,
        'tipo' => TipoDocumento::DocumentoEscola,
        'status' => StatusDocumento::Disponivel,
        'titulo' => 'Documento de outra escola',
        'anexo_url' => 'https://example.com/x.pdf',
    ]);

    $response = $this->actingAs($admin)->get('/school/documentos');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('school/documentos/Index')
        ->has('documentos.data', 0)
    );
});

it('includes aluno turma on documentos create form', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['ativo' => true]);
    $admin->tenants()->attach($tenant->id);

    $turma = \App\Models\Turma::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Turma 3A',
        'serie' => '3º ano',
        'turma_letra' => 'A',
        'ano_letivo' => 2026,
        'ativo' => true,
    ]);

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Carla Souza',
        'ativo' => true,
    ]);

    $driver = \Illuminate\Support\Facades\DB::connection('shared')->getDriverName();
    $matriculasTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';
    $matriculaId = (string) \Illuminate\Support\Str::uuid();
    $matriculaRow = [
        'id' => $matriculaId,
        'tenant_id' => $tenant->id,
        'aluno_id' => $student->id,
        'turma_id' => $turma->id,
        'data_matricula' => now()->toDateString(),
        'status' => 'ativo',
        'created_at' => now(),
    ];

    if ($driver === 'sqlite') {
        $matriculaRow['matricula'] = $matriculaId;
        $matriculaRow['ativo'] = true;
    }

    \Illuminate\Support\Facades\DB::connection('shared')->table($matriculasTable)->insert($matriculaRow);

    $response = $this->actingAs($admin)->get('/school/documentos/create');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('school/documentos/Create')
        ->has('alunos', 1)
        ->where('alunos.0.nome', 'Carla Souza')
        ->where('alunos.0.turma', '3º ano · Turma A')
    );
});

it('deletes a documento from the school tenant', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['ativo' => true]);
    $admin->tenants()->attach($tenant->id);

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Lucas',
        'ativo' => true,
    ]);

    $documento = Documento::create([
        'tenant_id' => $tenant->id,
        'aluno_id' => $student->id,
        'criado_por' => $admin->id,
        'tipo' => TipoDocumento::PedidoDeclaracao,
        'status' => StatusDocumento::Enviado,
        'titulo' => 'Pedido para excluir',
        'categoria_declaracao' => 'matricula',
    ]);

    $response = $this->actingAs($admin)->delete('/school/documentos/'.$documento->id);

    $response->assertRedirect(route('school.documentos.index', absolute: false));
    expect(Documento::query()->find($documento->id))->toBeNull();
});
