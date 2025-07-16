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

        // [MODIFICADO] Obtener gastos proyectados del catálogo usando las NUEVAS descripciones.
        $gastoLibreria = GastoProyectado::where('descripcion', 'Gastos por artículos de librería')->first();
        $gastoAlimentacion = GastoProyectado::where('descripcion', 'Gastos por Alimentación de personal')->first();
        $gastoMovilidad = GastoProyectado::where('descripcion', 'Gastos por servicio de Movilidad')->first();

        if (!$gastoLibreria || !$gastoAlimentacion || !$gastoMovilidad) {
            $this->command->error('No se encontraron los gastos proyectados de ejemplo. Asegúrate de que GastoProyectadoSeeder (actualizado) se haya ejecutado.');
            return;
        }

        // Asociar gastos proyectados a la solicitud APROBADA usando la relación
        $solicitudAprobada->gastosProyectados()->attach([
            $gastoLibreria->id_gasto_proyectado => ['monto_estimado' => 150.00],
            $gastoAlimentacion->id_gasto_proyectado => ['monto_estimado' => 300.00],
        ]);

        // Asociar gastos proyectados a la solicitud PENDIENTE
        $solicitudPendiente->gastosProyectados()->attach([
            $gastoMovilidad->id_gasto_proyectado => ['monto_estimado' => 200.00],
        ]);

        $this->command->info('Detalles de Gastos Proyectados (tabla pivote) creados exitosamente.');
    }
}
