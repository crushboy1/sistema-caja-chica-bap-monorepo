<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExcepcionCierre extends Model
{
    use HasFactory;

    /**
     * La tabla asociada con el modelo.
     *
     * @var string
     */
    protected $table = 'excepciones_cierre';

    /**
     * Los atributos que son asignables en masa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id_cierre_mensual',
        'id_usuario_excepcion',
        'id_usuario_otorga',
        'fecha_expiracion',
        'motivo',
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'fecha_expiracion' => 'date',
    ];

    /**
     * Relación: Una excepción pertenece a un cierre mensual específico.
     */
    public function cierreMensual(): BelongsTo
    {
        return $this->belongsTo(CierreMensual::class, 'id_cierre_mensual');
    }

    /**
     * Relación: El usuario que recibe la excepción.
     */
    public function usuarioExcepcion(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario_excepcion');
    }

    /**
     * Relación: El administrador que otorga la excepción.
     */
    public function usuarioOtorga(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario_otorga');
    }
}
