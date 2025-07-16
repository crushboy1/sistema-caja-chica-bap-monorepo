<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Proyecto;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class ProyectoController extends Controller
{
    /**
     * Muestra una lista de proyectos.
     * Acepta un parámetro 'scope' para diferenciar la vista de administración de los selectores.
     */
    public function index(Request $request)
    {
        $query = Proyecto::orderBy('nombre');

        if ($request->query('scope') === 'management') {
        
        } else {

            $query->where('activo', true);
        }

        $proyectos = $query->get();

        return response()->json([
            'proyectos' => $proyectos
        ]);
    }

    /**
     * Almacena un nuevo proyecto en la base de datos.
     */
    public function store(Request $request)
    {
        if (!Auth::user()->hasAnyRole(['jefe_administracion', 'super_admin'])) {
            return response()->json(['message' => 'Acción no autorizada.'], 403);
        }
        $validatedData = $request->validate([
            'codigo' => 'required|string|max:255|unique:proyectos,codigo',
            'nombre' => 'required|string|max:255',
            'activo' => 'sometimes|boolean',
        ]);

        $proyecto = Proyecto::create($validatedData);

        return response()->json([
            'message' => 'Proyecto creado exitosamente.',
            'proyecto' => $proyecto
        ], 201);
    }

    /**
     * Actualiza un proyecto específico.
     * Se restringe la edición del campo 'codigo' para mantener la integridad.
     */
    public function update(Request $request, Proyecto $proyecto)
    {
        if (!Auth::user()->hasAnyRole(['jefe_administracion', 'super_admin'])) {
            return response()->json(['message' => 'Acción no autorizada.'], 403);
        }
        $validatedData = $request->validate([
            'nombre' => 'required|string|max:255',
            'activo' => 'sometimes|boolean',
            
        ]);

        $proyecto->update($validatedData);

        return response()->json([
            'message' => 'Proyecto actualizado exitosamente.',
            'proyecto' => $proyecto
        ]);
    }

    /**
     * Desactiva un proyecto (soft delete) tras validar que no esté en uso.
     */
    public function destroy(Proyecto $proyecto)
    {
        if (!Auth::user()->hasAnyRole(['jefe_administracion', 'super_admin'])) {
            return response()->json(['message' => 'Acción no autorizada.'], 403);
        }

        // Verificar si el proyecto está asociado a algún FondoEfectivo ACTIVO.
        $fondosActivosAsociados = $proyecto->fondosEfectivo()->where('estado', 'Activo')->count();

        if ($fondosActivosAsociados > 0) {
            return response()->json([
                'message' => "No se puede desactivar: El proyecto está asociado a {$fondosActivosAsociados} fondo(s) de caja chica activo(s)."
            ], 409); 
        }

        $proyecto->activo = false;
        $proyecto->save();

        return response()->json(['message' => 'Proyecto desactivado exitosamente.']);
    }

    /**
     * Activa un proyecto que fue desactivado.
     */
    public function activate(Proyecto $proyecto)
    {
        if (!Auth::user()->hasAnyRole(['jefe_administracion', 'super_admin'])) {
            return response()->json(['message' => 'Acción no autorizada.'], 403);
        }

        $proyecto->activo = true;
        $proyecto->save();

        return response()->json([
            'message' => 'Proyecto activado exitosamente.',
            'proyecto' => $proyecto
        ]);
    }
}
