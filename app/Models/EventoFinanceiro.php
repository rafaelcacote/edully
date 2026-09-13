<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class EventoFinanceiro extends Model
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
    protected $table = 'escola.eventos_financeiros';

    public function getTable(): string
    {
        if ($this->getConnection()->getDriverName() === 'sqlite') {
            return 'eventos_financeiros';
        }

        return parent::getTable();
    }

    protected function alunosPivotTable(): string
    {
        return $this->getConnection()->getDriverName() === 'sqlite'
            ? 'evento_financeiro_alunos'
            : 'escola.evento_financeiro_alunos';
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'titulo',
        'descricao',
        'valor',
        'vencimento',
        'publico',
        'turma_id',
        'status',
        'pix_copia_cola',
        'pix_chave',
        'pix_qrcode_url',
        'boleto_url',
        'publicado_em',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'vencimento' => 'date',
            'publicado_em' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class, 'turma_id');
    }

    public function cobrancas(): HasMany
    {
        return $this->hasMany(Cobranca::class, 'evento_financeiro_id');
    }

    public function alunos(): BelongsToMany
    {
        return $this->belongsToMany(
            Student::class,
            $this->alunosPivotTable(),
            'evento_financeiro_id',
            'aluno_id'
        );
    }

    /**
     * Sync selected students for publico=alunos.
     *
     * @param  list<string>  $alunoIds
     */
    public function syncAlunosSelecionados(array $alunoIds): void
    {
        $table = $this->alunosPivotTable();
        $connection = $this->getConnection();

        $connection->table($table)
            ->where('evento_financeiro_id', $this->id)
            ->delete();

        $now = now();

        foreach (array_unique($alunoIds) as $alunoId) {
            $connection->table($table)->insert([
                'id' => (string) Str::uuid(),
                'evento_financeiro_id' => $this->id,
                'aluno_id' => $alunoId,
                'created_at' => $now,
            ]);
        }
    }
}
