<?php

use App\Enums\StatusDocumento;
use App\Enums\TipoDocumento;
use App\Models\Documento;
use App\Models\Responsavel;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('public');
});

function createResponsavelWithStudent(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['ativo' => true]);
    $responsavel = Responsavel::create([
        'tenant_id' => $tenant->id,
        'usuario_id' => $user->id,
        'cpf' => $user->cpf,
    ]);

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Aluno Documentos',
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

    return compact('tenant', 'user', 'responsavel', 'student');
}

it('lists documentos only for owned student', function () {
    ['user' => $user, 'student' => $student, 'tenant' => $tenant] = createResponsavelWithStudent();

    $otherTenant = Tenant::factory()->create();
    $otherStudent = Student::create([
        'tenant_id' => $otherTenant->id,
        'nome' => 'Outro aluno',
        'ativo' => true,
    ]);

    $owned = Documento::create([
        'tenant_id' => $tenant->id,
        'aluno_id' => $student->id,
        'criado_por' => $user->id,
        'tipo' => TipoDocumento::Atestado,
        'status' => StatusDocumento::Enviado,
        'titulo' => 'Atestado médico',
        'data_inicio' => '2026-09-10',
        'data_fim' => '2026-09-12',
        'anexo_url' => 'https://example.com/atestado.pdf',
    ]);

    Documento::create([
        'tenant_id' => $otherTenant->id,
        'aluno_id' => $otherStudent->id,
        'criado_por' => $user->id,
        'tipo' => TipoDocumento::Atestado,
        'status' => StatusDocumento::Enviado,
        'titulo' => 'Não deve aparecer',
        'data_inicio' => '2026-09-10',
        'data_fim' => '2026-09-12',
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/mobile/documentos?aluno_id='.$student->id);

    $response->assertSuccessful()
        ->assertJsonCount(1, 'documentos')
        ->assertJsonPath('documentos.0.id', $owned->id)
        ->assertJsonPath('documentos.0.tipo', 'atestado')
        ->assertJsonPath('documentos.0.status', 'enviado')
        ->assertJsonStructure([
            'documentos' => [
                '*' => [
                    'id',
                    'aluno_id',
                    'tipo',
                    'status',
                    'titulo',
                    'descricao',
                    'data_inicio',
                    'data_fim',
                    'categoria_declaracao',
                    'anexo_url',
                    'anexo_resposta_url',
                    'motivo_recusa',
                    'criado_em',
                    'atualizado_em',
                ],
            ],
        ]);
});

it('forbids listing documentos of a student not linked to the responsavel', function () {
    ['user' => $user] = createResponsavelWithStudent();

    $otherTenant = Tenant::factory()->create();
    $otherStudent = Student::create([
        'tenant_id' => $otherTenant->id,
        'nome' => 'Aluno alheio',
        'ativo' => true,
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/mobile/documentos?aluno_id='.$otherStudent->id)
        ->assertForbidden();
});

it('creates atestado with anexo via multipart', function () {
    ['user' => $user, 'student' => $student] = createResponsavelWithStudent();
    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->post('/api/mobile/documentos', [
            'aluno_id' => $student->id,
            'tipo' => 'atestado',
            'data_inicio' => '2026-09-10',
            'data_fim' => '2026-09-12',
            'descricao' => 'Gripe',
            'anexo' => UploadedFile::fake()->create('atestado.pdf', 500, 'application/pdf'),
        ], [
            'Accept' => 'application/json',
        ]);

    $response->assertCreated()
        ->assertJsonPath('documento.tipo', 'atestado')
        ->assertJsonPath('documento.status', 'enviado')
        ->assertJsonPath('documento.data_inicio', '2026-09-10')
        ->assertJsonPath('documento.data_fim', '2026-09-12');

    expect($response->json('documento.anexo_url'))->not->toBeNull();
    expect(Documento::query()->count())->toBe(1);
});

it('creates pedido_declaracao via json without anexo', function () {
    ['user' => $user, 'student' => $student] = createResponsavelWithStudent();
    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/mobile/documentos', [
            'aluno_id' => $student->id,
            'tipo' => 'pedido_declaracao',
            'categoria_declaracao' => 'matricula',
            'descricao' => 'Preciso para o trabalho',
        ]);

    $response->assertCreated()
        ->assertJsonPath('documento.tipo', 'pedido_declaracao')
        ->assertJsonPath('documento.status', 'enviado')
        ->assertJsonPath('documento.categoria_declaracao', 'matricula')
        ->assertJsonPath('documento.titulo', 'Declaração de matrícula');
});

it('shows documento detail for owned student', function () {
    ['user' => $user, 'student' => $student, 'tenant' => $tenant] = createResponsavelWithStudent();

    $documento = Documento::create([
        'tenant_id' => $tenant->id,
        'aluno_id' => $student->id,
        'criado_por' => $user->id,
        'tipo' => TipoDocumento::PedidoDeclaracao,
        'status' => StatusDocumento::EmAnalise,
        'titulo' => 'Declaração de frequência',
        'categoria_declaracao' => 'frequencia',
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/mobile/documentos/'.$documento->id)
        ->assertSuccessful()
        ->assertJsonPath('documento.id', $documento->id)
        ->assertJsonPath('documento.status', 'em_analise');
});

it('rejects show for documento of another student', function () {
    ['user' => $user] = createResponsavelWithStudent();

    $otherTenant = Tenant::factory()->create();
    $otherStudent = Student::create([
        'tenant_id' => $otherTenant->id,
        'nome' => 'Aluno alheio',
        'ativo' => true,
    ]);

    $documento = Documento::create([
        'tenant_id' => $otherTenant->id,
        'aluno_id' => $otherStudent->id,
        'tipo' => TipoDocumento::DocumentoEscola,
        'status' => StatusDocumento::Disponivel,
        'titulo' => 'Segredo',
        'anexo_url' => 'https://example.com/doc.pdf',
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/mobile/documentos/'.$documento->id)
        ->assertNotFound();
});

it('uploads anexo for pending pedido', function () {
    ['user' => $user, 'student' => $student, 'tenant' => $tenant] = createResponsavelWithStudent();

    $documento = Documento::create([
        'tenant_id' => $tenant->id,
        'aluno_id' => $student->id,
        'criado_por' => $user->id,
        'tipo' => TipoDocumento::PedidoDeclaracao,
        'status' => StatusDocumento::Enviado,
        'titulo' => 'Pedido',
        'categoria_declaracao' => 'outro',
    ]);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->post('/api/mobile/documentos/'.$documento->id.'/anexo', [
            'anexo' => UploadedFile::fake()->create('comprovante.jpg', 200, 'image/jpeg'),
        ], [
            'Accept' => 'application/json',
        ]);

    $response->assertSuccessful();
    expect($response->json('documento.anexo_url'))->not->toBeNull();
});

it('rejects invalid file type for atestado', function () {
    ['user' => $user, 'student' => $student] = createResponsavelWithStudent();
    $token = $user->createToken('mobile-app')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->post('/api/mobile/documentos', [
            'aluno_id' => $student->id,
            'tipo' => 'atestado',
            'data_inicio' => '2026-09-10',
            'data_fim' => '2026-09-12',
            'anexo' => UploadedFile::fake()->create('virus.exe', 100, 'application/octet-stream'),
        ], [
            'Accept' => 'application/json',
        ])
        ->assertUnprocessable();
});
