<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ExcepcionCierre;
use App\Models\CierreMensual;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ExcepcionController extends Controller
{
    public function index(CierreMensual $cierre)
    {
        $excepciones = $cierre->excepciones()->with([
            'usuarioExcepcion:id,name,last_name',
            'usuarioOtorga:id,name,last_name'
        ])->get();

        return response()->json($excepciones);
    }
    /**
     * Otorga una nueva excepción a un usuario para un período cerrado.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'periodo' => 'required|date_format:Y-m',
            'id_usuario_excepcion' => 'required|exists:users,id',
            'fecha_expiracion' => 'required|date|after_or_equal:today',
            'motivo' => 'required|string|max:1000',
        ]);

        $user = Auth::user();
        $fechaPeriodo = Carbon::createFromFormat('Y-m', $validated['periodo'])->startOfMonth();

        // Se busca el CierreMensual correspondiente o se crea si no existe.
        $cierreMensual = CierreMensual::firstOrCreate(
            ['periodo' => $fechaPeriodo],
            ['estado' => 'Abierto', 'id_usuario_accion' => $user->id]
        );

        // No se puede dar una excepción a un período que está abierto.
        if ($cierreMensual->estado === 'Abierto') {
            return response()->json(['message' => 'No se puede crear una excepción para un período que aún está abierto.'], 409);
        }

        $excepcion = ExcepcionCierre::create([
            'id_cierre_mensual' => $cierreMensual->id,
            'id_usuario_excepcion' => $validated['id_usuario_excepcion'],
            'id_usuario_otorga' => $user->id,
            'fecha_expiracion' => $validated['fecha_expiracion'],
            'motivo' => $validated['motivo'],
        ]);

        $usuarioExcepcion = User::find($validated['id_usuario_excepcion']);
        Log::info("El admin {$user->name} otorgó una excepción al usuario {$usuarioExcepcion->name} para el período {$validated['periodo']}.");

        return response()->json([
            'message' => 'Excepción otorgada exitosamente.',
            'excepcion' => $excepcion->load(['usuarioExcepcion:id,name,last_name']),
        ], 201);
    }

    /**
     * Revoca (elimina) una excepción existente.
     */
    public function destroy(ExcepcionCierre $excepcion)
    {
        $user = Auth::user();
        $excepcion->delete();

        Log::info("El admin {$user->name} revocó la excepción ID {$excepcion->id}.");

        return response()->json(['message' => 'La excepción ha sido revocada exitosamente.']);
    }
}
