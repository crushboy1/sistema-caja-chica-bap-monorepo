<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\ProyectoController;

/*
|--------------------------------------------------------------------------
| Rutas de API para la Administración de Areas
|--------------------------------------------------------------------------
| Estas rutas ya están protegidas por 'auth:sanctum' desde api.php.
*/

// Rutas protegidas por rol para el CRUD de Proyectos.
Route::middleware(['role:jefe_administracion|super_admin'])->group(function () {
    // El método 'index' no se incluye aquí porque se define en recursos.php sin restricción de rol.
    Route::apiResource('areas', ProyectoController::class)->except(['index']);
});
