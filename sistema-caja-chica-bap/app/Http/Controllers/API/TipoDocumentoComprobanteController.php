<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\TipoDocumentoComprobante;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class TipoDocumentoComprobanteController extends Controller
{
    public function index(Request $request)
    {
        $query = TipoDocumentoComprobante::query()->orderBy('codigo_comprobante');

        if ($request->has('activo') && $request->activo !== '') {
            $query->where('activo', (bool)$request->activo);
        } else if ($request->query('scope') !== 'management') {
            $query->where('activo', true);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        if (!Auth::user()->hasAnyRole(['jefe_administracion', 'super_admin'])) {
            return response()->json(['message' => 'Acción no autorizada.'], 403);
        }

        $validatedData = $request->validate([
            'nombre' => 'required|string|max:100|unique:tipos_documento_comprobante,nombre',
            'codigo_comprobante' => 'required|string|max:5|unique:tipos_documento_comprobante,codigo_comprobante',
            'activo' => 'sometimes|boolean',
        ]);

        $tipoDoc = TipoDocumentoComprobante::create($validatedData);

        return response()->json([
            'message' => 'Tipo de documento creado exitosamente.',
            'tipo_documento' => $tipoDoc
        ], 201);
    }

    public function update(Request $request, TipoDocumentoComprobante $tipoDocumentoComprobante)
    {
        if (!Auth::user()->hasAnyRole(['jefe_administracion', 'super_admin'])) {
            return response()->json(['message' => 'Acción no autorizada.'], 403);
        }

        $validatedData = $request->validate([
            'nombre' => ['required', 'string', 'max:100', Rule::unique('tipos_documento_comprobante')->ignore($tipoDocumentoComprobante->id)],
            'codigo_comprobante' => ['required', 'string', 'max:5', Rule::unique('tipos_documento_comprobante')->ignore($tipoDocumentoComprobante->id)],
            'activo' => 'sometimes|boolean',
        ]);

        $tipoDocumentoComprobante->update($validatedData);

        return response()->json([
            'message' => 'Tipo de documento actualizado exitosamente.',
            'tipo_documento' => $tipoDocumentoComprobante
        ]);
    }

    public function destroy(TipoDocumentoComprobante $tipoDocumentoComprobante)
    {
        if (!Auth::user()->hasAnyRole(['jefe_administracion', 'super_admin'])) {
            return response()->json(['message' => 'Acción no autorizada.'], 403);
        }

        if ($tipoDocumentoComprobante->gastos()->count() > 0) {
            return response()->json([
                'message' => 'No se puede desactivar: Este tipo de documento está siendo utilizado en uno o más gastos.'
            ], 409);
        }

        $tipoDocumentoComprobante->activo = false;
        $tipoDocumentoComprobante->save();

        return response()->json(['message' => 'Tipo de documento desactivado exitosamente.']);
    }

    public function activate(TipoDocumentoComprobante $tipoDocumentoComprobante)
    {
        if (!Auth::user()->hasAnyRole(['jefe_administracion', 'super_admin'])) {
            return response()->json(['message' => 'Acción no autorizada.'], 403);
        }

        $tipoDocumentoComprobante->activo = true;
        $tipoDocumentoComprobante->save();

        return response()->json([
            'message' => 'Tipo de documento activado exitosamente.',
            'tipo_documento' => $tipoDocumentoComprobante
        ]);
    }
}
