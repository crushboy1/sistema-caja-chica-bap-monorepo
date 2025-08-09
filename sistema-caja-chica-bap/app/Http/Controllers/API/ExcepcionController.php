<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use App\Models\ExcepcionCierre;
use App\Models\CierreMensual;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ExcepcionController extends Controller
{
    protected $activityLogService;
    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }

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
        $properties = [
            'descripcion' => "Otorgó una excepción al usuario '{$usuarioExcepcion->name} {$usuarioExcepcion->last_name}' para el período {$validated['periodo']}.",
            'usuario_excepcion' => [
                'id' => $usuarioExcepcion->id,
                'nombre' => $usuarioExcepcion->name . ' ' . $usuarioExcepcion->last_name,
            ],
            'periodo' => $validated['periodo'],
            'fecha_expiracion' => $validated['fecha_expiracion'],
            'motivo' => $validated['motivo'],
        ];

        $this->activityLogService->log('EXCEPCION_OTORGADA', $excepcion, $properties, $user);

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
        $excepcion->load('usuarioExcepcion:id,name,last_name', 'cierreMensual:id,periodo');
        $properties = [
            'descripcion' => "Revocó la excepción otorgada al usuario '{$excepcion->usuarioExcepcion->name} {$excepcion->usuarioExcepcion->last_name}' para el período " . Carbon::parse($excepcion->cierreMensual->periodo)->format('Y-m') . ".",
            'usuario_excepcion' => [
                'id' => $excepcion->usuarioExcepcion->id,
                'nombre' => $excepcion->usuarioExcepcion->name . ' ' . $excepcion->usuarioExcepcion->last_name,
            ],
            'periodo' => Carbon::parse($excepcion->cierreMensual->periodo)->format('Y-m'),
            'motivo_original' => $excepcion->motivo,
        ];

        $this->activityLogService->log('EXCEPCION_REVOCADA', $excepcion, $properties, $user);

        $excepcion->delete();

        return response()->json(['message' => 'La excepción ha sido revocada exitosamente.']);
    }
}
