<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\LogsActivity;
class TipoDocumentoComprobante extends Model
{
    use HasFactory, LogsActivity;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tipos_documento_comprobante';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nombre',
        'codigo_comprobante',
        'activo',
    ];

    /**
     * Define la relación "uno a muchos" con el modelo Gasto.
     * Un tipo de documento puede estar asociado a muchos gastos.
     */
    public function gastos(): HasMany
    {
        return $this->hasMany(Gasto::class, 'id_tipo_documento_comprobante');
    }
}
