<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Turma extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The connection name for the model.
     *
     * @var string|null
     */
    protected $connection = 'shared';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'escola.turmas';

    public function getTable(): string
    {
        // Em SQLite (testes), não existe schema. A migration cria a tabela como `turmas`.
        if ($this->getConnection()->getDriverName() === 'sqlite') {
            return 'turmas';
        }

        return parent::getTable();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'professor_id',
        'nome',
        'serie',
        'turma_letra',
        'capacidade',
        'ano_letivo',
        'ativo',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacidade' => 'integer',
            'ano_letivo' => 'integer',
            'ativo' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the tenant that owns the class.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the teacher responsible for the class (legacy - single teacher).
     */
    public function professor(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'professor_id');
    }

    /**
     * Get the pivot table name for the professor_turma relationship.
     */
    protected function professorTurmaPivotTable(): string
    {
        return $this->getConnection()->getDriverName() === 'sqlite'
            ? 'professor_turma'
            : 'escola.professor_turma';
    }

    /**
     * Get the teachers (professores) for the class.
     */
    public function professores(): BelongsToMany
    {
        $pivotTable = $this->professorTurmaPivotTable();

        return $this->belongsToMany(
            Teacher::class,
            $pivotTable,
            'turma_id',
            'professor_id'
        )
            ->withPivot(['tenant_id', 'created_at'])
            ->wherePivot('tenant_id', $this->tenant_id);
    }

    protected function matriculasTurmaPivotTable(): string
    {
        return $this->getConnection()->getDriverName() === 'sqlite'
            ? 'matriculas_turma'
            : 'escola.matriculas_turma';
    }

    /**
     * Get the students (alunos) for the class.
     */
    public function alunos(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, $this->matriculasTurmaPivotTable(), 'turma_id', 'aluno_id')
            ->withPivot(['tenant_id', 'data_matricula', 'status'])
            ->wherePivot('tenant_id', $this->tenant_id)
            ->wherePivot('status', 'ativo');
    }
}
