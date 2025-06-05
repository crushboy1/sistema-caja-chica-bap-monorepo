<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User; // Importar el modelo User
use App\Models\Area; // Importar el modelo Area

class SolicitudFondoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener IDs de usuarios y áreas necesarios
        $jefeAreaJuan = User::where('email', 'juan.perez@bap.com')->first();
        $jefeAdmMaria = User::where('email', 'maria.gomez@bap.com')->first();
        $gerenteCarlos = User::where('email', 'carlos.lopez@bap.com')->first();

        $areaGestionProyeccion = Area::where('name', 'Gestión y Proyección Social')->first();
        $areaAdministracion = Area::where('name', 'Administración y Contabilidad')->first();

        // Asegurarse de que los usuarios y áreas existan
        if (!$jefeAreaJuan || !$jefeAdmMaria || !$gerenteCarlos || !$areaGestionProyeccion || !$areaAdministracion) {
            $this->command->info('¡Advertencia! No se pudieron encontrar todos los usuarios o áreas necesarios para SolicitudFondoSeeder. Asegúrate de que UserSeeder y AreaSeeder se hayan ejecutado correctamente.');
            return;
        }

        // --- Solicitudes de Apertura de Fondo ---

        // 1. Solicitud de Apertura APROBADA (para que pueda tener un FondoEfectivo asociado)
        $solicitudAperturaAprobadaId = DB::table('solicitudes_fondos')->insertGetId([
            'codigo_solicitud' => 'SOL-00001',
            'id_solicitante' => $jefeAreaJuan->id,
            'id_area' => $areaGestionProyeccion->id,
            'tipo_solicitud' => 'Apertura',
            'motivo_detalle' => 'Apertura de fondo para gastos operativos del área de Gestión y Proyección Social.',
            'monto_solicitado' => 1500.00,
            'prioridad' => 'Alta',
            'estado' => 'Aprobada', // Estado final
            'id_revisor_adm' => $jefeAdmMaria->id,
            'id_aprobador_gerente' => $gerenteCarlos->id,
            'created_at' => Carbon::now()->subDays(30),
            'updated_at' => Carbon::now()->subDays(5),
        ]);

        // 2. Solicitud de Apertura PENDIENTE APROBACIÓN ADM
        DB::table('solicitudes_fondos')->insert([
            'codigo_solicitud' => 'SOL-00002',
            'id_solicitante' => $jefeAreaJuan->id,
            'id_area' => $areaGestionProyeccion->id,
            'tipo_solicitud' => 'Apertura',
            'motivo_detalle' => 'Nueva apertura de fondo para proyecto de invierno.',
            'monto_solicitado' => 2000.00,
            'prioridad' => 'Urgente',
            'estado' => 'Pendiente Aprobación ADM',
            'created_at' => Carbon::now()->subDays(10),
            'updated_at' => Carbon::now()->subDays(10),
        ]);

        // 3. Solicitud de Apertura OBSERVADA ADM (requiere descargo)
        DB::table('solicitudes_fondos')->insert([
            'codigo_solicitud' => 'SOL-00003',
            'id_solicitante' => $jefeAreaJuan->id,
            'id_area' => $areaGestionProyeccion->id,
            'tipo_solicitud' => 'Apertura',
            'motivo_detalle' => 'Apertura de fondo para materiales de campaña de verano.',
            'monto_solicitado' => 1200.00,
            'prioridad' => 'Media',
            'estado' => 'Creada',
            'motivo_observacion' => 'Detalle de gastos insuficiente. Favor de especificar más.',
            'id_revisor_adm' => $jefeAdmMaria->id,
            'created_at' => Carbon::now()->subDays(15),
            'updated_at' => Carbon::now()->subDays(12),
        ]);

        // 4. Solicitud de Apertura con DESCARGO ENVIADO ADM
        DB::table('solicitudes_fondos')->insert([
            'codigo_solicitud' => 'SOL-00004',
            'id_solicitante' => $jefeAreaJuan->id,
            'id_area' => $areaGestionProyeccion->id,
            'tipo_solicitud' => 'Apertura',
            'motivo_detalle' => 'Apertura para actividades de capacitación del personal.',
            'monto_solicitado' => 800.00,
            'prioridad' => 'Baja',
            'estado' => 'Descargo Enviado ADM',
            'motivo_descargo' => 'Se adjunta desglose detallado de los materiales y costos de los capacitadores.',
            'id_revisor_adm' => $jefeAdmMaria->id,
            'created_at' => Carbon::now()->subDays(20),
            'updated_at' => Carbon::now()->subDays(18),
        ]);

        // --- Solicitudes de Modificación (Incremento/Decremento/Cierre) ---

        // 5. Solicitud de Incremento PENDIENTE APROBACIÓN GRTE
        $solicitudIncrementoPendienteGrteId = DB::table('solicitudes_fondos')->insertGetId([
            'codigo_solicitud' => 'SOL-00005',
            'id_solicitante' => $jefeAreaJuan->id,
            'id_area' => $areaGestionProyeccion->id,
            'tipo_solicitud' => 'Incremento',
            'motivo_detalle' => 'Incremento de fondo debido a gastos imprevistos en proyecto SOL-00001.',
            'monto_solicitado' => 500.00,
            'prioridad' => 'Alta',
            'estado' => 'Pendiente Aprobación GRTE',
            'id_solicitud_original' => $solicitudAperturaAprobadaId, // Referencia a la solicitud de apertura aprobada
            'id_revisor_adm' => $jefeAdmMaria->id, // Ya fue revisada por ADM
            'created_at' => Carbon::now()->subDays(7),
            'updated_at' => Carbon::now()->subDays(6),
        ]);

        // 6. Solicitud de Decremento RECHAZADA FINAL (solo para modificaciones)
        DB::table('solicitudes_fondos')->insert([
            'codigo_solicitud' => 'SOL-00006',
            'id_solicitante' => $jefeAreaJuan->id,
            'id_area' => $areaGestionProyeccion->id,
            'tipo_solicitud' => 'Decremento',
            'motivo_detalle' => 'Reducción de fondo por cancelación de evento.',
            'monto_solicitado' => 300.00,
            'prioridad' => 'Media',
            'estado' => 'Rechazada Final',
            'motivo_rechazo_final' => 'La reducción del fondo no es viable en este momento debido a compromisos existentes.',
            'id_solicitud_original' => $solicitudAperturaAprobadaId, // Referencia a la solicitud de apertura aprobada
            'id_revisor_adm' => $jefeAdmMaria->id,
            'id_aprobador_gerente' => $gerenteCarlos->id, // Rechazada por Gerente
            'created_at' => Carbon::now()->subDays(25),
            'updated_at' => Carbon::now()->subDays(20),
        ]);

        // 7. Solicitud de Cierre PENDIENTE APROBACIÓN GRTE
        DB::table('solicitudes_fondos')->insert([
            'codigo_solicitud' => 'SOL-00007',
            'id_solicitante' => $jefeAreaJuan->id,
            'id_area' => $areaGestionProyeccion->id,
            'tipo_solicitud' => 'Cierre',
            'motivo_detalle' => 'Cierre de fondo por finalización de proyecto y liquidación total.',
            'monto_solicitado' => 0.00, // Monto 0 para cierre
            'prioridad' => 'Alta',
            'estado' => 'Pendiente Aprobación GRTE',
            'id_solicitud_original' => $solicitudAperturaAprobadaId, // Referencia a la solicitud de apertura aprobada
            'id_revisor_adm' => $jefeAdmMaria->id,
            'created_at' => Carbon::now()->subDays(3),
            'updated_at' => Carbon::now()->subDays(2),
        ]);

        $this->command->info('Solicitudes de Fondo creadas exitosamente.');
    }
}
