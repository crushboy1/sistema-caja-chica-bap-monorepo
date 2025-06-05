<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Llama a los seeders en el orden correcto para respetar las dependencias de claves foráneas

        // Seeders base (dependencias para usuarios)
        $this->call([
            TipoDocumentoIdentidadSeeder::class, // Necesario antes de UserSeeder
            AreaSeeder::class,                   // Necesario antes de UserSeeder
            RoleSeeder::class,                   // Necesario antes de UserSeeder y PermissionRoleSeeder
            PermissionSeeder::class,             // Necesario antes de PermissionRoleSeeder
            PermissionRoleSeeder::class,         // Necesario después de RoleSeeder y PermissionSeeder
            UserSeeder::class,                   // Necesario después de los anteriores
        ]);

        // Seeders para el módulo de Fondos (dependen de Users y Areas)
        $this->call([
            SolicitudFondoSeeder::class,         // Crea las solicitudes (depende de Users, Areas)
            DetalleGastoProyectadoSeeder::class, // Detalle de gastos para solicitudes (depende de SolicitudFondo)
            HistorialEstadoSolicitudSeeder::class, // Historial de estados para solicitudes (depende de SolicitudFondo, Users)
            FondoEfectivoSeeder::class,          // Fondos activos (depende de SolicitudFondo, Users, Areas)
        ]);
    }
}
