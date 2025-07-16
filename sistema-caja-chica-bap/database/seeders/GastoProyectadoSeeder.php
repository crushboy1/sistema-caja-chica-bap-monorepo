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
        // Limpieza segura de la tabla
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        GastoProyectado::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Mapeo de las descripciones de gastos proyectados a sus códigos de cuenta contable
        $gastos = [
            ['descripcion' => 'Gastos por eventos de agasajos al personal BAP', 'codigo_cuenta' => '62511001'],
            ['descripcion' => 'Gastos por servicio de Movilidad', 'codigo_cuenta' => '63112001'],
            ['descripcion' => 'Gastos por alojamiento en hoteles', 'codigo_cuenta' => '63131001'],
            ['descripcion' => 'Gastos por Alimentación de personal', 'codigo_cuenta' => '63141001'],
            ['descripcion' => 'Gastos de alquiler', 'codigo_cuenta' => '63551001'],
            ['descripcion' => 'Devolución de recargas', 'codigo_cuenta' => '63641001'],
            ['descripcion' => 'Gastos por lavado de mandiles', 'codigo_cuenta' => '63991001'],
            ['descripcion' => 'Gastos por compra de accesorios tecnológicos', 'codigo_cuenta' => '65611001'],
            ['descripcion' => 'Gastos por artículos de librería', 'codigo_cuenta' => '65612001'],
            ['descripcion' => 'Gastos por artículos de limpieza', 'codigo_cuenta' => '65613001'],
            ['descripcion' => 'Gastos por peajes', 'codigo_cuenta' => '63113001'],
            ['descripcion' => 'Gastos por compras de otros conceptos no especificados', 'codigo_cuenta' => '65941002'],
            ['descripcion' => 'Gastos de impresión', 'codigo_cuenta' => '65941003'],
            ['descripcion' => 'Gastos de refrigerio', 'codigo_cuenta' => '65941004'],
            ['descripcion' => 'Gastos por bocaditos', 'codigo_cuenta' => '65941005'],
            ['descripcion' => 'Gastos por movilidad de pasaje', 'codigo_cuenta' => '65942001'],
            ['descripcion' => 'Gastos de gasolina', 'codigo_cuenta' => '65951002'],
        ];

        foreach ($gastos as $gasto) {
            // Busca la cuenta contable por su código para obtener el ID
            $cuentaContable = CuentaContable::where('codigo_cuenta', $gasto['codigo_cuenta'])->first();

            if ($cuentaContable) {
                GastoProyectado::create([
                    'descripcion' => $gasto['descripcion'],
                    'id_cuenta_contable' => $cuentaContable->id,
                    'activo' => true,
                ]);
            } else {
                $this->command->error("No se encontró la cuenta contable con código: {$gasto['codigo_cuenta']}. El gasto '{$gasto['descripcion']}' no fue creado.");
            }
        }

        $this->command->info('Catálogo de Gastos Proyectados actualizado y vinculado exitosamente.');
    }
}
