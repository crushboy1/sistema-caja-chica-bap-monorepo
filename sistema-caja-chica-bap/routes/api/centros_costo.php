<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\CentroCostoController;

/*
|--------------------------------------------------------------------------
| Rutas de API para la Administración de Centros de Costo
|--------------------------------------------------------------------------
*/

/**
 * Rutas para el CRUD (store, show, update, destroy) de Centros de Costo.
 */
Route::apiResource('centros-costo', CentroCostoController::class)
    ->parameters(['centros-costo' => 'centroCosto'])
    ->except(['index']);

/**
 * Ruta para activar un centro de costo que fue desactivado.
 */
Route::post('centros-costo/{centroCosto}/activate', [CentroCostoController::class, 'activate']);
