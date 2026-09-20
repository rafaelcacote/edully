<?php

namespace App\Models;

use App\Enums\CategoriaDeclaracao;
use App\Enums\StatusDocumento;
use App\Enums\TipoDocumento;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Documento extends Model
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
    protected $table = 'escola.documentos';

    public function getTable(): string
    {
        // Em SQLite (testes), não existe schema. A migration cria a tabela como `documentos`.
        if ($this->getConnection()->getDriverName() === 'sqlite') {
            return 'documentos';
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
        'criado_por',
        'tipo',
        'status',
        'titulo',
        'descricao',
        'data_inicio',
        'data_fim',
        'categoria_declaracao',
        'anexo_url',
        'anexo_resposta_url',
        'motivo_recusa',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoDocumento::class,
            'status' => StatusDocumento::class,
            'categoria_declaracao' => CategoriaDeclaracao::class,
            'data_inicio' => 'date',
            'data_fim' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the tenant that owns the document.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the student related to the document.
     */
    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'aluno_id');
    }

    /**
     * Get the user who created the document.
     */
    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }

    /**
     * Atestados/pedidos que a secretaria ainda precisa analisar.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Documento>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Documento>
     */
    public function scopeNeedsSchoolAttention($query)
    {
        return $query
            ->whereIn('tipo', TipoDocumento::schoolAttentionValues())
            ->whereIn('status', StatusDocumento::attentionValues());
    }

    public function needsSchoolAttention(): bool
    {
        $tipo = $this->tipo instanceof TipoDocumento ? $this->tipo->value : (string) $this->tipo;
        $status = $this->status instanceof StatusDocumento ? $this->status->value : (string) $this->status;

        return in_array($tipo, TipoDocumento::schoolAttentionValues(), true)
            && in_array($status, StatusDocumento::attentionValues(), true);
    }
}
