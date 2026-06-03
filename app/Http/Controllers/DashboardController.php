<?php

// ¡Namespace corregido con 'C' mayúscula!
namespace App\Http\Controllers; 

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Service;
use App\Models\Turn;

class DashboardController extends Controller
{
    /**
     * Muestra el dashboard principal del usuario.
     */
    public function index()
    {
        $user_id = Auth::id();

        // 1. Obtener todos los servicios
        $services = Service::all();

        // 2. Obtener el turno activo del usuario
        $myTurn = Turn::where('user_id', $user_id)
                      ->whereIn('status', ['waiting', 'attending'])
                      ->with('service') // Carga la relación del servicio
                      ->first();
        
        // 3. Obtener la cola pública de turnos
        $publicTurns = Turn::whereIn('status', ['waiting', 'attending'])
                            ->with('service') // Carga la relación del servicio
                            ->orderBy('status', 'desc') // 'attending' primero
                            ->orderBy('created_at', 'asc') // El más antiguo primero
                            ->take(10) // Limitar a 10 para la pantalla
                            ->get();

        // 4. Calcular turnos por delante (si el usuario tiene un turno)
        $turnsAhead = 0;
        if ($myTurn && $myTurn->status == 'waiting') {
            // Contar turnos del mismo servicio que están siendo atendidos o en espera antes que el mío
            $turnsAhead = Turn::where('service_id', $myTurn->service_id)
                              ->whereIn('status', ['waiting', 'attending'])
                              ->where('created_at', '<', $myTurn->created_at)
                              ->count();
        }

        // 5. Pasar todos los datos a la vista
        return view('dashboard', [
            'services' => $services,
            'myTurn' => $myTurn,
            'publicTurns' => $publicTurns,
            'turnsAhead' => $turnsAhead,
        ]);
    }
}