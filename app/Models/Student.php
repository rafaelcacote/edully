<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
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
    protected $table = 'escola.alunos';

    public function getTable(): string
    {
        // Em SQLite (testes), não existe schema. A migration cria a tabela como `alunos`.
        if ($this->getConnection()->getDriverName() === 'sqlite') {
            return 'alunos';
        }

        return parent::getTable();
    }

    protected function alunoResponsavelPivotTable(): string
    {
        return $this->getConnection()->getDriverName() === 'sqlite'
            ? 'aluno_responsavel'
            : 'escola.aluno_responsavel';
    }

    protected function matriculasTurmaPivotTable(): string
    {
        return $this->getConnection()->getDriverName() === 'sqlite'
            ? 'matriculas_turma'
            : 'escola.matriculas_turma';
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'nome',
        'nome_social',
        'foto_url',
        'data_nascimento',
        'informacoes_medicas',
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
            'data_nascimento' => 'date',
            'ativo' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the tenant that owns the student.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the parents (responsáveis) for the student.
     */
    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(Responsavel::class, $this->alunoResponsavelPivotTable(), 'aluno_id', 'responsavel_id')
            ->withPivot(['tenant_id', 'principal'])
            ->wherePivot('tenant_id', $this->tenant_id);
    }

    /**
     * Get the classes (turmas) for the student.
     */
    public function turmas(): BelongsToMany
    {
        return $this->belongsToMany(Turma::class, $this->matriculasTurmaPivotTable(), 'aluno_id', 'turma_id')
            ->withPivot(['tenant_id', 'data_matricula', 'status'])
            ->wherePivot('tenant_id', $this->tenant_id)
            ->wherePivot('status', 'ativo');
    }

    /**
     * Get the charges (cobranças) for the student.
     */
    public function cobrancas(): HasMany
    {
        return $this->hasMany(Cobranca::class, 'aluno_id');
    }
}
