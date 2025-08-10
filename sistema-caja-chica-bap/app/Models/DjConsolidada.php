<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DjConsolidada extends Model
{
    use HasFactory;

    protected $table = 'djs_consolidadas';
    protected $primaryKey = 'id_dj_consolidada';

    /**
     * ATRIBUTOS ASIGNABLES
     * Se han añadido los campos necesarios para que la DJ tenga contexto.
     */
    protected $fillable = [
        'codigo_dj', 
        'fondo_efectivo_id', 
        'fecha_declaracion', 
        'monto_total_declarado', 
        'estado', 
        'creado_por', 
        'ruta_documento_firmado', 
        'id_uploader_firmado',
    ];

    /**
     * CASTS
     * Para asegurar que los tipos de datos sean correctos.
     */
    protected $casts = [
        'fecha_declaracion' => 'datetime',
        'monto_total_declarado' => 'decimal:2',
    ];

    /**
     * ACCESSORS
     * Añadimos un accesor para el documento firmado.
     */
    protected $appends = ['documento_firmado_url'];


    /*
    |--------------------------------------------------------------------------
    | RELACIONES DE ELOQUENT (¡MUY IMPORTANTE!)
    |--------------------------------------------------------------------------
    */

    /**
     * Una DJ consolidada pertenece a un Fondo de Efectivo.
     */
    public function fondoEfectivo(): BelongsTo
    {
        return $this->belongsTo(FondoEfectivo::class, 'fondo_efectivo_id');
    }

    /**
     * Una DJ consolidada tiene muchos gastos asociados.
     */
    public function gastos(): HasMany
    {
        return $this->hasMany(Gasto::class, 'id_dj_consolidada', 'id_dj_consolidada');
    }

    /**
     * La DJ fue creada por un usuario (EL DECLARANTE).
     * Esta es la relación que usaremos para obtener el DNI y nombre en el PDF.
     */
    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    /**
     * La DJ firmada fue subida por un usuario.
     */
    public function uploaderFirmado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_uploader_firmado');
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS & MUTATORS
    |--------------------------------------------------------------------------
    */

    /**
     * Obtiene la URL completa del documento FIRMADO de la DJ.
     */
    public function getDocumentoFirmadoUrlAttribute(): ?string
    {
        if ($this->ruta_documento_firmado && Storage::disk('public')->exists($this->ruta_documento_firmado)) {
            return Storage::url($this->ruta_documento_firmado);
        }
        return null;
    }
}
