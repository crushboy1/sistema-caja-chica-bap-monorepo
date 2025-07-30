<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\DashboardController;

/*
|--------------------------------------------------------------------------
| Rutas del Dashboard
|--------------------------------------------------------------------------
|
| Endpoints para obtener los datos consolidados para el dashboard interactivo.
|
*/

//  ruta GET que apunta al método principal del controlador.

Route::get('/dashboard', [DashboardController::class, 'getDashboardData'])
    ->name('dashboard.data')
    ->middleware('check.permission:navigate.dashboard');

