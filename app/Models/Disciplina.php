<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Disciplina extends Model
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
    protected $table = 'escola.disciplinas';

    public function getTable(): string
    {
        // Em SQLite (testes), não existe schema. A migration cria a tabela como `disciplinas`.
        if ($this->getConnection()->getDriverName() === 'sqlite') {
            return 'disciplinas';
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
        'nome',
        'sigla',
        'descricao',
        'carga_horaria_semanal',
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
            'carga_horaria_semanal' => 'integer',
            'ativo' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the tenant that owns the discipline.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
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
     * Get the professores (teachers) for the discipline.
     */
    public function professores(): BelongsToMany
    {
        $pivotTable = $this->professorDisciplinasPivotTable();

        return $this->belongsToMany(
            Teacher::class,
            $pivotTable,
            'disciplina_id',
            'professor_id'
        )
            ->withPivot(['tenant_id', 'created_at'])
            ->when($this->tenant_id, function ($query) {
                $query->wherePivot('tenant_id', $this->tenant_id);
            });
    }
}
