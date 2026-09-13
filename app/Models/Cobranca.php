<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cobranca extends Model
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
    protected $table = 'escola.cobrancas';

    public function getTable(): string
    {
        if ($this->getConnection()->getDriverName() === 'sqlite') {
            return 'cobrancas';
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
        'tipo',
        'evento_financeiro_id',
        'titulo',
        'descricao',
        'referencia',
        'valor',
        'vencimento',
        'status',
        'pago_em',
        'pago_observacao',
        'boleto_url',
        'pix_copia_cola',
        'pix_chave',
        'pix_qrcode_url',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'esta_atrasada',
        'status_exibicao',
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
            'pago_em' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'aluno_id');
    }

    public function eventoFinanceiro(): BelongsTo
    {
        return $this->belongsTo(EventoFinanceiro::class, 'evento_financeiro_id');
    }

    public function getEstaAtrasadaAttribute(): bool
    {
        return $this->status === 'pendente'
            && $this->vencimento !== null
            && $this->vencimento->isPast()
            && ! $this->vencimento->isToday();
    }

    public function getStatusExibicaoAttribute(): string
    {
        if ($this->esta_atrasada) {
            return 'atrasado';
        }

        return $this->status;
    }

    public function scopePendentes(Builder $query): Builder
    {
        return $query->where('status', 'pendente');
    }

    public function scopePagas(Builder $query): Builder
    {
        return $query->where('status', 'pago');
    }

    public function markAsPaid(?string $observacao = null): void
    {
        $this->status = 'pago';
        $this->pago_em = now();
        $this->pago_observacao = $observacao;
        $this->save();
    }

    public function markAsCancelled(?string $observacao = null): void
    {
        $this->status = 'cancelado';
        $this->pago_observacao = $observacao;
        $this->save();
    }
}
