<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Nota extends Model
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
    protected $table = 'escola.notas';

    public function getTable(): string
    {
        // Em SQLite (testes), não existe schema. A migration cria a tabela como `notas`.
        if ($this->getConnection()->getDriverName() === 'sqlite') {
            return 'notas';
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
        'aluno_id',
        'professor_id',
        'turma_id',
        'disciplina',
        'disciplina_id',
        'trimestre',
        'nota',
        'frequencia',
        'comportamento',
        'observacoes',
        'ano_letivo',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trimestre' => 'integer',
            'nota' => 'decimal:1',
            'frequencia' => 'integer',
            'ano_letivo' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the tenant that owns the nota.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the student (aluno) that owns the nota.
     */
    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'aluno_id');
    }

    /**
     * Get the teacher (professor) that owns the nota.
     */
    public function professor(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'professor_id');
    }

    /**
     * Get the class (turma) that owns the nota.
     */
    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class, 'turma_id');
    }

    /**
     * Get the discipline (disciplina) that owns the nota.
     */
    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class, 'disciplina_id');
    }
}
