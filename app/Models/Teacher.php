<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Teacher extends Model
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
    protected $table = 'escola.professores';

    public function getTable(): string
    {
        // Em SQLite (testes), não existe schema. A migration cria a tabela como `professores`.
        if ($this->getConnection()->getDriverName() === 'sqlite') {
            return 'professores';
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
        'usuario_id',
        'matricula',
        'especializacao',
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
            'ativo' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the pivot table name for the professor_disciplinas relationship.
     */
    protected function professorDisciplinasPivotTable(): string
    {
        return $this->getConnection()->getDriverName() === 'sqlite'
            ? 'professor_disciplinas'
            : 'escola.professor_disciplinas';
    }

    /**
     * Get the disciplinas (disciplines) for the teacher.
     */
    public function disciplinas(): BelongsToMany
    {
        $pivotTable = $this->professorDisciplinasPivotTable();

        return $this->belongsToMany(
            Disciplina::class,
            $pivotTable,
            'professor_id',
            'disciplina_id'
        )
            ->withPivot(['tenant_id', 'created_at'])
            ->wherePivot('tenant_id', $this->tenant_id);
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
     * Get the turmas (classes) for the teacher.
     */
    public function turmas(): BelongsToMany
    {
        $pivotTable = $this->professorTurmaPivotTable();

        return $this->belongsToMany(
            Turma::class,
            $pivotTable,
            'professor_id',
            'turma_id'
        )
            ->withPivot(['tenant_id', 'created_at'])
            ->wherePivot('tenant_id', $this->tenant_id);
    }

    /**
     * Get the tenant that owns the teacher.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the user associated with the teacher.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
