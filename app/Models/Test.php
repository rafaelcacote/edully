<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Test extends Model
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
    protected $table = 'escola.provas';

    public function getTable(): string
    {
        // Em SQLite (testes), não existe schema. A migration cria a tabela como `provas`.
        if ($this->getConnection()->getDriverName() === 'sqlite') {
            return 'provas';
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
        'turma_id',
        'disciplina_id',
        'titulo',
        'descricao',
        'data_prova',
        'horario',
        'sala',
        'duracao_minutos',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data_prova' => 'date',
            'duracao_minutos' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the tenant that owns the test.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the teacher that created the test.
     */
    public function professor(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'professor_id');
    }

    /**
     * Get the class (turma) for which the test was created.
     */
    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class, 'turma_id');
    }

    /**
     * Get the discipline (disciplina) for which the test was created.
     */
    public function disciplinaRelation(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class, 'disciplina_id');
    }
}
