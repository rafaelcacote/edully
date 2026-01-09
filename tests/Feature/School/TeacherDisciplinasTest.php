<?php

use App\Models\Disciplina;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create tenant
    $this->tenant = Tenant::factory()->create();

    // Create admin user with tenant
    $this->user = User::factory()->create([
        'nome_completo' => 'Admin User',
    ]);
    $this->user->tenants()->attach($this->tenant->id);
    $this->user->assignRole('Admin');

    // Create some disciplinas
    $this->disciplina1 = Disciplina::create([
        'tenant_id' => $this->tenant->id,
        'nome' => 'Matemática',
        'sigla' => 'MAT',
        'ativo' => true,
    ]);

    $this->disciplina2 = Disciplina::create([
        'tenant_id' => $this->tenant->id,
        'nome' => 'Português',
        'sigla' => 'PORT',
        'ativo' => true,
    ]);

    $this->disciplina3 = Disciplina::create([
        'tenant_id' => $this->tenant->id,
        'nome' => 'Ciências',
        'sigla' => 'CIE',
        'ativo' => true,
    ]);

    $this->actingAs($this->user);
});

it('can create a teacher with disciplinas', function () {
    $response = $this->post(route('school.teachers.store'), [
        'nome_completo' => 'Professor Teste',
        'cpf' => '12345678901',
        'email' => 'professor@test.com',
        'telefone' => '11999999999',
        'matricula' => 'PROF001',
        'especializacao' => 'Educação',
        'disciplinas' => json_encode([$this->disciplina1->id, $this->disciplina2->id]),
        'ativo' => true,
    ]);

    $response->assertRedirect(route('school.teachers.index'));

    // Verify teacher was created
    $teacher = Teacher::query()->where('matricula', 'PROF001')->first();
    expect($teacher)->not->toBeNull();

    // Verify disciplinas were saved in pivot table
    $pivotTable = DB::connection('shared')->getDriverName() === 'sqlite'
        ? 'professor_disciplinas'
        : 'escola.professor_disciplinas';

    $disciplinas = DB::connection('shared')
        ->table($pivotTable)
        ->where('tenant_id', $this->tenant->id)
        ->where('professor_id', $teacher->id)
        ->get();

    expect($disciplinas)->toHaveCount(2);

    $disciplinaIds = $disciplinas->pluck('disciplina_id')->toArray();
    expect($disciplinaIds)->toContain($this->disciplina1->id);
    expect($disciplinaIds)->toContain($this->disciplina2->id);
});

it('can update teacher disciplinas', function () {
    // Create a teacher with initial disciplinas
    $response = $this->post(route('school.teachers.store'), [
        'nome_completo' => 'Professor Teste',
        'cpf' => '12345678901',
        'email' => 'professor@test.com',
        'telefone' => '11999999999',
        'matricula' => 'PROF001',
        'disciplinas' => json_encode([$this->disciplina1->id]),
        'ativo' => true,
    ]);

    $teacher = Teacher::query()->where('matricula', 'PROF001')->first();

    // Update with different disciplinas
    $response = $this->put(route('school.teachers.update', $teacher), [
        'nome_completo' => 'Professor Teste',
        'cpf' => '12345678901',
        'email' => 'professor@test.com',
        'telefone' => '11999999999',
        'matricula' => 'PROF001',
        'disciplinas' => json_encode([$this->disciplina2->id, $this->disciplina3->id]),
        'especializacao' => 'Educação',
        'ativo' => true,
    ]);

    $response->assertRedirect(route('school.teachers.edit', $teacher));

    // Verify disciplinas were updated in pivot table
    $pivotTable = DB::connection('shared')->getDriverName() === 'sqlite'
        ? 'professor_disciplinas'
        : 'escola.professor_disciplinas';

    $disciplinas = DB::connection('shared')
        ->table($pivotTable)
        ->where('tenant_id', $this->tenant->id)
        ->where('professor_id', $teacher->id)
        ->get();

    expect($disciplinas)->toHaveCount(2);

    $disciplinaIds = $disciplinas->pluck('disciplina_id')->toArray();
    expect($disciplinaIds)->toContain($this->disciplina2->id);
    expect($disciplinaIds)->toContain($this->disciplina3->id);
    expect($disciplinaIds)->not->toContain($this->disciplina1->id);
});

it('can remove all disciplinas from a teacher', function () {
    // Create a teacher with disciplinas
    $response = $this->post(route('school.teachers.store'), [
        'nome_completo' => 'Professor Teste',
        'cpf' => '12345678901',
        'email' => 'professor@test.com',
        'telefone' => '11999999999',
        'matricula' => 'PROF001',
        'disciplinas' => json_encode([$this->disciplina1->id, $this->disciplina2->id]),
        'ativo' => true,
    ]);

    $teacher = Teacher::query()->where('matricula', 'PROF001')->first();

    // Update with no disciplinas
    $response = $this->put(route('school.teachers.update', $teacher), [
        'nome_completo' => 'Professor Teste',
        'cpf' => '12345678901',
        'email' => 'professor@test.com',
        'telefone' => '11999999999',
        'matricula' => 'PROF001',
        'disciplinas' => json_encode([]),
        'especializacao' => 'Educação',
        'ativo' => true,
    ]);

    // Verify all disciplinas were removed from pivot table
    $pivotTable = DB::connection('shared')->getDriverName() === 'sqlite'
        ? 'professor_disciplinas'
        : 'escola.professor_disciplinas';

    $disciplinas = DB::connection('shared')
        ->table($pivotTable)
        ->where('tenant_id', $this->tenant->id)
        ->where('professor_id', $teacher->id)
        ->get();

    expect($disciplinas)->toHaveCount(0);
});

it('can load teacher with disciplinas relationship', function () {
    // Create a teacher with disciplinas
    $response = $this->post(route('school.teachers.store'), [
        'nome_completo' => 'Professor Teste',
        'cpf' => '12345678901',
        'email' => 'professor@test.com',
        'telefone' => '11999999999',
        'matricula' => 'PROF001',
        'disciplinas' => json_encode([$this->disciplina1->id, $this->disciplina2->id]),
        'ativo' => true,
    ]);

    $teacher = Teacher::query()
        ->where('matricula', 'PROF001')
        ->with('disciplinas')
        ->first();

    expect($teacher->disciplinas)->toHaveCount(2);

    $disciplinaIds = $teacher->disciplinas->pluck('id')->toArray();
    expect($disciplinaIds)->toContain($this->disciplina1->id);
    expect($disciplinaIds)->toContain($this->disciplina2->id);
});
