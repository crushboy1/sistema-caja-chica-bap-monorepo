<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\SolicitudFondo; // Importar el modelo SolicitudFondo
use App\Models\User; // Importar el modelo User
use App\Models\Area; // Importar el modelo Area

class FondoEfectivoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener la solicitud de Apertura Aprobada
        $solicitudAperturaAprobada = SolicitudFondo::where('codigo_solicitud', 'SOL-00001')->first();

        // Obtener IDs de usuarios y áreas necesarios
        $jefeAreaJuan = User::where('email', 'juan.perez@bap.com')->first();
        $areaGestionProyeccion = Area::where('name', 'Gestión y Proyección Social')->first();

        if (!$solicitudAperturaAprobada || !$jefeAreaJuan || !$areaGestionProyeccion) {
            $this->command->info('¡Advertencia! No se pudieron encontrar la solicitud de apertura aprobada, usuario o área necesarios para FondoEfectivoSeeder. Asegúrate de que SolicitudFondoSeeder, UserSeeder y AreaSeeder se hayan ejecutado correctamente.');
            return;
        }

        // Crear un fondo efectivo solo si la solicitud de apertura fue aprobada
        if ($solicitudAperturaAprobada && $solicitudAperturaAprobada->estado === 'Aprobada') {
            DB::table('fondo_efectivo')->insert([
                'codigo_fondo' => 'FNRO-00001', // Código de fondo para el primer fondo
                'monto_aprobado' => $solicitudAperturaAprobada->monto_solicitado,
                'fecha_apertura' => $solicitudAperturaAprobada->updated_at->toDateString(), // Fecha de aprobación de la solicitud
                'estado' => 'Activo',
                'id_solicitud_apertura' => $solicitudAperturaAprobada->id,
                'id_responsable' => $jefeAreaJuan->id, // Juan Perez es el responsable de este fondo
                'id_area' => $areaGestionProyeccion->id,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
            $this->command->info('Fondo Efectivo creado exitosamente para SOL-00001.');
        } else {
            $this->command->info('No se creó Fondo Efectivo porque SOL-00001 no está en estado "Aprobada".');
        }
    }
}
