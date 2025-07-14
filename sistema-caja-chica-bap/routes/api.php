<?php

use Illuminate\Support\Facades\Route;

Route::get('/health', function (Request $request) {
    return response()->json([
        'status' => 'ok',
        'message' => 'Conexión con el backend del SGFE-BAP exitosa!'
    ]);
});
/*
|--------------------------------------------------------------------------
| Cargador de Rutas de la API del SGFE-BAP
|--------------------------------------------------------------------------
|
| Este archivo actúa como el punto de entrada principal para las rutas de la API.
| En lugar de definir todas las rutas aquí, carga archivos de ruta modulares
| para mantener el código organizado y mantenible. Cada archivo agrupa los
| endpoints por su dominio o funcionalidad.
|
*/

Route::prefix('auth')->group(base_path('routes/api/auth.php'));
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    require base_path('routes/api/solicitudes.php');
    require base_path('routes/api/fondos.php');
    require base_path('routes/api/gastos.php');
    require base_path('routes/api/recursos.php');
    require base_path('routes/api/cuentas_contables.php');
    require base_path('routes/api/proyectos.php');
    require base_path('routes/api/gastos_proyectados.php');
    require base_path('routes/api/areas.php');
});
