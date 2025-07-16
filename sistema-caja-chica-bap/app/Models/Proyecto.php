<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proyecto extends Model
{
    use HasFactory;

    protected $table = 'proyectos';
    protected $primaryKey = 'id_proyecto';

    protected $fillable = [
        'codigo',
        'nombre',
        'activo',
    ];

    public function areas(): BelongsToMany
    {
        return $this->belongsToMany(Area::class, 'area_proyecto', 'id_proyecto', 'id_area');
    }

    public function fondosEfectivo(): HasMany
    {
        return $this->hasMany(FondoEfectivo::class, 'id_proyecto', 'id_proyecto');
    }
}
