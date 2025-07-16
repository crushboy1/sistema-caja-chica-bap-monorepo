<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CuentaContableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        // Desactiva temporalmente la revisión de claves foráneas para evitar problemas de orden
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        // Vacía la tabla para evitar duplicados si se ejecuta el seeder varias veces
        DB::table('cuentas_contables')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $cuentas = [
            ['codigo_cuenta' => '62511001', 'descripcion' => 'Atenciones Especiales'],
            ['codigo_cuenta' => '63112001', 'descripcion' => 'De Pasajeros'],
            ['codigo_cuenta' => '63131001', 'descripcion' => 'Alojamiento'],
            ['codigo_cuenta' => '63141001', 'descripcion' => 'Alimentación'],
            ['codigo_cuenta' => '63551001', 'descripcion' => 'Muebles Y Enseres'],
            ['codigo_cuenta' => '63641001', 'descripcion' => 'Teléfono'],
            ['codigo_cuenta' => '63991001', 'descripcion' => 'Diversos (Lavado)'],
            ['codigo_cuenta' => '65611001', 'descripcion' => 'Suministro'],
            ['codigo_cuenta' => '65612001', 'descripcion' => 'Utiles De Oficina'],
            ['codigo_cuenta' => '65613001', 'descripcion' => 'Articulos De Limpieza'],
            ['codigo_cuenta' => '63113001', 'descripcion' => 'Estacionamiento y Peaje'],
            ['codigo_cuenta' => '65941002', 'descripcion' => 'Diversos (Compras)'],
            ['codigo_cuenta' => '65941003', 'descripcion' => 'Impresiones'],
            ['codigo_cuenta' => '65941004', 'descripcion' => 'Refrigerios'],
            ['codigo_cuenta' => '65941005', 'descripcion' => 'Gastos De Representación'],
            ['codigo_cuenta' => '65942001', 'descripcion' => 'Movilidad'],
            ['codigo_cuenta' => '65951002', 'descripcion' => 'Diesel'],
        ];

        // Itera sobre el array e inserta cada cuenta en la base de datos
        foreach ($cuentas as $cuenta) {
            DB::table('cuentas_contables')->insert([
                'codigo_cuenta' => $cuenta['codigo_cuenta'],
                'descripcion' => $cuenta['descripcion'],
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
