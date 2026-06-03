<?php

namespace App\Http\Controllers;

use App\Models\Turn;
use Illuminate\Http\Request;

class WelcomeController extends Controller
{
    public function index()
    {
        // Obtener los turnos que están siendo atendidos actualmente
        $currentTurns = Turn::where('status', 'attending')
                            ->with('service')
                            ->orderBy('called_at', 'desc')
                            ->take(1)
                            ->get();

        // Obtener los próximos turnos en espera
        $waitingTurns = Turn::where('status', 'waiting')
                            ->with('service')
                            ->orderBy('created_at', 'asc')
                            ->take(5)
                            ->get();

        return view('welcome', compact('currentTurns', 'waitingTurns'));
    }
}
