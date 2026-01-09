<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Http\Requests\School\StoreTeacherRequest;
use App\Http\Requests\School\UpdateTeacherRequest;
use App\Models\Disciplina;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TeachersController extends Controller
{
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
     * Check if CPF already exists.
     */
    public function checkCpf(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'cpf' => ['required', 'string'],
        ]);

        $cpf = preg_replace('/[^0-9]/', '', $request->input('cpf'));

        if (strlen($cpf) !== 11) {
            return response()->json([
                'exists' => false,
                'valid' => false,
            ]);
        }

        $exists = User::query()->where('cpf', $cpf)->exists();

        return response()->json([
            'exists' => $exists,
            'valid' => $this->validateCpf($cpf),
        ]);
    }

    /**
     * Validate CPF using Brazilian algorithm.
     */
    private function validateCpf(string $cpf): bool
    {
        // Remove non-numeric characters
        $cpf = preg_replace('/[^0-9]/', '', $cpf);

        // Check if has 11 digits
        if (strlen($cpf) !== 11) {
            return false;
        }

        // Check for known invalid CPFs
        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        // Validate check digits
        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += (int) $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ((int) $cpf[$c] !== $d) {
                return false;
            }
        }

        return true;
    }

    /**
     * Display a listing of the teachers.
     */
    public function index(Request $request): Response
    {
        $tenant = $this->getTenant();
        $filters = $request->only(['search', 'active']);

        $teachers = Teacher::query()
            ->where('tenant_id', $tenant->id)
            ->with([
                'usuario:id,nome_completo,cpf,email,telefone',
                'disciplinas:id,nome,sigla',
            ])
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $search = trim($search);
                $cpfSearch = preg_replace('/[^0-9]/', '', $search);
                $query->where(function ($q) use ($search, $cpfSearch) {
                    $q->where('matricula', 'ilike', "%{$search}%")
                        ->orWhereHas('usuario', function ($userQuery) use ($search, $cpfSearch) {
                            $userQuery->where('nome_completo', 'ilike', "%{$search}%")
                                ->orWhere('email', 'ilike', "%{$search}%")
                                ->orWhere('telefone', 'ilike', "%{$search}%")
                                ->orWhere('cpf', 'ilike', "%{$cpfSearch}%");
                        });
                });
            })
            ->when(isset($filters['active']) && $filters['active'] !== '' && $filters['active'] !== null, function ($query) use ($filters) {
                $active = filter_var($filters['active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($active !== null) {
                    $query->where('ativo', $active);
                }
            })
            ->orderBy('matricula')
            ->paginate(10)
            ->withQueryString()
            ->through(function ($teacher) {
                try {
                    $disciplinas = [];
                    if ($teacher->relationLoaded('disciplinas')) {
                        $disciplinasRelation = $teacher->getRelation('disciplinas');
                        if (is_object($disciplinasRelation) && method_exists($disciplinasRelation, 'map')) {
                            $disciplinas = $disciplinasRelation->map(fn ($d) => $d->nome ?? $d->sigla)->toArray();
                        }
                    } elseif (method_exists($teacher, 'disciplinas')) {
                        $disciplinasRelation = $teacher->disciplinas;
                        if (is_object($disciplinasRelation) && method_exists($disciplinasRelation, 'map')) {
                            $disciplinas = $disciplinasRelation->map(fn ($d) => $d->nome ?? $d->sigla)->toArray();
                        }
                    }
                } catch (\Exception $e) {
                    $disciplinas = [];
                }

                return [
                    'id' => $teacher->id,
                    'matricula' => $teacher->matricula,
                    'disciplinas' => $disciplinas,
                    'especializacao' => $teacher->especializacao,
                    'ativo' => $teacher->ativo,
                    'nome_completo' => $teacher->usuario?->nome_completo,
                    'cpf' => $teacher->usuario?->cpf,
                    'email' => $teacher->usuario?->email,
                    'telefone' => $teacher->usuario?->telefone,
                ];
            });

        return Inertia::render('school/teachers/Index', [
            'teachers' => $teachers,
            'filters' => $filters,
        ]);
    }

    /**
     * Show the form for creating a new teacher.
     */
    public function create(): Response
    {
        $tenant = $this->getTenant();

        $disciplinas = Disciplina::query()
            ->where('tenant_id', $tenant->id)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome', 'sigla']);

        return Inertia::render('school/teachers/Create', [
            'disciplinas' => $disciplinas->map(fn ($d) => [
                'id' => $d->id,
                'nome' => $d->nome,
                'sigla' => $d->sigla,
            ]),
        ]);
    }

    /**
     * Store a newly created teacher.
     */
    public function store(StoreTeacherRequest $request): RedirectResponse
    {
        $tenant = $this->getTenant();
        $validated = $request->validated();

        DB::transaction(function () use ($tenant, $validated) {
            // Process disciplinas if it's a JSON string
            \Log::info('Store Teacher - Dados recebidos:', [
                'disciplinas_raw' => $validated['disciplinas'] ?? 'não enviado',
                'disciplinas_type' => gettype($validated['disciplinas'] ?? null),
            ]);

            if (isset($validated['disciplinas'])) {
                if (is_string($validated['disciplinas'])) {
                    $disciplinasJson = json_decode($validated['disciplinas'], true);
                    $validated['disciplinas'] = is_array($disciplinasJson) ? $disciplinasJson : [];
                } elseif (! is_array($validated['disciplinas'])) {
                    $validated['disciplinas'] = [];
                }
            }

            \Log::info('Store Teacher - Disciplinas processadas:', [
                'disciplinas' => $validated['disciplinas'] ?? [],
            ]);
            // Remove CPF formatting
            if (! empty($validated['cpf'])) {
                $validated['cpf'] = preg_replace('/[^0-9]/', '', $validated['cpf']);
            }

            // Determine password: use provided password, or CPF, or default
            $password = $validated['password'] ?? $validated['cpf'] ?? 'password';

            // Create the user first
            $user = User::create([
                'nome_completo' => $validated['nome_completo'],
                'cpf' => $validated['cpf'] ?? null,
                'email' => $validated['email'] ?? null,
                'telefone' => $validated['telefone'] ?? null,
                'password_hash' => Hash::make($password),
                'ativo' => true,
            ]);

            // Assign the "Professor" role to the user
            $user->assignRole('Professor');

            // Link the user to the tenant
            $user->tenants()->syncWithoutDetaching([$tenant->id]);

            // Create the teacher linked to the user
            $teacher = Teacher::create([
                'tenant_id' => $tenant->id,
                'usuario_id' => $user->id,
                'matricula' => $validated['matricula'],
                'especializacao' => $validated['especializacao'] ?? null,
                'ativo' => $validated['ativo'] ?? true,
            ]);

            // Sync disciplinas if provided
            $disciplinasToSync = $validated['disciplinas'] ?? [];
            $this->syncTeacherDisciplinas($teacher, $disciplinasToSync, $tenant);
        });

        return redirect()
            ->route('school.teachers.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Professor criado',
                'message' => 'O professor foi cadastrado com sucesso.',
            ]);
    }

    /**
     * Display the specified teacher.
     */
    public function show(Teacher $teacher): Response
    {
        $tenant = $this->getTenant();

        if ($teacher->tenant_id !== $tenant->id) {
            abort(404);
        }

        $teacher->load(['usuario:id,nome_completo,cpf,email,telefone', 'disciplinas:id,nome,sigla']);

        // Get disciplinas names safely
        $disciplinasNames = [];
        try {
            if ($teacher->relationLoaded('disciplinas')) {
                $disciplinasRelation = $teacher->getRelation('disciplinas');
                if (is_object($disciplinasRelation) && method_exists($disciplinasRelation, 'map')) {
                    $disciplinasNames = $disciplinasRelation->map(fn ($d) => $d->nome ?? $d->sigla)->toArray();
                }
            } elseif (method_exists($teacher, 'disciplinas')) {
                $disciplinasRelation = $teacher->disciplinas;
                if (is_object($disciplinasRelation) && method_exists($disciplinasRelation, 'map')) {
                    $disciplinasNames = $disciplinasRelation->map(fn ($d) => $d->nome ?? $d->sigla)->toArray();
                }
            }
        } catch (\Exception $e) {
            $disciplinasNames = [];
        }

        return Inertia::render('school/teachers/Show', [
            'teacher' => [
                'id' => $teacher->id,
                'matricula' => $teacher->matricula,
                'disciplinas' => $disciplinasNames,
                'especializacao' => $teacher->especializacao,
                'ativo' => $teacher->ativo,
                'nome_completo' => $teacher->usuario?->nome_completo,
                'cpf' => $teacher->usuario?->cpf,
                'email' => $teacher->usuario?->email,
                'telefone' => $teacher->usuario?->telefone,
            ],
        ]);
    }

    /**
     * Show the form for editing the specified teacher.
     */
    public function edit(Teacher $teacher): Response
    {
        $tenant = $this->getTenant();

        if ($teacher->tenant_id !== $tenant->id) {
            abort(404);
        }

        $teacher->load(['usuario:id,nome_completo,cpf,email,telefone']);

        $disciplinas = Disciplina::query()
            ->where('tenant_id', $tenant->id)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome', 'sigla']);

        // Get disciplinas IDs directly from pivot table
        $pivotTable = $teacher->getConnection()->getDriverName() === 'sqlite'
            ? 'professor_disciplinas'
            : 'escola.professor_disciplinas';

        $teacherDisciplinaIds = DB::connection('shared')
            ->table($pivotTable)
            ->where('tenant_id', $tenant->id)
            ->where('professor_id', $teacher->id)
            ->pluck('disciplina_id')
            ->toArray();

        return Inertia::render('school/teachers/Edit', [
            'teacher' => [
                'id' => $teacher->id,
                'matricula' => $teacher->matricula,
                'disciplinas' => $teacherDisciplinaIds,
                'especializacao' => $teacher->especializacao,
                'ativo' => $teacher->ativo,
                'nome_completo' => $teacher->usuario?->nome_completo,
                'cpf' => $teacher->usuario?->cpf,
                'email' => $teacher->usuario?->email,
                'telefone' => $teacher->usuario?->telefone,
            ],
            'disciplinas' => $disciplinas->map(fn ($d) => [
                'id' => $d->id,
                'nome' => $d->nome,
                'sigla' => $d->sigla,
            ]),
        ]);
    }

    /**
     * Update the specified teacher.
     */
    public function update(UpdateTeacherRequest $request, Teacher $teacher): RedirectResponse
    {
        $tenant = $this->getTenant();

        if ($teacher->tenant_id !== $tenant->id) {
            abort(404);
        }

        $validated = $request->validated();

        DB::transaction(function () use ($teacher, $validated, $tenant) {
            // Process disciplinas if it's a JSON string
            \Log::info('Update Teacher - Dados recebidos:', [
                'teacher_id' => $teacher->id,
                'disciplinas_raw' => $validated['disciplinas'] ?? 'não enviado',
                'disciplinas_type' => gettype($validated['disciplinas'] ?? null),
            ]);

            if (isset($validated['disciplinas'])) {
                if (is_string($validated['disciplinas'])) {
                    $disciplinasJson = json_decode($validated['disciplinas'], true);
                    $validated['disciplinas'] = is_array($disciplinasJson) ? $disciplinasJson : [];
                } elseif (! is_array($validated['disciplinas'])) {
                    $validated['disciplinas'] = [];
                }
            }

            \Log::info('Update Teacher - Disciplinas processadas:', [
                'disciplinas' => $validated['disciplinas'] ?? [],
            ]);
            // Remove CPF formatting
            if (! empty($validated['cpf'])) {
                $validated['cpf'] = preg_replace('/[^0-9]/', '', $validated['cpf']);
            }

            // Update the user
            $teacher->usuario->update([
                'nome_completo' => $validated['nome_completo'],
                'cpf' => $validated['cpf'] ?? null,
                'email' => $validated['email'] ?? null,
                'telefone' => $validated['telefone'] ?? null,
            ]);

            // Update password if provided
            if (! empty($validated['password'])) {
                $teacher->usuario->update([
                    'password_hash' => Hash::make($validated['password']),
                ]);
            }

            // Update the teacher
            $teacher->update([
                'matricula' => $validated['matricula'],
                'especializacao' => $validated['especializacao'] ?? null,
                'ativo' => $validated['ativo'] ?? $teacher->ativo,
            ]);

            // Sync disciplinas
            $disciplinasToSync = $validated['disciplinas'] ?? [];
            $this->syncTeacherDisciplinas($teacher, $disciplinasToSync, $tenant);
        });

        return redirect()
            ->route('school.teachers.edit', $teacher)
            ->with('toast', [
                'type' => 'success',
                'title' => 'Professor atualizado',
                'message' => 'As alterações foram salvas com sucesso.',
            ]);
    }

    /**
     * Sync teacher disciplinas.
     */
    protected function syncTeacherDisciplinas(Teacher $teacher, array $disciplinaIds, $tenant): void
    {
        $pivotTable = $teacher->getConnection()->getDriverName() === 'sqlite'
            ? 'professor_disciplinas'
            : 'escola.professor_disciplinas';

        \Log::info('=== SYNC DISCIPLINAS - INÍCIO ===', [
            'teacher_id' => $teacher->id,
            'tenant_id' => $tenant->id,
            'disciplina_ids_recebidos' => $disciplinaIds,
            'pivot_table' => $pivotTable,
        ]);

        // Normalize disciplinas array
        if (empty($disciplinaIds) || ! is_array($disciplinaIds)) {
            $disciplinaIds = [];
        }

        // Filter out empty values and ensure they are strings
        $disciplinaIds = array_values(array_filter(array_map(function ($id) {
            return is_string($id) ? trim($id) : (string) $id;
        }, $disciplinaIds), function ($id) {
            return ! empty($id) && $id !== 'null' && $id !== '0';
        }));

        \Log::info('Disciplinas após normalização:', [
            'disciplina_ids' => $disciplinaIds,
            'count' => count($disciplinaIds),
        ]);

        // Remove all existing disciplinas for this teacher and tenant
        $deleted = DB::connection('shared')
            ->table($pivotTable)
            ->where('tenant_id', $tenant->id)
            ->where('professor_id', $teacher->id)
            ->delete();

        \Log::info('Registros deletados da pivot:', ['count' => $deleted]);

        // Attach new disciplinas
        if (! empty($disciplinaIds)) {
            $insertData = [];
            foreach ($disciplinaIds as $disciplinaId) {
                if (! empty($disciplinaId) && is_string($disciplinaId)) {
                    $insertData[] = [
                        'id' => Str::uuid()->toString(),
                        'tenant_id' => $tenant->id,
                        'professor_id' => $teacher->id,
                        'disciplina_id' => $disciplinaId,
                        'created_at' => now(),
                    ];
                }
            }

            \Log::info('Dados para inserir:', [
                'insert_data' => $insertData,
                'count' => count($insertData),
            ]);

            if (! empty($insertData)) {
                try {
                    DB::connection('shared')
                        ->table($pivotTable)
                        ->insert($insertData);

                    \Log::info('✅ Disciplinas inseridas com sucesso!');
                } catch (\Exception $e) {
                    \Log::error('❌ ERRO ao inserir disciplinas: '.$e->getMessage(), [
                        'teacher_id' => $teacher->id,
                        'tenant_id' => $tenant->id,
                        'disciplina_ids' => $disciplinaIds,
                        'insert_data' => $insertData,
                        'exception' => $e,
                        'trace' => $e->getTraceAsString(),
                    ]);
                    throw $e;
                }
            } else {
                \Log::warning('⚠️ Nenhum dado para inserir após filtrar');
            }
        } else {
            \Log::info('ℹ️ Nenhuma disciplina para vincular (array vazio)');
        }

        \Log::info('=== SYNC DISCIPLINAS - FIM ===');
    }

    /**
     * Remove the specified teacher.
     */
    public function destroy(Teacher $teacher): RedirectResponse
    {
        $tenant = $this->getTenant();

        if ($teacher->tenant_id !== $tenant->id) {
            abort(404);
        }

        $teacher->delete();

        return redirect()
            ->route('school.teachers.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Professor excluído',
                'message' => 'O professor foi removido com sucesso.',
            ]);
    }
}
