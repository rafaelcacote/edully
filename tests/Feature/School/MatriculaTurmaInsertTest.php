<?php

use App\Actions\School\CreateStudentAction;
use App\Actions\School\ReenrollStudentAction;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\Turma;
use App\Support\MatriculaTurmaRowBuilder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    MatriculaTurmaRowBuilder::flushColumnCache();
});

it('includes matricula when the column exists on matriculas_turma', function () {
    $tenant = Tenant::factory()->create();
    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Aluno Matricula',
        'ativo' => true,
    ]);
    $turma = Turma::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Turma Matricula',
        'serie' => '1º Ano',
        'ano_letivo' => 2026,
        'ativo' => true,
    ]);

    $row = MatriculaTurmaRowBuilder::forInsert([
        'tenant_id' => $tenant->id,
        'aluno_id' => $student->id,
        'turma_id' => $turma->id,
    ]);

    expect($row)->toHaveKey('matricula')
        ->and((string) $row['matricula'])->toBe((string) $row['id'])
        ->and($row['status'])->toBe('ativo');
});

it('creates enrollment with non-null matricula via CreateStudentAction', function () {
    $tenant = Tenant::factory()->create();
    $turma = Turma::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Turma Create',
        'serie' => '2º Ano',
        'ano_letivo' => 2026,
        'ativo' => true,
    ]);

    $student = app(CreateStudentAction::class)->execute([
        'nome' => 'Aluno Create Action',
        'turma_id' => $turma->id,
        'ativo' => true,
    ], $tenant);

    $driver = DB::connection('shared')->getDriverName();
    $pivotTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';

    $matricula = DB::connection('shared')
        ->table($pivotTable)
        ->where('aluno_id', $student->id)
        ->first();

    expect($matricula)->not->toBeNull()
        ->and($matricula->matricula)->not->toBeNull()
        ->and((string) $matricula->matricula)->toBe((string) $matricula->id);
});

it('reenrolls student with non-null matricula', function () {
    $tenant = Tenant::factory()->create();
    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Aluno Reenroll',
        'ativo' => true,
    ]);

    $turmaAtual = Turma::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Turma Atual',
        'serie' => '3º Ano',
        'ano_letivo' => 2025,
        'ativo' => true,
    ]);

    $novaTurma = Turma::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Turma Nova',
        'serie' => '4º Ano',
        'ano_letivo' => 2026,
        'ativo' => true,
    ]);

    $driver = DB::connection('shared')->getDriverName();
    $pivotTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';

    DB::connection('shared')->table($pivotTable)->insert(
        MatriculaTurmaRowBuilder::forInsert([
            'tenant_id' => $tenant->id,
            'aluno_id' => $student->id,
            'turma_id' => $turmaAtual->id,
        ])
    );

    app(ReenrollStudentAction::class)->execute($student, $novaTurma, $tenant);

    $matricula = DB::connection('shared')
        ->table($pivotTable)
        ->where('aluno_id', $student->id)
        ->where('turma_id', $novaTurma->id)
        ->where('status', 'ativo')
        ->first();

    expect($matricula)->not->toBeNull()
        ->and($matricula->matricula)->not->toBeNull()
        ->and((string) $matricula->matricula)->toBe((string) $matricula->id);
});
