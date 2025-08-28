<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AreaController;

/*
|--------------------------------------------------------------------------
| Rutas de API para la Administración de Áreas
|--------------------------------------------------------------------------
| El endpoint 'index' para la lista pública se define en 'recursos.php'.
| Las siguientes rutas son para la gestión y la autorización se maneja
| directamente dentro del AreaController para ser consistente.
*/

/**
 * Rutas para el CRUD (store, show, update, destroy) de Áreas.
 * Se excluye 'index' porque ya está definido de forma pública.
 */
Route::apiResource('areas', AreaController::class)
    ->parameters(['areas' => 'area'])
    ->except(['index']);

/**
 * Ruta para activar un área que fue desactivada.
 */
Route::post('areas/{area}/activate', [AreaController::class, 'activate']);
