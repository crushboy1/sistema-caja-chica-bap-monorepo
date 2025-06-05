<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany; // ¡Importar HasMany!

class Role extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'display_name', 'description'];

    /**
     * Los permisos que pertenecen al rol.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_role');
    }

    /**
     * Los usuarios que tienen este rol.
     * Un rol puede tener muchos usuarios.
     */
    public function users(): HasMany // Define la relación uno a muchos (inversa) con User
    {
        return $this->hasMany(User::class);
    }
}
