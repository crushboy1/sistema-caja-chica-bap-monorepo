<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\CierreController;
use App\Http\Controllers\API\ExcepcionController;
use App\Http\Controllers\API\ActivityLogController;

/*
|--------------------------------------------------------------------------
| Rutas del Módulo de Administración
|--------------------------------------------------------------------------
*/

//==========================================================================
// GESTIÓN DE CIERRES CONTABLES
//==========================================================================
Route::controller(CierreController::class)
    ->prefix('cierres-mensuales')
    ->middleware('check.permission:admin.cierres.manage')
    ->group(function () {
        Route::get('/', 'index');
        Route::put('/', 'update');
    });

//==========================================================================
// GESTIÓN DE EXCEPCIONES DE CIERRE
//==========================================================================
Route::controller(ExcepcionController::class)
    ->middleware('check.permission:admin.excepciones.manage')
    ->group(function () {
        Route::get('/cierres-mensuales/{cierre}/excepciones', 'index')->name('cierres.excepciones.index');
        Route::post('/excepciones-cierre', 'store');
        Route::delete('/excepciones-cierre/{excepcion}', 'destroy');
    });

//==========================================================================
// LOG DE AUDITORÍA
//==========================================================================
//  Se agrupan las rutas de auditoría para aplicar el prefijo
// y el middleware de permisos de forma centralizada.
Route::middleware(['check.permission:admin.audit.view'])
    ->prefix('activity-logs')
    ->name('activity-logs.')
    ->group(function () {
        Route::get('/', [ActivityLogController::class, 'index']);
        Route::get('/stats', [ActivityLogController::class, 'stats']);
        Route::get('/export', [ActivityLogController::class, 'export']);
        Route::get('/filter-options', [ActivityLogController::class, 'filterOptions']);
        Route::get('/{id}', [ActivityLogController::class, 'show']);
    });
