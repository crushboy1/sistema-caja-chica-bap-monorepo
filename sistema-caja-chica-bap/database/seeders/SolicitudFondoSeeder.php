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
    /**
     * Run the database seeds.
     *
     * Este seeder ahora crea solicitudes de ejemplo y las asocia a los
     * proyectos que ya existen en la base de datos (creados por ProyectoSeeder).
     */
    public function run(): void
    {
        // Se limpian solo las solicitudes para no afectar otros datos.
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        SolicitudFondo::truncate();
        // La tabla pivote de gastos proyectados también se limpia, ya que depende de las solicitudes.
        DB::table('solicitud_gasto_proyectado')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // --- 1. Obtener los datos necesarios (Usuarios y Proyectos) ---
        $jefeAreaJuan = User::where('email', 'juan.perez@bap.com')->first();
        $jefeAdmMaria = User::where('email', 'maria.gomez@bap.com')->first();
        $gerenteCarlos = User::where('email', 'carlos.lopez@bap.com')->first();
        
        // Se buscan los proyectos por su código único en lugar de crearlos.
        $proyectoRepsol = Proyecto::where('codigo', 'REPSOL 2023')->first();

        // Verificación para asegurar que los datos base existen.
        if (!$jefeAreaJuan || !$jefeAdmMaria || !$gerenteCarlos || !$proyectoRepsol) {
            $this->command->error('No se encontraron usuarios o proyectos necesarios. Ejecuta los seeders de User y Proyecto primero.');
            return;
        }

        // --- 2. Crear Solicitudes de Ejemplo ---

        // Solicitud 1: Apertura de Fondo Regular (Aprobada)
        SolicitudFondo::create([
            'id_solicitante' => $jefeAreaJuan->id,
            'id_area' => $jefeAreaJuan->area_id,
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

        // Solicitud 2: Apertura de Fondo Excepcional (Pendiente)
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

        // Solicitud 3: Apertura de Fondo de Proyecto (Auto-aprobada)
        //  Se asocia con el ID del proyecto encontrado.
        SolicitudFondo::create([
            'id_solicitante' => $jefeAdmMaria->id,
            'id_area' => $jefeAdmMaria->area_id,
            'tipo_solicitud' => 'Apertura',
            'tipo_fondo_solicitado' => 'Proyecto',
            'id_proyecto' => $proyectoRepsol->id_proyecto,
            'motivo_detalle' => 'Apertura de fondo para el proyecto ' . $proyectoRepsol->nombre,
            'monto_solicitado' => 25000.00,
            'prioridad' => 'Alta',
            'estado' => 'Aprobada',
            'created_at' => Carbon::now()->subDays(20),
            'updated_at' => Carbon::now()->subDays(20),
        ]);

        $this->command->info('Seeder de Solicitudes de Fondo ejecutado exitosamente.');
    }
}
