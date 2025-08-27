<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;



class User extends Authenticatable 
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens; 
    

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'numero_documento_identidad',
        'last_name',
        'name',
        'cargo',
        'email',
        'telefono',
        'password',
        'role_id',
        'tipo_documento_identidad_id',
        'area_id',
        'jefe_area_id',
        'email_verified_at',
        'activo',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relaciones de Eloquent
    |--------------------------------------------------------------------------
    */
    /**
     * Obtiene el rol al que pertenece el usuario.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Role::class);
    }

    /**
     * Obtiene el tipo de documento de identidad del usuario.
     */
    public function tipoDocumentoIdentidad(): BelongsTo
    {
        return $this->belongsTo(\App\Models\TipoDocumentoIdentidad::class);
    }

    /**
     * Obtiene el área a la que pertenece el usuario.
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Area::class);
    }

    /**
     * Obtiene el jefe de área al que reporta este usuario.
     * Es una relación auto-referenciada.
     */
    public function jefeArea(): BelongsTo
    {
        return $this->belongsTo(User::class, 'jefe_area_id');
    }

    /**
     * Obtiene los colaboradores que reportan a este usuario (si este usuario es un jefe de área).
     * Es la inversa de la relación jefeArea.
     */
    public function colaboradores(): HasMany
    {
        return $this->hasMany(User::class, 'jefe_area_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Métodos de Ayuda para Roles (Personalizados)
    |--------------------------------------------------------------------------
    | Estos métodos asumen que el rol se maneja a través de la relación 'role'
    | y la columna 'name' en la tabla 'roles'.
    | Si se usa Spatie Laravel Permission, estos métodos serían redundantes.
    */

    /**
     * Verifica si el usuario tiene un rol específico.
     * @param string $roleName El nombre del rol a verificar.
     * @return bool
     */
    public function hasRole(string $roleName): bool
    {
        return $this->role?->name === $roleName;
    }
    /**
     * Verifica si el usuario tiene alguno de los roles en una lista.
     * @param array $roles Los nombres de los roles a verificar.
     * @return bool
     */
    public function hasAnyRole(array $roles): bool
    {
        // Si el usuario no tiene un rol asignado, devuelve false.
        if (!$this->role) {
            return false;
        }

        // Comprueba si el nombre del rol del usuario está en el array de roles proporcionado.
        return in_array($this->role->name, $roles);
    }
    
    /**
     * Verifica si el usuario es un jefe de área.
     * @return bool
     */
    public function isJefeArea(): bool
    {
        return $this->hasRole('jefe_area');
    }

    /**
     * Verifica si el usuario es un jefe de administración.
     * @return bool
     */
    public function isJefeAdministracion(): bool
    {
        return $this->hasRole('jefe_administracion');
    }

    /**
     * Verifica si el usuario es un gerente general.
     * @return bool
     */
    public function isGerenteGeneral(): bool
    {
        return $this->hasRole('gerente_general');
    }

    /**
     * Verifica si el usuario es un super administrador.
     * @return bool
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    /**
     * Verifica si el usuario es un colaborador.
     * @return bool
     */
    public function isColaborador(): bool
    {
        return $this->hasRole('colaborador');
    }
    
}