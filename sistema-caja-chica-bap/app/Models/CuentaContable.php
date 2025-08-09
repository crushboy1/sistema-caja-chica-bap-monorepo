<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany; 
use App\Traits\LogsActivity;
class CuentaContable extends Model
{
    use HasFactory, LogsActivity;
    protected $table = 'cuentas_contables';
    protected $fillable = [
        'codigo_cuenta',
        'descripcion',
        'activo',
    ];

    /**
     * Una cuenta contable puede estar asociada a muchos gastos.
     */
    public function gastos(): HasMany
    {
        return $this->hasMany(Gasto::class, 'id_cuenta_contable');
    }

    /**
     * Relación: Una cuenta contable puede tener muchos gastos proyectados asociados.
     */
    public function gastosProyectados(): HasMany
    {
        return $this->hasMany(GastoProyectado::class, 'id_cuenta_contable', 'id');
    }
}
