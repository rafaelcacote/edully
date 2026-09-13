<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushToken extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $connection = 'shared';

    protected $table = 'shared.push_tokens';

    protected $fillable = [
        'usuario_id',
        'push_token',
        'platform',
        'tenant_id',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        if ($this->getConnection()->getDriverName() === 'sqlite') {
            return 'push_tokens';
        }

        return parent::getTable();
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
