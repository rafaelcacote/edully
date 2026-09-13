<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

function disableStudentAuthMiddleware(): void
{
    test()->withoutMiddleware([
        HandleInertiaRequests::class,
        PermissionMiddleware::class,
        RoleMiddleware::class,
        RoleOrPermissionMiddleware::class,
    ]);
}

it('updates student photo while keeping nome via method spoofing', function () {
    disableStudentAuthMiddleware();
    Storage::fake('public');

    $tenant = Tenant::factory()->create();
    $authUser = User::factory()->create();
    $authUser->tenants()->attach($tenant->id);

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'João da Silva',
        'nome_social' => null,
        'ativo' => true,
    ]);

    $foto = UploadedFile::fake()->image('aluno.jpg', 200, 200);

    // Espelha o envio do formulário Inertia: POST + _method=patch (multipart)
    $response = $this->actingAs($authUser)->post("/school/students/{$student->id}", [
        '_method' => 'patch',
        'nome' => 'João da Silva',
        'nome_social' => '',
        'ativo' => '1',
        'foto' => $foto,
    ]);

    $response->assertRedirect(route('school.students.edit', $student, absolute: false));
    $response->assertSessionDoesntHaveErrors();

    $student->refresh();

    expect($student->nome)->toBe('João da Silva');
    expect($student->foto_url)->not->toBeNull();
    expect($student->foto_url)->toContain('storage/students/photos');

    $filePath = str_replace(asset('storage/'), '', $student->foto_url);
    Storage::disk('public')->assertExists($filePath);
});

it('updates student fields without photo', function () {
    disableStudentAuthMiddleware();

    $tenant = Tenant::factory()->create();
    $authUser = User::factory()->create();
    $authUser->tenants()->attach($tenant->id);

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Maria Souza',
        'ativo' => true,
    ]);

    $response = $this->actingAs($authUser)->post("/school/students/{$student->id}", [
        '_method' => 'patch',
        'nome' => 'Maria Souza Santos',
        'nome_social' => 'Maria',
        'ativo' => '1',
    ]);

    $response->assertRedirect(route('school.students.edit', $student, absolute: false));
    $response->assertSessionDoesntHaveErrors();

    $student->refresh();

    expect($student->nome)->toBe('Maria Souza Santos');
    expect($student->nome_social)->toBe('Maria');
});

it('includes foto_url on the student show page', function () {
    disableStudentAuthMiddleware();

    $tenant = Tenant::factory()->create();
    $authUser = User::factory()->create();
    $authUser->tenants()->attach($tenant->id);

    $fotoUrl = asset('storage/students/photos/aluno-teste.jpg');

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'nome' => 'Pedro Alves',
        'foto_url' => $fotoUrl,
        'ativo' => true,
    ]);

    $response = $this->actingAs($authUser)->get("/school/students/{$student->id}");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('school/students/Show')
        ->where('student.foto_url', $fotoUrl)
        ->where('student.nome', 'Pedro Alves')
    );
});
