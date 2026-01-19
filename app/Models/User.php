<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, HasUuids, Notifiable, SoftDeletes;

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
    protected $table = 'shared.usuarios';

    /**
     * Get the table name for the model.
     */
    public function getTable(): string
    {
        // Em SQLite (testes), não existe schema. A migration cria a tabela como `usuarios`.
        if ($this->getConnection()->getDriverName() === 'sqlite') {
            return 'usuarios';
        }

        return parent::getTable();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'password_hash',
        'nome_completo',
        'cpf',
        'telefone',
        'avatar_url',
        'ativo',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password_hash',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'last_login_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the full_name attribute (maps to nome_completo).
     */
    public function getFullNameAttribute(): string
    {
        return $this->attributes['nome_completo'] ?? '';
    }

    /**
     * Set the full_name attribute (maps to nome_completo).
     */
    public function setFullNameAttribute(string $value): void
    {
        $this->attributes['nome_completo'] = $value;
    }

    /**
     * Get the name attribute (maps to nome_completo).
     */
    public function getNameAttribute(): string
    {
        return $this->attributes['nome_completo'] ?? '';
    }

    /**
     * Set the name attribute (maps to nome_completo).
     */
    public function setNameAttribute(string $value): void
    {
        $this->attributes['nome_completo'] = $value;
    }

    /**
     * Get the password attribute (maps to password_hash).
     */
    public function getPasswordAttribute(): string
    {
        return $this->attributes['password_hash'] ?? '';
    }

    /**
     * Set the password attribute (maps to password_hash and hashes it).
     */
    public function setPasswordAttribute(string $value): void
    {
        // Only hash if the value is not already hashed (doesn't start with $2y$)
        if (! str_starts_with($value, '$2y$') && ! str_starts_with($value, '$2a$') && ! str_starts_with($value, '$2x$')) {
            $this->attributes['password_hash'] = Hash::make($value);
        } else {
            $this->attributes['password_hash'] = $value;
        }
    }

    /**
     * Get the password for authentication.
     */
    public function getAuthPassword(): string
    {
        return $this->attributes['password_hash'] ?? '';
    }

    /**
     * Get the phone attribute (maps to telefone).
     */
    public function getPhoneAttribute(): ?string
    {
        return $this->attributes['telefone'] ?? null;
    }

    /**
     * Set the phone attribute (maps to telefone).
     */
    public function setPhoneAttribute(?string $value): void
    {
        $this->attributes['telefone'] = $value;
    }

    /**
     * Get the is_active attribute (maps to ativo).
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->attributes['ativo'] ?? false;
    }

    /**
     * Set the is_active attribute (maps to ativo).
     */
    public function setIsActiveAttribute(bool $value): void
    {
        $this->attributes['ativo'] = $value;
    }

    /**
     * Get the tenants that belong to the user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function tenants()
    {
        $pivotTable = $this->getConnection()->getDriverName() === 'sqlite'
            ? 'usuario_tenants'
            : 'shared.usuario_tenants';

        return $this->belongsToMany(
            Tenant::class,
            $pivotTable,
            'usuario_id',
            'tenant_id'
        )->withPivot('created_at');
    }

    /**
     * Get the first tenant (for backward compatibility).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function tenant()
    {
        return $this->tenants();
    }

    /**
     * Get the teacher relationship.
     */
    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class, 'usuario_id');
    }

    /**
     * Get the responsavel (parent/guardian) relationship.
     */
    public function responsavel(): HasOne
    {
        return $this->hasOne(Responsavel::class, 'usuario_id');
    }

    /**
     * Check if the user is a teacher.
     */
    public function isTeacher(): bool
    {
        return $this->teacher()->exists();
    }

    /**
     * Check if the user is a responsavel (parent/guardian).
     */
    public function isResponsavel(): bool
    {
        return $this->responsavel()->exists();
    }

    /**
     * Check if the user can access the mobile API (is teacher or responsavel).
     */
    public function canAccessMobileApi(): bool
    {
        return $this->isTeacher() || $this->isResponsavel();
    }
}
