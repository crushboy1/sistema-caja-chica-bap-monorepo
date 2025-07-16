<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\CuentaContableController;

/*
|--------------------------------------------------------------------------
| Rutas de API para la Administración de Cuentas Contables
|--------------------------------------------------------------------------
*/

Route::apiResource('cuentas-contables', CuentaContableController::class)
    ->parameters(['cuentas-contables' => 'cuentaContable'])
    ->except(['index']);
Route::post('cuentas-contables/{cuentaContable}/activate', [CuentaContableController::class, 'activate']);
