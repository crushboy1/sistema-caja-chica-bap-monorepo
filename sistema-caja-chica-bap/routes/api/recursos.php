<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AreaController;
use App\Http\Controllers\API\CuentaContableController;
use App\Http\Controllers\API\DocumentoController;

Route::middleware('auth:sanctum')->group(function () {
    // Listas para Selectores
    Route::get('/areas', [AreaController::class, 'index']);
    Route::get('/cuentas-contables', [CuentaContableController::class, 'index']);

    // Generación de Documentos
    Route::post('/documentos/generar-dj', [DocumentoController::class, 'generarDJ']);

    // Endpoint de salud del sistema
    Route::get('/health', fn() => response()->json(['status' => 'OK']));
});
