<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasUuids, Notifiable, SoftDeletes;

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
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'password_hash',
        'full_name',
        'cpf',
        'role',
        'phone',
        'avatar_url',
        'is_active',
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
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the name attribute (maps to full_name).
     *
     * @return string
     */
    public function getNameAttribute(): string
    {
        return $this->attributes['full_name'] ?? '';
    }

    /**
     * Set the name attribute (maps to full_name).
     *
     * @param  string  $value
     * @return void
     */
    public function setNameAttribute(string $value): void
    {
        $this->attributes['full_name'] = $value;
    }

    /**
     * Get the password attribute (maps to password_hash).
     *
     * @return string
     */
    public function getPasswordAttribute(): string
    {
        return $this->attributes['password_hash'] ?? '';
    }

    /**
     * Set the password attribute (maps to password_hash and hashes it).
     *
     * @param  string  $value
     * @return void
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
     *
     * @return string
     */
    public function getAuthPassword(): string
    {
        return $this->attributes['password_hash'] ?? '';
    }
}
