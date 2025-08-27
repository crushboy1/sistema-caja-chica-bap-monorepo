<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClasificacionBienServicio extends Model
{
    use HasFactory;

    protected $table = 'clasificaciones_bien_servicio';
    protected $primaryKey = 'id_clasificacion_bien_servicio';

    protected $fillable = [
        'nombre',
        'codigo',
        'activo',
    ];

    /**
     * Define la relación: Una clasificación puede estar en muchos Gastos Proyectados.
     */
    public function gastosProyectados(): HasMany
    {
        return $this->hasMany(GastoProyectado::class, 'clasificacion_bien_servicio_id', 'id_clasificacion_bien_servicio');
    }
}
