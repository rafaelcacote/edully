<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
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
    protected $table = 'shared.tenants';

    /**
     * Get the table name for the model.
     */
    public function getTable(): string
    {
        // Em SQLite (testes), não existe schema. A migration cria a tabela como `tenants`.
        if ($this->getConnection()->getDriverName() === 'sqlite') {
            return 'tenants';
        }

        return parent::getTable();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nome',
        'subdominio',
        'cnpj',
        'email',
        'telefone',
        'endereco',
        'endereco_numero',
        'endereco_complemento',
        'endereco_bairro',
        'endereco_cep',
        'endereco_cidade',
        'endereco_estado',
        'endereco_pais',
        'logo_url',
        'plano_id',
        'ativo',
        'trial_ate',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'name',
        'subdomain',
        'phone',
        'address',
        'is_active',
        'trial_until',
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
            'trial_ate' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the name attribute (maps to nome).
     */
    public function getNameAttribute(): string
    {
        return $this->attributes['nome'] ?? '';
    }

    /**
     * Set the name attribute (maps to nome).
     */
    public function setNameAttribute(string $value): void
    {
        $this->attributes['nome'] = $value;
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
     * Get the address attribute (maps to endereco).
     */
    public function getAddressAttribute(): ?string
    {
        return $this->attributes['endereco'] ?? null;
    }

    /**
     * Set the address attribute (maps to endereco).
     */
    public function setAddressAttribute(?string $value): void
    {
        $this->attributes['endereco'] = $value;
    }

    /**
     * Get the logo_url attribute.
     */
    public function getLogoUrlAttribute(): ?string
    {
        $value = $this->attributes['logo_url'] ?? null;

        if (! $value) {
            return null;
        }

        if (str_starts_with($value, 'storage/')) {
            return '/'.$value;
        }

        $storagePosition = strpos($value, '/storage/');
        if ($storagePosition !== false) {
            return substr($value, $storagePosition);
        }

        return $value;
    }

    /**
     * Set the logo_url attribute.
     */
    public function setLogoUrlAttribute(?string $value): void
    {
        $this->attributes['logo_url'] = $value;
    }

    /**
     * Get the subdomain attribute (maps to subdominio).
     */
    public function getSubdomainAttribute(): ?string
    {
        return $this->attributes['subdominio'] ?? null;
    }

    /**
     * Set the subdomain attribute (maps to subdominio).
     */
    public function setSubdomainAttribute(?string $value): void
    {
        $this->attributes['subdominio'] = $value;
    }

    /**
     * Get the trial_until attribute (maps to trial_ate).
     */
    public function getTrialUntilAttribute(): ?\Illuminate\Support\Carbon
    {
        return $this->trial_ate;
    }

    /**
     * Set the trial_until attribute (maps to trial_ate).
     *
     * @param  \DateTimeInterface|string|null  $value
     */
    public function setTrialUntilAttribute($value): void
    {
        $this->attributes['trial_ate'] = $value;
    }
}
