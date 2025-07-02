<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\CuentaContableController;

/*
|--------------------------------------------------------------------------
| Rutas de API para la Administración de Cuentas Contables
|--------------------------------------------------------------------------
*/

// Rutas protegidas por rol para el CRUD de Cuentas Contables.
Route::middleware(['role:jefe_administracion|super_admin'])->group(function () {
    // La ruta 'index' ya está definida en recursos.php, por lo que la excluimos aquí.
    Route::apiResource('cuentas-contables', CuentaContableController::class)->except(['index']);
});
