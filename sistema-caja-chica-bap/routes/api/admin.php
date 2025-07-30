<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\CierreController;
use App\Http\Controllers\API\ExcepcionController;

// Rutas para gestionar los cierres mensuales
Route::prefix('cierres-mensuales')->group(function () {
    Route::get('/', [CierreController::class, 'index'])->middleware('check.permission:admin.cierres.manage');
    Route::put('/', [CierreController::class, 'update'])->middleware('check.permission:admin.cierres.manage');
});

/**
 * COMENTARIO BAP: INICIO DE LA CORRECCIÓN
 * Se añade la ruta GET que faltaba para poder listar las excepciones de un cierre.
 * Se usa el route model binding de Laravel ('{cierre}') para pasar automáticamente
 * el objeto CierreMensual al método index del controlador.
 */
Route::get('/cierres-mensuales/{cierre}/excepciones', [ExcepcionController::class, 'index'])
    ->name('cierres.excepciones.index')
    ->middleware('check.permission:admin.excepciones.manage');
// COMENTARIO BAP: FIN DE LA CORRECCIÓN

// Rutas para gestionar las excepciones de cierre
Route::prefix('excepciones-cierre')->group(function () {
    Route::post('/', [ExcepcionController::class, 'store'])->middleware('check.permission:admin.excepciones.manage');
    Route::delete('/{excepcion}', [ExcepcionController::class, 'destroy'])->middleware('check.permission:admin.excepciones.manage');
});
