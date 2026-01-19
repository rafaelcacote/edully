<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
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
    protected $table = 'laravel.personal_access_tokens';

    /**
     * Get the table name for the model.
     */
    public function getTable(): string
    {
        // Em SQLite (testes), não existe schema. A migration cria a tabela como `personal_access_tokens`.
        if ($this->getConnection()->getDriverName() === 'sqlite') {
            return 'personal_access_tokens';
        }

        return parent::getTable();
    }
}
