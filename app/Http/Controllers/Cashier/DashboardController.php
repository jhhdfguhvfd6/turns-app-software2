<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\Turn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Helpers\AuditHelper;

class DashboardController extends Controller
{
    public function index()
    {
        $cashier = Auth::user();

        // Busca el turno que el cajero podría estar atendiendo actualmente
        $currentTurn = Turn::where('assigned_cashier_id', $cashier->_id)
                            ->where('status', 'attending')
                            ->with('user', 'service')
                            ->first();

        // Busca todos los turnos en espera (sin filtrar por servicio por ahora)
        $pendingTurns = Turn::where('status', 'waiting')
                            ->with('user', 'service')
                            ->orderBy('created_at', 'asc')
                            ->get();

        return view('cashier.dashboard', compact('currentTurn', 'pendingTurns'));
    }

    /**
     * Llama al siguiente turno en la cola
     */
    public function callNext()
    {
        $cashier = Auth::user();

        // Verificar si ya está atendiendo a alguien
        $currentTurn = Turn::where('assigned_cashier_id', $cashier->_id)
                            ->where('status', 'attending')
                            ->first();

        if ($currentTurn) {
            return redirect()->route('cashier.dashboard')
                ->with('error', 'Debes finalizar la atención actual antes de llamar al siguiente');
        }

        // Buscar el siguiente turno en espera (sin filtrar por servicio)
        $nextTurn = Turn::where('status', 'waiting')
                        ->orderBy('created_at', 'asc')
                        ->first();

        if (!$nextTurn) {
            return redirect()->route('cashier.dashboard')
                ->with('info', 'No hay turnos pendientes en la cola');
        }

        // Asignar el turno al cajero y cambiar estado
        $nextTurn->assigned_cashier_id = $cashier->_id;
        $nextTurn->status = 'attending';
        $nextTurn->called_at = now();
        $nextTurn->save();

        // Registrar auditoría
        AuditHelper::log('call_turn', "Cajero llamó al turno {$nextTurn->turn_code}");

        return redirect()->route('cashier.dashboard')
            ->with('success', "Turno {$nextTurn->turn_code} llamado");
    }

    /**
     * Finaliza la atención del turno actual
     */
    public function finishCurrent()
    {
        $cashier = Auth::user();

        // Buscar el turno que está atendiendo
        $currentTurn = Turn::where('assigned_cashier_id', $cashier->_id)
                            ->where('status', 'attending')
                            ->first();

        if (!$currentTurn) {
            return redirect()->route('cashier.dashboard')
                ->with('error', 'No hay ningún turno en atención');
        }

        // Finalizar el turno
        $currentTurn->status = 'completed';
        $currentTurn->finished_at = now();
        $currentTurn->save();

        // Registrar auditoría
        AuditHelper::log('finish_turn', "Cajero finalizó el turno {$currentTurn->turn_code}");

        return redirect()->route('cashier.dashboard')
            ->with('success', "Turno {$currentTurn->turn_code} finalizado");
    }
}