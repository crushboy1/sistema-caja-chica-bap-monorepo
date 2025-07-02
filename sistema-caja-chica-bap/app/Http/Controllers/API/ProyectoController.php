<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Proyecto;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProyectoController extends Controller
{
    /**
     * Muestra una lista de todos los proyectos activos.
     * Este endpoint es para que el frontend pueda poblar los selectores.
     */
    public function index()
    {
        $proyectos = Proyecto::where('activo', true)->orderBy('nombre_proyecto')->get();
        return response()->json([
            'proyectos' => $proyectos
        ]);
    }

    /**
     * Almacena un nuevo proyecto en la base de datos.
     * Protegido por middleware de rol en el archivo de rutas.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'nombre_proyecto' => 'required|string|max:255|unique:proyectos,nombre_proyecto',
            'descripcion' => 'nullable|string',
            'presupuesto' => 'nullable|numeric|min:0',
            'activo' => 'boolean',
        ]);

        $proyecto = Proyecto::create($validatedData);

        return response()->json([
            'message' => 'Proyecto creado exitosamente.',
            'proyecto' => $proyecto
        ], 201);
    }

    /**
     * Actualiza un proyecto específico.
     * Protegido por middleware de rol en el archivo de rutas.
     */
    public function update(Request $request, Proyecto $proyecto)
    {
        $validatedData = $request->validate([
            'nombre_proyecto' => [
                'required',
                'string',
                'max:255',
                Rule::unique('proyectos')->ignore($proyecto->id_proyecto, 'id_proyecto'),
            ],
            'descripcion' => 'nullable|string',
            'presupuesto' => 'nullable|numeric|min:0',
            'activo' => 'boolean',
        ]);

        $proyecto->update($validatedData);

        return response()->json([
            'message' => 'Proyecto actualizado exitosamente.',
            'proyecto' => $proyecto
        ]);
    }

    /**
     * Desactiva un proyecto.
     * Protegido por middleware de rol en el archivo de rutas.
     */
    public function destroy(Proyecto $proyecto)
    {
        $proyecto->activo = false;
        $proyecto->save();

        return response()->json(['message' => 'Proyecto desactivado exitosamente.']);
    }
}
