<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SolicitudFondo;
use App\Models\HistorialEstadoSolicitud;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class HistorialEstadoSolicitudSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Limpieza segura de la tabla
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        HistorialEstadoSolicitud::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Obtener los usuarios para simular las acciones
        $jefeAreaJuan = User::where('email', 'juan.perez@bap.com')->first();
        $jefeAdmMaria = User::where('email', 'maria.gomez@bap.com')->first();
        $gerenteCarlos = User::where('email', 'carlos.lopez@bap.com')->first();

        // Obtener una solicitud que ya esté aprobada para añadirle un historial completo
        $solicitudAprobada = SolicitudFondo::where('estado', 'Aprobada')
            ->where('tipo_solicitud', 'Apertura')
            ->where('tipo_fondo_solicitado', 'Regular')
            ->first();

        if (!$solicitudAprobada || !$jefeAreaJuan || !$jefeAdmMaria || !$gerenteCarlos) {
            $this->command->warn('No se pudo encontrar la solicitud aprobada o los usuarios necesarios para crear el historial de estados. Omitiendo seeder.');
            return;
        }

        // Crear un historial de ejemplo para la solicitud aprobada
        // Usamos el método registrarEnHistorial del modelo SolicitudFondo para consistencia
        $solicitudAprobada->registrarEnHistorial('Creada', 'Solicitud de apertura de fondo creada por el Jefe de Área.', $jefeAreaJuan->id);
        $solicitudAprobada->registrarEnHistorial('Pendiente Aprobación ADM', 'Enviada a revisión del Jefe de Administración.', $jefeAreaJuan->id);
        $solicitudAprobada->registrarEnHistorial('Aprobada ADM', 'Aprobada por el Jefe de Administración.', $jefeAdmMaria->id);
        $solicitudAprobada->registrarEnHistorial('Pendiente Aprobación GRTE', 'Enviada a revisión del Gerente General.', $jefeAdmMaria->id);
        $solicitudAprobada->registrarEnHistorial('Aprobada', 'Aprobada por el Gerente General. Fondo listo para ser activado.', $gerenteCarlos->id);

        $this->command->info('Historial de Estados de Solicitud creado exitosamente.');
    }
}
