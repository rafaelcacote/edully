<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
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
    protected $table = 'escola.mensagens';

    public function getTable(): string
    {
        // Em SQLite (testes), não existe schema. A migration cria a tabela como `mensagens`.
        if ($this->getConnection()->getDriverName() === 'sqlite') {
            return 'mensagens';
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
        'remetente_id',
        'aluno_id',
        'turma_id',
        'titulo',
        'conteudo',
        'tipo',
        'prioridade',
        'anexo_url',
        'lida',
        'lida_em',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lida' => 'boolean',
            'lida_em' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the tenant that owns the message.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the sender (remetente) of the message.
     */
    public function remetente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'remetente_id');
    }

    /**
     * Get the student (aluno) that received the message.
     */
    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'aluno_id');
    }

    /**
     * Get the turma that the message was sent to.
     */
    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class, 'turma_id');
    }
}
