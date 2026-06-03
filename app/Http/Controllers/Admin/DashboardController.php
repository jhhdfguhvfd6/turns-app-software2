<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Turn;
use App\Models\Service;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Muestra el dashboard principal de administración.
     */
    public function index()
    {
        // Estadísticas generales
        $totalUsers = User::count();
        $totalCashiers = User::where('role', 'cashier')->count();
        $totalServices = Service::where('is_active', true)->count();
        
        // Turnos de hoy
        $today = Carbon::today();
        $turnsToday = Turn::whereDate('created_at', $today)->count();
        
        // Turnos activos (en espera o siendo atendidos)
        $activeTurns = Turn::whereIn('status', ['waiting', 'attending'])->count();
        
        // Turnos completados hoy
        $completedToday = Turn::whereDate('created_at', $today)
                              ->where('status', 'completed')
                              ->count();
        
        // Últimos usuarios registrados
        $recentUsers = User::latest()->take(5)->get();
        
        // Turnos recientes
        $recentTurns = Turn::with(['user', 'service'])
                           ->latest()
                           ->take(10)
                           ->get();

        return view('admin.dashboard', compact(
            'totalUsers',
            'totalCashiers',
            'totalServices',
            'turnsToday',
            'activeTurns',
            'completedToday',
            'recentUsers',
            'recentTurns'
        ));
    }
}