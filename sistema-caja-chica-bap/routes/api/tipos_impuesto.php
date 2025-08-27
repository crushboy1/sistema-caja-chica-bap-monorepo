<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\TipoImpuestoController;

// Rutas para el CRUD de Tipos de Impuesto
Route::apiResource('tipos-impuesto', TipoImpuestoController::class)
    ->parameters(['tipos-impuesto' => 'tipoImpuesto'])
    ->except(['index']);

Route::post('tipos-impuesto/{tipoImpuesto}/activate', [TipoImpuestoController::class, 'activate']);
