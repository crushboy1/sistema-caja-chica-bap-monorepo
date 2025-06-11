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
            ['codigo_cuenta' => '63211001', 'descripcion' => 'RECIBO POR HONORARIOS'],
            ['codigo_cuenta' => '63911002', 'descripcion' => 'RECIBO POR HONORARIOS POR DIGITACIÓN'],
            ['codigo_cuenta' => '63631001', 'descripcion' => 'SERVICIO DE AGUA (SEDAPAL)'],
            ['codigo_cuenta' => '63141001', 'descripcion' => 'ALIMENTACIÓN POR VIAJE'],
            ['codigo_cuenta' => '63131001', 'descripcion' => 'ALOJAMIENTO'],
            ['codigo_cuenta' => '65613001', 'descripcion' => 'ARTÍCULOS DE LIMPIEZA'],
            ['codigo_cuenta' => '63291001', 'descripcion' => 'CAPACITACIÓN'],
            ['codigo_cuenta' => '65951001', 'descripcion' => 'COMBUSTIBLE'],
            ['codigo_cuenta' => '63911003', 'descripcion' => 'MATERIALES DIVERSOS'],
            ['codigo_cuenta' => '63611001', 'descripcion' => 'SERVICIO DE LUZ (LUZ DEL SUR, ENEL)'],
            ['codigo_cuenta' => '63541001', 'descripcion' => 'ALQUILER DE EQUIPO DE TRANSPORTE'],
            ['codigo_cuenta' => '63561001', 'descripcion' => 'ALQUILER DE EQUIPOS DIVERSOS'],
            ['codigo_cuenta' => '65931001', 'descripcion' => 'ESTACIONAMIENTO Y PEAJE'],
            ['codigo_cuenta' => '63621001', 'descripcion' => 'SERVICIO DE GAS'],
            ['codigo_cuenta' => '63911001', 'descripcion' => 'GASTOS BANCARIOS'],
            ['codigo_cuenta' => '63921001', 'descripcion' => 'GASTOS DE LABORATORIO'],
            ['codigo_cuenta' => '65941005', 'descripcion' => 'GASTOS DE REPRESENTACIÓN'],
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
