<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\DashboardDataRequest;
use App\Services\DashboardService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
class DashboardController extends Controller
{
    /**
     * Obtiene todos los datos necesarios para el dashboard, adaptados al rol del usuario.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function __construct(private DashboardService $dashboardService) {

    }

    public function getDashboardData(DashboardDataRequest $request): JsonResponse
    {
        // 4. La validación ahora es automática gracias a DashboardDataRequest.
        $validatedData = $request->validated();
        $user = Auth::user();

        // 5. El controlador solo delega el trabajo al servicio. ¡Limpio!
        $data = $this->dashboardService->generateDashboardData($validatedData, $user);

        return response()->json($data);
    }
}

