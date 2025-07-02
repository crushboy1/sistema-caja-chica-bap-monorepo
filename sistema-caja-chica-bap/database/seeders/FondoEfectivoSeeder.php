<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FondoEfectivo;
use App\Models\SolicitudFondo;
use Illuminate\Support\Facades\DB;

class FondoEfectivoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Limpieza segura de la tabla
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        FondoEfectivo::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // En lugar de crear el fondo manualmente, usamos la lógica de negocio del modelo.
        // Buscamos todas las solicitudes de Apertura que fueron aprobadas en el seeder anterior.
        $solicitudesAprobadas = SolicitudFondo::where('tipo_solicitud', 'Apertura')
            ->where('estado', 'Aprobada')
            ->get();

        if ($solicitudesAprobadas->isEmpty()) {
            $this->command->warn('No se encontraron solicitudes de apertura aprobadas para crear fondos efectivos. Omitiendo seeder.');
            return;
        }

        // Por cada solicitud aprobada, creamos su fondo correspondiente.
        foreach ($solicitudesAprobadas as $solicitud) {
            try {
                // Este método ya contiene toda la lógica para generar el código del fondo,
                // asignar montos, fechas, etc.
                FondoEfectivo::crearDesdeSolicitudApertura($solicitud);
            } catch (\Exception $e) {
                $this->command->error('Error al crear fondo para solicitud ' . $solicitud->codigo_solicitud . ': ' . $e->getMessage());
            }
        }

        $this->command->info(count($solicitudesAprobadas) . ' Fondo(s) Efectivo(s) creados desde solicitudes aprobadas.');
    }
}
