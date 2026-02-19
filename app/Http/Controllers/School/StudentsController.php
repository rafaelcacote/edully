<?php

namespace App\Http\Controllers\School;

use App\Actions\School\CreateStudentAction;
use App\Actions\School\ReenrollStudentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\ReenrollStudentRequest;
use App\Http\Requests\School\StoreStudentRequest;
use App\Http\Requests\School\UpdateStudentRequest;
use App\Models\Student;
use App\Models\Turma;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class StudentsController extends Controller
{
    public function __construct(
        protected CreateStudentAction $createStudentAction,
        protected ReenrollStudentAction $reenrollStudentAction
    ) {}

    protected function alunosTable(): string
    {
        return DB::connection('shared')->getDriverName() === 'sqlite'
            ? 'alunos'
            : 'escola.alunos';
    }

    /**
     * Get the current user's tenant.
     */
    protected function getTenant()
    {
        $user = auth()->user();
        $tenant = $user->tenants()->first();

        if (! $tenant) {
            abort(404, 'Escola não encontrada');
        }

        return $tenant;
    }

    /**
     * Display a listing of the students.
     */
    public function index(Request $request): Response
    {
        $tenant = $this->getTenant();
        $filters = $request->only(['search', 'active']);

        $connection = DB::connection('shared');
        $driver = $connection->getDriverName();
        $likeOperator = $driver === 'pgsql' ? 'ilike' : 'like';
        $pivotTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';

        $studentsQuery = $connection
            ->table($this->alunosTable().' as alunos')
            ->where('alunos.tenant_id', $tenant->id)
            ->when($filters['search'] ?? null, function ($query, string $search) use ($likeOperator) {
                $search = trim($search);

                $query->where(function ($q) use ($search, $likeOperator) {
                    $q->where('alunos.nome', $likeOperator, "%{$search}%")
                        ->orWhere('alunos.nome_social', $likeOperator, "%{$search}%");
                });
            })
            ->when(isset($filters['active']) && $filters['active'] !== '' && $filters['active'] !== null, function ($query) use ($filters) {
                $active = filter_var($filters['active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($active !== null) {
                    $query->where('alunos.ativo', $active);
                }
            })
            ->orderBy('alunos.nome')
            ->select([
                'alunos.id',
                'alunos.nome',
                'alunos.nome_social',
                'alunos.foto_url',
                'alunos.data_nascimento',
                'alunos.ativo',
            ]);

        $students = $studentsQuery->paginate(10)->withQueryString();

        // Buscar turmas dos alunos
        $studentIds = collect($students->items())->pluck('id')->toArray();
        $matriculas = ! empty($studentIds) ? $connection
            ->table($pivotTable)
            ->where('tenant_id', $tenant->id)
            ->where('status', 'ativo')
            ->whereIn('aluno_id', $studentIds)
            ->get(['aluno_id', 'turma_id']) : collect();

        $turmaIds = $matriculas->pluck('turma_id')->unique()->toArray();
        $turmasMap = ! empty($turmaIds) ? Turma::query()
            ->whereIn('id', $turmaIds)
            ->get(['id', 'nome', 'serie', 'turma_letra', 'ano_letivo'])
            ->keyBy('id') : collect();

        $matriculasMap = $matriculas->groupBy('aluno_id')->map(function ($items) {
            return $items->first()->turma_id;
        });

        // Adicionar turma a cada aluno
        $transformedItems = $students->getCollection()->map(function ($student) use ($matriculasMap, $turmasMap) {
            $turmaId = $matriculasMap->get($student->id);
            $turma = $turmaId ? $turmasMap->get($turmaId) : null;

            $student->turma = $turma ? [
                'id' => $turma->id,
                'nome' => $turma->nome,
                'serie' => $turma->serie,
                'turma_letra' => $turma->turma_letra,
                'ano_letivo' => $turma->ano_letivo,
            ] : null;

            return $student;
        });

        $students->setCollection($transformedItems);

        return Inertia::render('school/students/Index', [
            'students' => $students,
            'filters' => $filters,
        ]);
    }

    /**
     * Show the form for creating a new student.
     */
    public function create(): Response
    {
        $tenant = $this->getTenant();

        $turmas = Turma::query()
            ->where('tenant_id', $tenant->id)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome', 'serie', 'turma_letra', 'ano_letivo']);

        return Inertia::render('school/students/Create', [
            'turmas' => $turmas,
        ]);
    }

    /**
     * Store a newly created student.
     */
    public function store(StoreStudentRequest $request): RedirectResponse
    {
        $tenant = $this->getTenant();
        $validated = $request->validated();

        DB::connection('shared')->transaction(function () use ($tenant, $validated) {
            $this->createStudentAction->execute($validated, $tenant);
        });

        return redirect()
            ->route('school.students.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Aluno criado',
                'message' => 'O aluno foi cadastrado com sucesso.',
            ]);
    }

    /**
     * Display the specified student.
     */
    public function show(Student $student): Response
    {
        $tenant = $this->getTenant();

        if ($student->tenant_id !== $tenant->id) {
            abort(404);
        }

        $student->load(['parents.user']);

        // Buscar turma do aluno
        $driver = DB::connection('shared')->getDriverName();
        $pivotTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';

        $matricula = DB::connection('shared')
            ->table($pivotTable)
            ->where('tenant_id', $tenant->id)
            ->where('aluno_id', $student->id)
            ->where('status', 'ativo')
            ->first(['turma_id']);

        $turma = null;
        if ($matricula) {
            $turma = Turma::query()
                ->where('id', $matricula->turma_id)
                ->first(['id', 'nome', 'serie', 'turma_letra', 'ano_letivo']);
        }

        // Buscar turmas disponíveis para rematrícula (excluindo a turma atual)
        $turmasQuery = Turma::query()
            ->where('tenant_id', $tenant->id)
            ->where('ativo', true);

        if ($turma) {
            $turmasQuery->where('id', '!=', $turma->id);
        }

        $turmas = $turmasQuery
            ->orderBy('ano_letivo', 'desc')
            ->orderBy('serie')
            ->orderBy('nome')
            ->get(['id', 'nome', 'serie', 'turma_letra', 'ano_letivo']);

        return Inertia::render('school/students/Show', [
            'student' => [
                'id' => $student->id,
                'nome' => $student->nome,
                'nome_social' => $student->nome_social,
                'foto_url' => $student->foto_url,
                'data_nascimento' => optional($student->data_nascimento)->toDateString(),
                'informacoes_medicas' => $student->informacoes_medicas,
                'ativo' => (bool) $student->ativo,
                'turma' => $turma ? [
                    'id' => $turma->id,
                    'nome' => $turma->nome,
                    'serie' => $turma->serie,
                    'turma_letra' => $turma->turma_letra,
                    'ano_letivo' => $turma->ano_letivo,
                ] : null,
                'parents' => $student->parents->map(fn ($parent) => [
                    'id' => $parent->id,
                    'nome_completo' => $parent->user?->nome_completo,
                    'parentesco' => $parent->parentesco,
                ])->values(),
            ],
            'turmas' => $turmas->map(fn ($t) => [
                'id' => $t->id,
                'nome' => $t->nome,
                'serie' => $t->serie,
                'turma_letra' => $t->turma_letra,
                'ano_letivo' => $t->ano_letivo,
            ])->values(),
        ]);
    }

    /**
     * Show the form for editing the specified student.
     */
    public function edit(Student $student): Response
    {
        $tenant = $this->getTenant();

        if ($student->tenant_id !== $tenant->id) {
            abort(404);
        }

        // Buscar turma atual do aluno
        $driver = DB::connection('shared')->getDriverName();
        $pivotTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';

        $matricula = DB::connection('shared')
            ->table($pivotTable)
            ->where('tenant_id', $tenant->id)
            ->where('aluno_id', $student->id)
            ->where('status', 'ativo')
            ->first(['turma_id']);

        $turmaAtualId = $matricula?->turma_id;

        $turmas = Turma::query()
            ->where('tenant_id', $tenant->id)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome', 'serie', 'turma_letra', 'ano_letivo']);

        return Inertia::render('school/students/Edit', [
            'student' => [
                'id' => $student->id,
                'nome' => $student->nome,
                'nome_social' => $student->nome_social,
                'foto_url' => $student->foto_url,
                'data_nascimento' => optional($student->data_nascimento)->toDateString(),
                'informacoes_medicas' => $student->informacoes_medicas,
                'ativo' => (bool) $student->ativo,
                'turma_id' => $turmaAtualId,
            ],
            'turmas' => $turmas,
        ]);
    }

    /**
     * Update the specified student.
     */
    public function update(UpdateStudentRequest $request, Student $student): RedirectResponse
    {
        $tenant = $this->getTenant();

        if ($student->tenant_id !== $tenant->id) {
            abort(404);
        }

        $validated = $request->validated();

        DB::connection('shared')->transaction(function () use ($student, $validated, $tenant, $request) {
            // Processar upload da foto
            $fotoUrl = $student->foto_url;
            if ($request->hasFile('foto')) {
                // Deletar foto antiga se existir e for do storage local
                if ($student->foto_url) {
                    $storageBaseUrl = asset('storage/');
                    if (str_starts_with($student->foto_url, $storageBaseUrl)) {
                        $oldFotoPath = str_replace($storageBaseUrl, '', $student->foto_url);
                        if (Storage::disk('public')->exists($oldFotoPath)) {
                            Storage::disk('public')->delete($oldFotoPath);
                        }
                    }
                }

                $foto = $request->file('foto');
                $fotoPath = $foto->store('students/photos', 'public');
                $fotoUrl = asset('storage/'.$fotoPath);
            }

            $student->update([
                'nome' => $validated['nome'],
                'nome_social' => $validated['nome_social'] ?? null,
                'foto_url' => $fotoUrl,
                'data_nascimento' => $validated['data_nascimento'] ?? null,
                'informacoes_medicas' => $validated['informacoes_medicas'] ?? null,
                'ativo' => $validated['ativo'] ?? true,
            ]);

            // Atualizar turma do aluno se fornecida
            if (isset($validated['turma_id'])) {
                $driver = DB::connection('shared')->getDriverName();
                $pivotTable = $driver === 'sqlite' ? 'matriculas_turma' : 'escola.matriculas_turma';

                $turma = Turma::findOrFail($validated['turma_id']);

                if ($turma->tenant_id !== $tenant->id) {
                    throw new \Exception('Turma não pertence ao tenant');
                }

                // Desativar matrícula atual se existir
                DB::connection('shared')
                    ->table($pivotTable)
                    ->where('tenant_id', $tenant->id)
                    ->where('aluno_id', $student->id)
                    ->where('status', 'ativo')
                    ->update(['status' => 'inativo']);

                // Verificar se já existe matrícula para esta turma
                $matriculaExistente = DB::connection('shared')
                    ->table($pivotTable)
                    ->where('tenant_id', $tenant->id)
                    ->where('aluno_id', $student->id)
                    ->where('turma_id', $turma->id)
                    ->first();

                if ($matriculaExistente) {
                    // Reativar matrícula existente
                    DB::connection('shared')
                        ->table($pivotTable)
                        ->where('id', $matriculaExistente->id)
                        ->update([
                            'status' => 'ativo',
                            'data_matricula' => now()->toDateString(),
                        ]);
                } else {
                    // Criar nova matrícula
                    $matriculaId = \Illuminate\Support\Str::uuid();

                    DB::connection('shared')->table($pivotTable)->insert([
                        'id' => $matriculaId,
                        'tenant_id' => $tenant->id,
                        'aluno_id' => $student->id,
                        'turma_id' => $turma->id,
                        'data_matricula' => now()->toDateString(),
                        'status' => 'ativo',
                        'created_at' => now(),
                    ]);
                }
            }
        });

        return redirect()
            ->route('school.students.edit', $student)
            ->with('toast', [
                'type' => 'success',
                'title' => 'Aluno atualizado',
                'message' => 'As alterações foram salvas com sucesso.',
            ]);
    }

    /**
     * Remove the specified student.
     */
    public function destroy(Student $student): RedirectResponse
    {
        $tenant = $this->getTenant();

        if ($student->tenant_id !== $tenant->id) {
            abort(404);
        }

        $student->delete();

        return redirect()
            ->route('school.students.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Aluno excluído',
                'message' => 'O aluno foi removido com sucesso.',
            ]);
    }

    /**
     * Rematricular um aluno em uma nova turma de um novo ano letivo.
     */
    public function reenroll(ReenrollStudentRequest $request, Student $student): RedirectResponse
    {
        $tenant = $this->getTenant();

        if ($student->tenant_id !== $tenant->id) {
            abort(404);
        }

        $validated = $request->validated();
        $novaTurma = Turma::findOrFail($validated['turma_id']);

        try {
            $this->reenrollStudentAction->execute($student, $novaTurma, $tenant);

            return redirect()
                ->route('school.students.show', $student)
                ->with('toast', [
                    'type' => 'success',
                    'title' => 'Rematrícula realizada',
                    'message' => "O aluno foi rematriculado na turma {$novaTurma->nome} ({$novaTurma->ano_letivo}).",
                ]);
        } catch (\Exception $e) {
            return redirect()
                ->route('school.students.show', $student)
                ->with('toast', [
                    'type' => 'error',
                    'title' => 'Erro na rematrícula',
                    'message' => $e->getMessage(),
                ]);
        }
    }

    /**
     * Search students for autocomplete/select.
     */
    public function search(Request $request): \Illuminate\Http\JsonResponse
    {
        $tenant = $this->getTenant();
        $search = $request->get('search', '');
        $limit = min((int) $request->get('limit', 20), 50);

        $connection = DB::connection('shared');
        $driver = $connection->getDriverName();
        $likeOperator = $driver === 'pgsql' ? 'ilike' : 'like';

        $query = $connection
            ->table($this->alunosTable().' as alunos')
            ->where('alunos.tenant_id', $tenant->id)
            ->whereNull('alunos.deleted_at')
            ->when($search, function ($q) use ($search, $likeOperator) {
                $search = trim($search);
                $q->where(function ($query) use ($search, $likeOperator) {
                    $query->where('alunos.nome', $likeOperator, "%{$search}%")
                        ->orWhere('alunos.nome_social', $likeOperator, "%{$search}%");
                });
            })
            ->orderBy('alunos.nome')
            ->limit($limit)
            ->select([
                'alunos.id',
                'alunos.nome',
                'alunos.nome_social',
                'alunos.foto_url',
                'alunos.ativo',
            ]);

        $students = $query->get();

        return response()->json([
            'students' => $students->map(function ($student) {
                return [
                    'id' => $student->id,
                    'nome' => $student->nome,
                    'nome_social' => $student->nome_social,
                    'foto_url' => $student->foto_url,
                    'ativo' => (bool) $student->ativo,
                ];
            }),
        ]);
    }
}
