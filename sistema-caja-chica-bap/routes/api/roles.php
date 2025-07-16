<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\RoleController;

/*
|--------------------------------------------------------------------------
| Rutas de API para la Gestión de Roles
|--------------------------------------------------------------------------
| Este endpoint se utiliza para obtener la lista de roles en los paneles
| de administración.
*/

Route::get('/roles', [RoleController::class, 'index']);
