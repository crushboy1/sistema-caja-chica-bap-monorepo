<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\SolicitudFondo; // Importar el modelo SolicitudFondo
use App\Models\User; // Importar el modelo User

class HistorialEstadoSolicitudSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener IDs de usuarios y solicitudes necesarios
        $jefeAreaJuan = User::where('email', 'juan.perez@bap.com')->first();
        $jefeAdmMaria = User::where('email', 'maria.gomez@bap.com')->first();
        $gerenteCarlos = User::where('email', 'carlos.lopez@bap.com')->first();

        $solicitudAperturaAprobada = SolicitudFondo::where('codigo_solicitud', 'SOL-00001')->first();
        $solicitudAperturaPendienteAdm = SolicitudFondo::where('codigo_solicitud', 'SOL-00002')->first();
        $solicitudAperturaObservadaAdm = SolicitudFondo::where('codigo_solicitud', 'SOL-00003')->first();
        $solicitudAperturaDescargoEnviadoAdm = SolicitudFondo::where('codigo_solicitud', 'SOL-00004')->first();
        $solicitudIncrementoPendienteGrte = SolicitudFondo::where('codigo_solicitud', 'SOL-00005')->first();
        $solicitudDecrementoRechazadaFinal = SolicitudFondo::where('codigo_solicitud', 'SOL-00006')->first();
        $solicitudCierrePendienteGrte = SolicitudFondo::where('codigo_solicitud', 'SOL-00007')->first();


        if (!$jefeAreaJuan || !$jefeAdmMaria || !$gerenteCarlos ||
            !$solicitudAperturaAprobada || !$solicitudAperturaPendienteAdm ||
            !$solicitudAperturaObservadaAdm || !$solicitudAperturaDescargoEnviadoAdm ||
            !$solicitudIncrementoPendienteGrte || !$solicitudDecrementoRechazadaFinal ||
            !$solicitudCierrePendienteGrte) {
            $this->command->info('¡Advertencia! No se pudieron encontrar todas las solicitudes o usuarios necesarios para HistorialEstadoSolicitudSeeder. Asegúrate de que SolicitudFondoSeeder y UserSeeder se hayan ejecutado correctamente.');
            return;
        }

        // --- Historial para Solicitud de Apertura APROBADA (SOL-00001) ---
        DB::table('historial_estados_solicitud')->insert([
            [
                'id_solicitud_fondo' => $solicitudAperturaAprobada->id,
                'estado_anterior' => null,
                'estado_nuevo' => 'Creada',
                'observaciones' => 'Solicitud de apertura de fondo creada por el Jefe de Área.',
                'id_usuario_accion' => $jefeAreaJuan->id,
                'fecha_cambio' => $solicitudAperturaAprobada->created_at,
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
            ],
            [
                'id_solicitud_fondo' => $solicitudAperturaAprobada->id,
                'estado_anterior' => 'Creada',
                'estado_nuevo' => 'Pendiente Aprobación ADM',
                'observaciones' => 'Enviada a revisión del Jefe de Administración.',
                'id_usuario_accion' => null, // Acción automática del sistema
                'fecha_cambio' => $solicitudAperturaAprobada->created_at->addMinutes(5),
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
            ],
            [
                'id_solicitud_fondo' => $solicitudAperturaAprobada->id,
                'estado_anterior' => 'Pendiente Aprobación ADM',
                'estado_nuevo' => 'Aprobada ADM',
                'observaciones' => 'Aprobada por el Jefe de Administración.',
                'id_usuario_accion' => $jefeAdmMaria->id,
                'fecha_cambio' => $solicitudAperturaAprobada->created_at->addDays(1),
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
            ],
            [
                'id_solicitud_fondo' => $solicitudAperturaAprobada->id,
                'estado_anterior' => 'Aprobada ADM',
                'estado_nuevo' => 'Pendiente Aprobación GRTE',
                'observaciones' => 'Enviada a revisión del Gerente General.',
                'id_usuario_accion' => null, // Acción automática del sistema
                'fecha_cambio' => $solicitudAperturaAprobada->created_at->addDays(1)->addMinutes(5),
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
            ],
            [
                'id_solicitud_fondo' => $solicitudAperturaAprobada->id,
                'estado_anterior' => 'Pendiente Aprobación GRTE',
                'estado_nuevo' => 'Aprobada',
                'observaciones' => 'Aprobada por el Gerente General. Fondo listo para ser activado.',
                'id_usuario_accion' => $gerenteCarlos->id,
                'fecha_cambio' => $solicitudAperturaAprobada->updated_at,
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
            ],
        ]);

        // --- Historial para Solicitud de Apertura PENDIENTE ADM (SOL-00002) ---
        DB::table('historial_estados_solicitud')->insert([
            [
                'id_solicitud_fondo' => $solicitudAperturaPendienteAdm->id,
                'estado_anterior' => null,
                'estado_nuevo' => 'Creada',
                'observaciones' => 'Solicitud de apertura de fondo creada.',
                'id_usuario_accion' => $jefeAreaJuan->id,
                'fecha_cambio' => $solicitudAperturaPendienteAdm->created_at,
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
            ],
            [
                'id_solicitud_fondo' => $solicitudAperturaPendienteAdm->id,
                'estado_anterior' => 'Creada',
                'estado_nuevo' => 'Pendiente Aprobación ADM',
                'observaciones' => 'Enviada a revisión del Jefe de Administración.',
                'id_usuario_accion' => null,
                'fecha_cambio' => $solicitudAperturaPendienteAdm->created_at->addMinutes(5),
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
            ],
        ]);

        // --- Historial para Solicitud de Apertura OBSERVADA ADM (SOL-00003) ---
        DB::table('historial_estados_solicitud')->insert([
            [
                'id_solicitud_fondo' => $solicitudAperturaObservadaAdm->id,
                'estado_anterior' => null,
                'estado_nuevo' => 'Creada',
                'observaciones' => 'Solicitud de apertura de fondo creada.',
                'id_usuario_accion' => $jefeAreaJuan->id,
                'fecha_cambio' => $solicitudAperturaObservadaAdm->created_at,
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
            ],
            [
                'id_solicitud_fondo' => $solicitudAperturaObservadaAdm->id,
                'estado_anterior' => 'Creada',
                'estado_nuevo' => 'Pendiente Aprobación ADM',
                'observaciones' => 'Enviada a revisión del Jefe de Administración.',
                'id_usuario_accion' => null,
                'fecha_cambio' => $solicitudAperturaObservadaAdm->created_at->addMinutes(5),
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
            ],
            [
                'id_solicitud_fondo' => $solicitudAperturaObservadaAdm->id,
                'estado_anterior' => 'Pendiente Aprobación ADM',
                'estado_nuevo' => 'Observada ADM',
                'observaciones' => 'Detalle de gastos insuficiente. Favor de especificar más.',
                'id_usuario_accion' => $jefeAdmMaria->id,
                'fecha_cambio' => $solicitudAperturaObservadaAdm->updated_at,
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
            ],
        ]);

        // --- Historial para Solicitud de Apertura con DESCARGO ENVIADO ADM (SOL-00004) ---
        DB::table('historial_estados_solicitud')->insert([
            [
                'id_solicitud_fondo' => $solicitudAperturaDescargoEnviadoAdm->id,
                'estado_anterior' => null,
                'estado_nuevo' => 'Creada',
                'observaciones' => 'Solicitud de apertura de fondo creada.',
                'id_usuario_accion' => $jefeAreaJuan->id,
                'fecha_cambio' => $solicitudAperturaDescargoEnviadoAdm->created_at->subDays(2), // Creada antes de ser observada
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
            ],
            [
                'id_solicitud_fondo' => $solicitudAperturaDescargoEnviadoAdm->id,
                'estado_anterior' => 'Creada',
                'estado_nuevo' => 'Pendiente Aprobación ADM',
                'observaciones' => 'Enviada a revisión del Jefe de Administración.',
                'id_usuario_accion' => null,
                'fecha_cambio' => $solicitudAperturaDescargoEnviadoAdm->created_at->subDays(2)->addMinutes(5),
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
            ],
            [
                'id_solicitud_fondo' => $solicitudAperturaDescargoEnviadoAdm->id,
                'estado_anterior' => 'Pendiente Aprobación ADM',
                'estado_nuevo' => 'Observada ADM',
                'observaciones' => 'Se requiere mayor justificación para el monto solicitado.',
                'id_usuario_accion' => $jefeAdmMaria->id,
                'fecha_cambio' => $solicitudAperturaDescargoEnviadoAdm->created_at->subDays(1),
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
            ],
            [
                'id_solicitud_fondo' => $solicitudAperturaDescargoEnviadoAdm->id,
                'estado_anterior' => 'Observada ADM',
                'estado_nuevo' => 'Descargo Enviado ADM',
                'observaciones' => 'Descargo presentado por el Jefe de Área: Se adjunta desglose detallado de los materiales y costos de los capacitadores.',
                'id_usuario_accion' => $jefeAreaJuan->id,
                'fecha_cambio' => $solicitudAperturaDescargoEnviadoAdm->updated_at,
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
            ],
        ]);

        // --- Historial para Solicitud de Incremento PENDIENTE GRTE (SOL-00005) ---
        DB::table('historial_estados_solicitud')->insert([
            [
                'id_solicitud_fondo' => $solicitudIncrementoPendienteGrte->id,
                'estado_anterior' => null,
                'estado_nuevo' => 'Creada',
                'observaciones' => 'Solicitud de incremento de fondo creada.',
                'id_usuario_accion' => $jefeAreaJuan->id,
                'fecha_cambio' => $solicitudIncrementoPendienteGrte->created_at,
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
            ],
            [
                'id_solicitud_fondo' => $solicitudIncrementoPendienteGrte->id,
                'estado_anterior' => 'Creada',
                'estado_nuevo' => 'Pendiente Aprobación ADM',
                'observaciones' => 'Enviada a revisión del Jefe de Administración.',
                'id_usuario_accion' => null,
                'fecha_cambio' => $solicitudIncrementoPendienteGrte->created_at->addMinutes(5),
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
            ],
            [
                'id_solicitud_fondo' => $solicitudIncrementoPendienteGrte->id,
                'estado_anterior' => 'Pendiente Aprobación ADM',
                'estado_nuevo' => 'Aprobada ADM',
                'observaciones' => 'Aprobada por el Jefe de Administración.',
                'id_usuario_accion' => $jefeAdmMaria->id,
                'fecha_cambio' => $solicitudIncrementoPendienteGrte->updated_at,
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
            ],
            [
                'id_solicitud_fondo' => $solicitudIncrementoPendienteGrte->id,
                'estado_anterior' => 'Aprobada ADM',
                'estado_nuevo' => 'Pendiente Aprobación GRTE',
                'observaciones' => 'Enviada a revisión del Gerente General.',
                'id_usuario_accion' => null,
                'fecha_cambio' => $solicitudIncrementoPendienteGrte->updated_at->addMinutes(5),
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
            ],
        ]);

        // --- Historial para Solicitud de Decremento RECHAZADA FINAL (SOL-00006) ---
        DB::table('historial_estados_solicitud')->insert([
            [
                'id_solicitud_fondo' => $solicitudDecrementoRechazadaFinal->id,
                'estado_anterior' => null,
                'estado_nuevo' => 'Creada',
                'observaciones' => 'Solicitud de decremento de fondo creada.',
                'id_usuario_accion' => $jefeAreaJuan->id,
                'fecha_cambio' => $solicitudDecrementoRechazadaFinal->created_at,
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
            ],
            [
                'id_solicitud_fondo' => $solicitudDecrementoRechazadaFinal->id,
                'estado_anterior' => 'Creada',
                'estado_nuevo' => 'Pendiente Aprobación ADM',
                'observaciones' => 'Enviada a revisión del Jefe de Administración.',
                'id_usuario_accion' => null,
                'fecha_cambio' => $solicitudDecrementoRechazadaFinal->created_at->addMinutes(5),
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
            ],
            [
                'id_solicitud_fondo' => $solicitudDecrementoRechazadaFinal->id,
                'estado_anterior' => 'Pendiente Aprobación ADM',
                'estado_nuevo' => 'Aprobada ADM',
                'observaciones' => 'Aprobada por el Jefe de Administración.',
                'id_usuario_accion' => $jefeAdmMaria->id,
                'fecha_cambio' => $solicitudDecrementoRechazadaFinal->created_at->addDays(1),
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
            ],
            [
                'id_solicitud_fondo' => $solicitudDecrementoRechazadaFinal->id,
                'estado_anterior' => 'Aprobada ADM',
                'estado_nuevo' => 'Pendiente Aprobación GRTE',
                'observaciones' => 'Enviada a revisión del Gerente General.',
                'id_usuario_accion' => null,
                'fecha_cambio' => $solicitudDecrementoRechazadaFinal->created_at->addDays(1)->addMinutes(5),
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
            ],
            [
                'id_solicitud_fondo' => $solicitudDecrementoRechazadaFinal->id,
                'estado_anterior' => 'Pendiente Aprobación GRTE',
                'estado_nuevo' => 'Rechazada Final',
                'observaciones' => 'La reducción del fondo no es viable en este momento debido a compromisos existentes.',
                'id_usuario_accion' => $gerenteCarlos->id,
                'fecha_cambio' => $solicitudDecrementoRechazadaFinal->updated_at,
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
            ],
        ]);

        // --- Historial para Solicitud de Cierre PENDIENTE GRTE (SOL-00007) ---
        DB::table('historial_estados_solicitud')->insert([
            [
                'id_solicitud_fondo' => $solicitudCierrePendienteGrte->id,
                'estado_anterior' => null,
                'estado_nuevo' => 'Creada',
                'observaciones' => 'Solicitud de cierre de fondo creada.',
                'id_usuario_accion' => $jefeAreaJuan->id,
                'fecha_cambio' => $solicitudCierrePendienteGrte->created_at,
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
            ],
            [
                'id_solicitud_fondo' => $solicitudCierrePendienteGrte->id,
                'estado_anterior' => 'Creada',
                'estado_nuevo' => 'Pendiente Aprobación ADM',
                'observaciones' => 'Enviada a revisión del Jefe de Administración.',
                'id_usuario_accion' => null,
                'fecha_cambio' => $solicitudCierrePendienteGrte->created_at->addMinutes(5),
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
            ],
            [
                'id_solicitud_fondo' => $solicitudCierrePendienteGrte->id,
                'estado_anterior' => 'Pendiente Aprobación ADM',
                'estado_nuevo' => 'Aprobada ADM',
                'observaciones' => 'Aprobada por el Jefe de Administración.',
                'id_usuario_accion' => $jefeAdmMaria->id,
                'fecha_cambio' => $solicitudCierrePendienteGrte->created_at->addDays(1),
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
            ],
            [
                'id_solicitud_fondo' => $solicitudCierrePendienteGrte->id,
                'estado_anterior' => 'Aprobada ADM',
                'estado_nuevo' => 'Pendiente Aprobación GRTE',
                'observaciones' => 'Enviada a revisión del Gerente General.',
                'id_usuario_accion' => null,
                'fecha_cambio' => $solicitudCierrePendienteGrte->updated_at,
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
            ],
        ]);

        $this->command->info('Historial de Estados de Solicitud creado exitosamente.');
    }
}
