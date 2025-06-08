<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CuentaContable extends Model
{
    use HasFactory;

    /**
     * El nombre de la tabla asociada con el modelo.
     *
     * @var string
     */
    protected $table = 'cuentas_contables';

    /**
     * Los atributos que se pueden asignar masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'codigo_cuenta',
        'descripcion',
        'activo',
    ];

    /**
     * Define la relación "uno a muchos" con el modelo Gasto.
     * Una cuenta contable puede estar asociada a muchos gastos.
     */
    public function gastos()
    {
        return $this->hasMany(Gasto::class, 'id_cuenta_contable');
    }
}
