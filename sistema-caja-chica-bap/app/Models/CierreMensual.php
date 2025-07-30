<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CierreMensual extends Model
{
    use HasFactory;

    /**
     * La tabla asociada con el modelo.
     *
     * @var string
     */
    protected $table = 'cierres_mensuales';

    /**
     * Los atributos que son asignables en masa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'periodo',
        'estado',
        'id_usuario_accion',
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'periodo' => 'date',
    ];

    /**
     * Relación: Un cierre mensual tiene muchas excepciones.
     */
    public function excepciones(): HasMany
    {
        return $this->hasMany(ExcepcionCierre::class, 'id_cierre_mensual');
    }

    /**
     * Relación: El usuario que realizó la última acción sobre el cierre.
     */
    public function usuarioAccion(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario_accion');
    }
}
