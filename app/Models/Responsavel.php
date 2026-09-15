<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Responsavel extends Model
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
    protected $table = 'escola.responsaveis';

    public function getTable(): string
    {
        // Em SQLite (testes), não existe schema. A migration cria a tabela como `responsaveis`.
        if ($this->getConnection()->getDriverName() === 'sqlite') {
            return 'responsaveis';
        }

        return parent::getTable();
    }

    protected function alunoResponsavelPivotTable(): string
    {
        return $this->getConnection()->getDriverName() === 'sqlite'
            ? 'aluno_responsavel'
            : 'escola.aluno_responsavel';
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'usuario_id',
        'cpf',
        'parentesco',
        'profissao',
        'data_nascimento',
        'observacoes',
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
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the tenant that owns the parent.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the user (usuário) that owns the parent.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Get the students (alunos) for the parent.
     */
    public function students(): BelongsToMany
    {
        $relation = $this->belongsToMany(Student::class, $this->alunoResponsavelPivotTable(), 'responsavel_id', 'aluno_id')
            ->withPivot(['tenant_id', 'principal']);

        // Aplicar filtro de tenant_id apenas se estiver disponível
        if ($this->tenant_id) {
            $relation->wherePivot('tenant_id', $this->tenant_id);
        }

        return $relation;
    }

    /**
     * Get the nome_completo attribute from the user.
     */
    public function getNomeCompletoAttribute(): ?string
    {
        return $this->user?->nome_completo;
    }

    /**
     * Get the email attribute from the user.
     */
    public function getEmailAttribute(): ?string
    {
        return $this->user?->email;
    }

    /**
     * Get the telefone attribute from the user.
     */
    public function getTelefoneAttribute(): ?string
    {
        return $this->user?->telefone;
    }

    /**
     * Get the ativo attribute from the user.
     */
    public function getAtivoAttribute(): bool
    {
        return $this->user?->ativo ?? false;
    }
}
