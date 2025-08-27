<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\TipoDocumentoComprobanteController;

// Rutas para el CRUD de Tipos de Documento de Comprobante
Route::apiResource('tipos-documento-comprobante', TipoDocumentoComprobanteController::class)
    ->parameters(['tipos-documento-comprobante' => 'tipoDocumentoComprobante'])
    ->except(['index']);

Route::post('tipos-documento-comprobante/{tipoDocumentoComprobante}/activate', [TipoDocumentoComprobanteController::class, 'activate']);
