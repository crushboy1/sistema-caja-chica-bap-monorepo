<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\SolicitudFondo;
use App\Models\Proyecto;
use App\Models\User;
use App\Models\Area;

class SolicitudFondoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Limpieza segura de la tabla para hacer el seeder re-ejecutable
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        SolicitudFondo::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Obtener usuarios y áreas
        $jefeAreaJuan = User::where('email', 'juan.perez@bap.com')->first();
        $jefeAdmMaria = User::where('email', 'maria.gomez@bap.com')->first();
        $gerenteCarlos = User::where('email', 'carlos.lopez@bap.com')->first();
        $areaGestionProyeccion = Area::where('name', 'Gestión y Proyección Social')->first();
        $proyectoCucharones = Proyecto::where('nombre_proyecto', 'Cucharones Luchadores')->first();

        if (!$jefeAreaJuan || !$jefeAdmMaria || !$gerenteCarlos || !$areaGestionProyeccion || !$proyectoCucharones) {
            $this->command->error('No se encontraron todos los usuarios, áreas o proyectos necesarios. Asegúrate de que los seeders anteriores se hayan ejecutado.');
            return;
        }

        // 1. Solicitud de Apertura APROBADA (Fondo Regular)
        $solicitudAprobada = SolicitudFondo::create([
            'id_solicitante' => $jefeAreaJuan->id,
            'id_area' => $areaGestionProyeccion->id,
            'tipo_solicitud' => 'Apertura',
            'tipo_fondo_solicitado' => 'Regular',
            'motivo_detalle' => 'Apertura de fondo para gastos operativos del área.',
            'monto_solicitado' => 1500.00,
            'prioridad' => 'Alta',
            'estado' => 'Aprobada',
            'id_revisor_adm' => $jefeAdmMaria->id,
            'id_aprobador_gerente' => $gerenteCarlos->id,
            'created_at' => Carbon::now()->subDays(30),
            'updated_at' => Carbon::now()->subDays(5),
        ]);

        // 2. Solicitud de Apertura PENDIENTE ADM (Fondo Excepcional)
        SolicitudFondo::create([
            'id_solicitante' => $jefeAreaJuan->id,
            'id_area' => $areaGestionProyeccion->id,
            'tipo_solicitud' => 'Apertura',
            'tipo_fondo_solicitado' => 'Excepcional',
            'motivo_detalle' => 'Fondo excepcional para viaje de emergencia.',
            'monto_solicitado' => 2000.00,
            'prioridad' => 'Urgente',
            'estado' => 'Pendiente Aprobación ADM',
            'created_at' => Carbon::now()->subDays(10),
            'updated_at' => Carbon::now()->subDays(10),
        ]);

        // 3. Solicitud de Apertura de PROYECTO (Auto-aprobada)
        SolicitudFondo::create([
            'id_solicitante' => $jefeAdmMaria->id, // Solicitado por el Jefe de Proyectos/ADM
            'id_area' => $jefeAdmMaria->area_id,
            'tipo_solicitud' => 'Apertura',
            'tipo_fondo_solicitado' => 'Proyecto',
            'id_proyecto' => $proyectoCucharones->id_proyecto,
            'motivo_detalle' => 'Apertura de fondo para el proyecto Cucharones Luchadores.',
            'monto_solicitado' => 25000.00,
            'prioridad' => 'Alta',
            'estado' => 'Aprobada', // El controlador lo auto-aprueba, aquí lo simulamos.
            'created_at' => Carbon::now()->subDays(20),
            'updated_at' => Carbon::now()->subDays(20),
        ]);

        // 4. Solicitud de Incremento PENDIENTE GRTE
        SolicitudFondo::create([
            'id_solicitante' => $jefeAreaJuan->id,
            'id_area' => $areaGestionProyeccion->id,
            'tipo_solicitud' => 'Incremento',
            'motivo_detalle' => 'Incremento de fondo debido a gastos imprevistos.',
            'monto_solicitado' => 500.00,
            'prioridad' => 'Alta',
            'estado' => 'Pendiente Aprobación GRTE',
            'id_solicitud_original' => $solicitudAprobada->id,
            'id_revisor_adm' => $jefeAdmMaria->id,
            'created_at' => Carbon::now()->subDays(7),
            'updated_at' => Carbon::now()->subDays(6),
        ]);

        $this->command->info('Solicitudes de Fondo creadas exitosamente.');
    }
}
