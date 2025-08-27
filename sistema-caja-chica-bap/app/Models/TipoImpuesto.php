<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoImpuesto extends Model
{
    use HasFactory;

    protected $table = 'tipos_impuesto';
    protected $primaryKey = 'id_tipo_impuesto';

    protected $fillable = [
        'nombre',
        'porcentaje',
        'factor_calculo',
        'activo',
    ];

    /**
     * Define la relación: Un tipo de impuesto puede estar en muchos Gastos Proyectados.
     */
    public function gastosProyectados(): HasMany
    {
        return $this->hasMany(GastoProyectado::class, 'tipo_impuesto_id', 'id_tipo_impuesto');
    }
}
