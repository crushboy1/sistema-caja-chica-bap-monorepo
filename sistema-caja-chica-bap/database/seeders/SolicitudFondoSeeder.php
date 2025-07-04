<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Area;
use App\Models\SolicitudFondo;
use App\Models\Proyecto;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SolicitudFondoSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        SolicitudFondo::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Obtener usuarios y áreas
        $jefeAreaJuan = User::where('email', 'juan.perez@bap.com')->first();
        $jefeAdmMaria = User::where('email', 'maria.gomez@bap.com')->first();
        $gerenteCarlos = User::where('email', 'carlos.lopez@bap.com')->first();
        
        // --- INICIO DE CORRECCIÓN: Usar el nombre de área correcto ---
        $areaProyectos = Area::where('name', 'Proyectos')->first(); // Se corrige el nombre del área
        // --- FIN DE CORRECCIÓN ---
        
        $proyectoCucharones = Proyecto::where('nombre_proyecto', 'Cucharones Luchadores')->first();

        if (!$jefeAreaJuan || !$jefeAdmMaria || !$gerenteCarlos || !$areaProyectos || !$proyectoCucharones) {
            $this->command->error('No se encontraron todos los usuarios, áreas o proyectos necesarios. Asegúrate de que los seeders anteriores se hayan ejecutado.');
            return;
        }

        // --- INICIO DE REFACTORIZACIÓN: Se elimina la asignación manual de 'codigo_solicitud' ---

        // 1. Solicitud de Apertura APROBADA (Fondo Regular)
        $solicitudAprobada = SolicitudFondo::create([
            'id_solicitante' => $jefeAreaJuan->id,
            'id_area' => $jefeAreaJuan->area_id, // Usar el área del usuario solicitante
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
            'id_area' => $jefeAreaJuan->area_id,
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
            'id_solicitante' => $jefeAdmMaria->id,
            'id_area' => $jefeAdmMaria->area_id,
            'tipo_solicitud' => 'Apertura',
            'tipo_fondo_solicitado' => 'Proyecto',
            'id_proyecto' => $proyectoCucharones->id_proyecto,
            'motivo_detalle' => 'Apertura de fondo para el proyecto Cucharones Luchadores.',
            'monto_solicitado' => 25000.00,
            'prioridad' => 'Alta',
            'estado' => 'Aprobada',
            'created_at' => Carbon::now()->subDays(20),
            'updated_at' => Carbon::now()->subDays(20),
        ]);

        $this->command->info('Solicitudes de Fondo creadas exitosamente.');
    }
}
