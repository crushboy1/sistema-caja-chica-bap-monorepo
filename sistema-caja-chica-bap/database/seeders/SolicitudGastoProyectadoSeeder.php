<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SolicitudFondo;
use App\Models\GastoProyectado;
use Illuminate\Support\Facades\DB;

class SolicitudGastoProyectadoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Limpieza segura de la tabla pivote
        DB::table('solicitud_gasto_proyectado')->truncate();

        // Obtener solicitudes de ejemplo de una manera más robusta
        $solicitudAprobada = SolicitudFondo::where('estado', 'Aprobada')->where('tipo_solicitud', 'Apertura')->first();
        $solicitudPendiente = SolicitudFondo::where('estado', 'Pendiente Aprobación ADM')->first();

        if (!$solicitudAprobada || !$solicitudPendiente) {
            $this->command->error('No se encontraron las solicitudes de fondo de ejemplo. Asegúrate de que SolicitudFondoSeeder se haya ejecutado correctamente.');
            return;
        }

        // Obtener algunos gastos proyectados del catálogo
        $gastoMateriales = GastoProyectado::where('descripcion', 'MATERIALES DIVERSOS')->first();
        $gastoAlimentacion = GastoProyectado::where('descripcion', 'ALIMENTACIÓN POR VIAJE')->first();
        $gastoTransporte = GastoProyectado::where('descripcion', 'ALQUILER DE EQUIPO DE TRANSPORTE')->first();

        if (!$gastoMateriales || !$gastoAlimentacion || !$gastoTransporte) {
            $this->command->error('No se encontraron los gastos proyectados de ejemplo. Asegúrate de que GastoProyectadoSeeder se haya ejecutado.');
            return;
        }

        // Asociar gastos proyectados a la solicitud APROBADA usando la relación
        $solicitudAprobada->gastosProyectados()->attach([
            $gastoMateriales->id_gasto_proyectado => ['monto_estimado' => 500.00],
            $gastoAlimentacion->id_gasto_proyectado => ['monto_estimado' => 1000.00],
        ]);

        // Asociar gastos proyectados a la solicitud PENDIENTE
        $solicitudPendiente->gastosProyectados()->attach([
            $gastoTransporte->id_gasto_proyectado => ['monto_estimado' => 800.00],
        ]);

        $this->command->info('Detalles de Gastos Proyectados (tabla pivote) creados exitosamente.');
    }
}
