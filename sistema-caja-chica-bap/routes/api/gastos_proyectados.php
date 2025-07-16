<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\GastoProyectadoController;

/*
|--------------------------------------------------------------------------
| Rutas de API para la Administración de Gastos Proyectados
|--------------------------------------------------------------------------
*/

Route::apiResource('gastos-proyectados', GastoProyectadoController::class)
    ->parameters(['gastos-proyectados' => 'gastoProyectado'])
    ->except(['index']);
Route::post('gastos-proyectados/{gastoProyectado}/activate', [GastoProyectadoController::class, 'activate']);
