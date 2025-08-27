<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\ClasificacionBienServicioController;

// Rutas para el CRUD de Clasificación de Bienes y Servicios
Route::apiResource('clasificaciones', ClasificacionBienServicioController::class)
    ->parameters(['clasificaciones' => 'clasificacion'])
    ->except(['index']);

Route::post('clasificaciones/{clasificacion}/activate', [ClasificacionBienServicioController::class, 'activate']);
