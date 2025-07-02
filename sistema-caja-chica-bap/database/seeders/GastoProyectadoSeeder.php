<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CuentaContable;
use App\Models\GastoProyectado;
use Illuminate\Support\Facades\DB;

class GastoProyectadoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Desactivar revisión de claves foráneas para una limpieza segura.
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        GastoProyectado::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Obtener todas las cuentas contables existentes.
        $cuentasContables = CuentaContable::all();

        if ($cuentasContables->isEmpty()) {
            $this->command->info('No se encontraron Cuentas Contables. Por favor, ejecuta primero el CuentaContableSeeder.');
            return;
        }

        // Crear un Gasto Proyectado para cada Cuenta Contable.
        // La descripción del Gasto Proyectado será la misma que la de la Cuenta Contable.
        foreach ($cuentasContables as $cuenta) {
            GastoProyectado::create([
                'descripcion' => $cuenta->descripcion,
                'id_cuenta_contable' => $cuenta->id,
                'activo' => true,
            ]);
        }

        $this->command->info('Catálogo de Gastos Proyectados creado exitosamente a partir de las Cuentas Contables.');
    }
}
