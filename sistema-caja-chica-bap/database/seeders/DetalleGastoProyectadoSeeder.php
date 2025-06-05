<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\SolicitudFondo; // Importar el modelo SolicitudFondo

class DetalleGastoProyectadoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener las IDs de las solicitudes de fondo relevantes
        $solicitudAperturaAprobada = SolicitudFondo::where('codigo_solicitud', 'SOL-00001')->first();
        $solicitudAperturaPendienteAdm = SolicitudFondo::where('codigo_solicitud', 'SOL-00002')->first();
        $solicitudAperturaObservadaAdm = SolicitudFondo::where('codigo_solicitud', 'SOL-00003')->first();
        $solicitudIncrementoPendienteGrte = SolicitudFondo::where('codigo_solicitud', 'SOL-00005')->first();

        if (!$solicitudAperturaAprobada || !$solicitudAperturaPendienteAdm || !$solicitudAperturaObservadaAdm || !$solicitudIncrementoPendienteGrte) {
            $this->command->info('¡Advertencia! No se pudieron encontrar todas las solicitudes de fondo necesarias para DetalleGastoProyectadoSeeder. Asegúrate de que SolicitudFondoSeeder se haya ejecutado correctamente.');
            return;
        }

        // Gastos para la Solicitud de Apertura APROBADA (SOL-00001)
        DB::table('detalle_gastos_proyectados')->insert([
            [
                'id_solicitud_fondo' => $solicitudAperturaAprobada->id,
                'descripcion_gasto' => 'Materiales de oficina',
                'monto_estimado' => 500.00,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id_solicitud_fondo' => $solicitudAperturaAprobada->id,
                'descripcion_gasto' => 'Servicios básicos (luz, agua, internet)',
                'monto_estimado' => 1000.00,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);

        // Gastos para la Solicitud de Apertura PENDIENTE ADM (SOL-00002)
        DB::table('detalle_gastos_proyectados')->insert([
            [
                'id_solicitud_fondo' => $solicitudAperturaPendienteAdm->id,
                'descripcion_gasto' => 'Insumos para talleres',
                'monto_estimado' => 1200.00,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id_solicitud_fondo' => $solicitudAperturaPendienteAdm->id,
                'descripcion_gasto' => 'Transporte de personal',
                'monto_estimado' => 800.00,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);

        // Gastos para la Solicitud de Apertura OBSERVADA ADM (SOL-00003)
        DB::table('detalle_gastos_proyectados')->insert([
            [
                'id_solicitud_fondo' => $solicitudAperturaObservadaAdm->id,
                'descripcion_gasto' => 'Publicidad y difusión',
                'monto_estimado' => 1200.00,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);

        // Gastos para la Solicitud de Incremento PENDIENTE GRTE (SOL-00005)
        DB::table('detalle_gastos_proyectados')->insert([
            [
                'id_solicitud_fondo' => $solicitudIncrementoPendienteGrte->id,
                'descripcion_gasto' => 'Compra de equipos de protección personal adicionales',
                'monto_estimado' => 500.00,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);

        $this->command->info('Detalles de Gastos Proyectados creados exitosamente.');
    }
}
