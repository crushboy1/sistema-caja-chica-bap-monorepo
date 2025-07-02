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
        'nombre_proyecto',
        'descripcion',
        'presupuesto',
        'fecha_inicio',
        'fecha_fin',
        'activo',
    ];

    /**
     * Las áreas que participan en este proyecto.
     */
    public function areas(): BelongsToMany
    {
        return $this->belongsToMany(Area::class, 'area_proyecto', 'id_proyecto', 'id_area');
    }

    /**
     * Los fondos de efectivo que han sido creados para este proyecto.
     */
    public function fondosEfectivo(): HasMany
    {
        return $this->hasMany(FondoEfectivo::class, 'id_proyecto', 'id_proyecto');
    }
}