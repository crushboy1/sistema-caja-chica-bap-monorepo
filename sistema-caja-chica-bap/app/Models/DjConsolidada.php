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

    /**
     * La tabla asociada con el modelo.
     *
     * @var string
     */
    protected $table = 'djs_consolidadas';

    /**
     * La clave primaria para el modelo.
     *
     * @var string
     */
    protected $primaryKey = 'id_dj_consolidada';

    /**
     * Los atributos que son asignables en masa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'ruta_documento',
        'id_uploader',
    ];

    /**
     * Los "accessors" para añadir al array del modelo.
     *
     * @var array
     */
    protected $appends = ['documento_url'];


    /*
    |--------------------------------------------------------------------------
    | RELACIONES DE ELOQUENT
    |--------------------------------------------------------------------------
    */

    /**
     * Una DJ consolidada puede tener muchos gastos asociados.
     */
    public function gastos(): HasMany
    {
        // Una DjConsolidada tiene muchos Gastos. La clave foránea en la tabla 'gastos' es 'id_dj_consolidada'.
        return $this->hasMany(Gasto::class, 'id_dj_consolidada', 'id_dj_consolidada');
    }

    /**
     * La DJ consolidada fue subida por un usuario.
     */
    public function uploader(): BelongsTo
    {
        // Una DjConsolidada pertenece a un User. La clave foránea aquí es 'id_uploader'.
        return $this->belongsTo(User::class, 'id_uploader');
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS & MUTATORS
    |--------------------------------------------------------------------------
    */

    /**
     * Obtiene la URL completa del documento de la DJ.
     *
     * @return string|null
     */
    public function getDocumentoUrlAttribute(): ?string
    {
        // Chequeo para asegurar que el campo no esté vacío
        // y se ha tipado el retorno para mejorar la predicción de código.
        if ($this->ruta_documento && Storage::disk('public')->exists($this->ruta_documento)) {
            // Devuelve la URL pública completa del archivo almacenado.
            return Storage::url($this->ruta_documento);
        }
        return null;
    }
}
