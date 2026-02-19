<?php

namespace App\Http\Controllers\School;

use App\Actions\School\CreateStudentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\StoreStudentRequest;
use App\Models\Responsavel;
use App\Models\Student;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ParentStudentsController extends Controller
{
    public function __construct(protected CreateStudentAction $createStudentAction) {}

    protected function pivotTable(): string
    {
        return DB::connection('shared')->getDriverName() === 'sqlite'
            ? 'aluno_responsavel'
            : 'escola.aluno_responsavel';
    }

    protected function getTenant(): Tenant
    {
        $user = auth()->user();
        $tenant = $user->tenants()->first();

        if (! $tenant) {
            abort(404, 'Escola não encontrada');
        }

        return $tenant;
    }

    public function store(StoreStudentRequest $request, Responsavel $parent): RedirectResponse
    {
        $tenant = $this->getTenant();

        if ($parent->tenant_id !== $tenant->id) {
            abort(404);
        }

        $validated = $request->validated();

        DB::connection('shared')->transaction(function () use ($tenant, $validated, $parent) {
            $student = $this->createStudentAction->execute($validated, $tenant);

            $this->attachParentStudent($parent, $student, $tenant);
        });

        return redirect()
            ->route('school.parents.show', $parent)
            ->with('toast', [
                'type' => 'success',
                'title' => 'Aluno vinculado',
                'message' => 'O aluno foi criado e vinculado ao responsável.',
            ]);
    }

    public function attach(Request $request, Responsavel $parent): RedirectResponse
    {
        $tenant = $this->getTenant();

        if ($parent->tenant_id !== $tenant->id) {
            abort(404);
        }

        $request->validate([
            'student_id' => ['required', 'string'],
        ]);

        $student = Student::findOrFail($request->student_id);

        if ($student->tenant_id !== $tenant->id) {
            abort(404, 'Aluno não pertence a esta escola');
        }

        try {
            $this->attachParentStudent($parent, $student, $tenant);

            return redirect()
                ->route('school.parents.show', $parent)
                ->with('toast', [
                    'type' => 'success',
                    'title' => 'Aluno vinculado',
                    'message' => 'O aluno foi vinculado ao responsável com sucesso.',
                ]);
        } catch (\Exception $e) {
            return redirect()
                ->route('school.parents.show', $parent)
                ->with('toast', [
                    'type' => 'error',
                    'title' => 'Erro ao vincular',
                    'message' => $e->getMessage(),
                ]);
        }
    }

    public function destroy(Responsavel $parent, Student $student): RedirectResponse
    {
        $tenant = $this->getTenant();

        if ($parent->tenant_id !== $tenant->id || $student->tenant_id !== $tenant->id) {
            abort(404);
        }

        $this->detachParentStudent($parent, $student, $tenant);

        return redirect()
            ->route('school.parents.show', $parent)
            ->with('toast', [
                'type' => 'success',
                'title' => 'Aluno desvinculado',
                'message' => 'O vínculo do aluno com este responsável foi removido.',
            ]);
    }

    protected function attachParentStudent(Responsavel $parent, Student $student, Tenant $tenant): void
    {
        $connection = DB::connection('shared');
        $pivotTable = $this->pivotTable();

        $alreadyLinked = $connection->table($pivotTable)
            ->where('tenant_id', $tenant->id)
            ->where('aluno_id', $student->id)
            ->where('responsavel_id', $parent->id)
            ->exists();

        if ($alreadyLinked) {
            throw new \Exception('Este aluno já está vinculado a este responsável.');
        }

        $connection->table($pivotTable)->insert([
            'id' => Str::uuid(),
            'tenant_id' => $tenant->id,
            'aluno_id' => $student->id,
            'responsavel_id' => $parent->id,
            'principal' => false,
            'created_at' => now(),
        ]);
    }

    protected function detachParentStudent(Responsavel $parent, Student $student, Tenant $tenant): void
    {
        DB::connection('shared')
            ->table($this->pivotTable())
            ->where('tenant_id', $tenant->id)
            ->where('aluno_id', $student->id)
            ->where('responsavel_id', $parent->id)
            ->delete();
    }
}
