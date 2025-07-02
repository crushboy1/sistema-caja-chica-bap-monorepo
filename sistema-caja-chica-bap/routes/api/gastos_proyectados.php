<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\GastoProyectadoController;

/*
|--------------------------------------------------------------------------
| Rutas de API para la Administración de Gastos Proyectados
|--------------------------------------------------------------------------
*/

// Rutas protegidas por rol para el CRUD de Gastos Proyectados.
Route::middleware(['role:jefe_administracion|super_admin'])->group(function () {
    // El método 'index' se define en recursos.php, por lo que se excluye aquí.
    Route::apiResource('gastos-proyectados', GastoProyectadoController::class)->except(['index']);
});
