<?php

namespace App\Http\Controllers;

use App\Events\TurnQueueUpdated;
use App\Helpers\AuditHelper;
use App\Models\Service;
use App\Models\Turn;
use App\Services\NotificationMicroservice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TurnController extends Controller
{
    /**
     * Crea un nuevo turno en la cola para el usuario autenticado.
     */
    public function store(Request $request)
    {
        $request->validate([
            'service_id' => 'required|string',
        ]);

        $user = Auth::user();

        // Evitar que el usuario tenga dos turnos activos al mismo tiempo
        $existingTurn = Turn::where('user_id', $user->_id)
                            ->whereIn('status', ['waiting', 'attending'])
                            ->first();

        if ($existingTurn) {
            return redirect()->route('dashboard')
                ->with('error', 'Ya tienes un turno activo: ' . $existingTurn->turn_code);
        }

        $service = Service::findOrFail($request->service_id);

        // Generar número correlativo para este servicio
        $lastTurn = Turn::where('service_id', $service->_id)
                        ->orderBy('turn_number', 'desc')
                        ->first();

        $nextNumber = $lastTurn ? ($lastTurn->turn_number + 1) : 1;
        $prefix     = $service->prefix ?? strtoupper(substr($service->name, 0, 1));
        $turnCode   = $prefix . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

        $turn = Turn::create([
            'user_id'    => $user->_id,
            'service_id' => $service->_id,
            'turn_code'  => $turnCode,
            'turn_number'=> $nextNumber,
            'status'     => 'waiting',
        ]);

        // Transmitir actualización de la cola por WebSocket
        $publicTurns = Turn::whereIn('status', ['waiting', 'attending'])
                           ->with('service')
                           ->orderBy('status', 'desc')
                           ->orderBy('created_at', 'asc')
                           ->get();
        broadcast(new TurnQueueUpdated($publicTurns))->toOthers();

        AuditHelper::log('create_turn', "Usuario solicitó turno {$turnCode} para servicio {$service->name}");

        // Notificar al usuario por email vía microservicio (sin bloquear si falla)
        $email = is_array($user->email) ? ($user->email[0] ?? null) : $user->email;
        if ($email) {
            app(NotificationMicroservice::class)
                ->sendTurnConfirmation($email, $user->name, $turnCode, $service->name);
        }

        return redirect()->route('dashboard')
            ->with('success', "Tu turno es: {$turnCode}");
    }

    /**
     * Cancela un turno del usuario autenticado.
     */
    public function cancel(Turn $turn)
    {
        $user = Auth::user();

        // Solo el dueño del turno puede cancelarlo
        if ($turn->user_id !== $user->_id) {
            abort(403, 'No tienes permiso para cancelar este turno.');
        }

        if (!in_array($turn->status, ['waiting', 'attending'])) {
            return redirect()->route('dashboard')
                ->with('error', 'Este turno ya no se puede cancelar.');
        }

        $turnCode = $turn->turn_code;
        $turn->status = 'cancelled';
        $turn->save();

        // Transmitir actualización de la cola por WebSocket
        $publicTurns = Turn::whereIn('status', ['waiting', 'attending'])
                           ->with('service')
                           ->orderBy('status', 'desc')
                           ->orderBy('created_at', 'asc')
                           ->get();
        broadcast(new TurnQueueUpdated($publicTurns))->toOthers();

        AuditHelper::log('cancel_turn', "Usuario canceló el turno {$turnCode}");

        return redirect()->route('dashboard')
            ->with('success', "Turno {$turnCode} cancelado.");
    }
}
