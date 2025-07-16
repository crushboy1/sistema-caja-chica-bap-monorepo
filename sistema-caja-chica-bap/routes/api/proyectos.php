<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\ProyectoController;

/*
|--------------------------------------------------------------------------
| Rutas de API para la Administración de Proyectos
|--------------------------------------------------------------------------
*/

// El apiResource ya está protegido por 'auth:sanctum' desde api.php.
Route::apiResource('proyectos', ProyectoController::class)->except(['index']);
Route::post('proyectos/{proyecto}/activate', [ProyectoController::class, 'activate']);