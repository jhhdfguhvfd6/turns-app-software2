<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendAccessPin;
use App\Models\User;
use App\Services\NotificationMicroservice;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use App\Helpers\AuditHelper;

class LoginController extends Controller
{
    /**
     * Muestra el formulario de login.
     */
    public function showLoginForm()
    {
        return view('login');
    }

    /**
     * Maneja la solicitud de envío de PIN.
     */
    public function sendPin(Request $request)
    {
        // ... (Tu código de sendPin sigue igual) ...
        
        // 1. Validar que tengamos un número de documento
        $credentials = $request->validate([
            'document_number' => 'required|string|max:20',
        ]);

        // 2. Encontrar al usuario por su número de documento
        $user = User::where('document_number', $credentials['document_number'])->first();

        // 3. Si el usuario no existe, regresar con un error
        if (!$user) {
            return redirect()->back()->withErrors([
                'document_number' => 'El número de documento no se encuentra registrado.',
            ])->withInput($request->only('document_number'));
        }

        // 4. Generar y guardar el PIN
        $pin = random_int(100000, 999999); // PIN de 6 dígitos

        $user->pin = $pin;
        $user->pin_expires_at = now()->addMinutes(3);
        $user->save();

        // 5. Determinar email (soporta email como string o array)
        $email = null;
        if (is_array($user->email)) {
            $email = $user->email[0] ?? null;
        } else {
            $email = $user->email;
        }

        if (empty($email)) {
            Log::error('FALLO AL ENVIAR EMAIL: usuario sin email. user_id=' . $user->getKey());
            return redirect()->back()->withErrors(['document_number' => 'No se pudo enviar el correo: email no disponible.']);
        }

        // 6. Enviar el email con el PIN (primero intenta vía microservicio, fallback al mailer de Laravel)
        $sentViaMicroservice = app(NotificationMicroservice::class)
            ->sendPin($email, (string) $pin, $user->name);

        if (!$sentViaMicroservice) {
            try {
                Mail::to($email)->send(new SendAccessPin($pin));
                Log::info('EMAIL ENVIADO (Laravel mailer) a ' . $email);
            } catch (\Exception $e) {
                Log::error('FALLO AL ENVIAR EMAIL: ' . $e->getMessage());
                Log::error('Stack trace: ' . $e->getTraceAsString());
            }
        }

        // 7. Log de depuración (solo para desarrollo)
        Log::info('PIN DE ACCESO (DEPURACIÓN) PARA ' . $email . ': ' . $pin);

        // Registrar auditoría de solicitud de PIN
        AuditHelper::log('pin_request', "Usuario {$user->name} solicitó PIN de acceso");

        // 8. Desconectar cualquier sesión activa
        Auth::logout();

        // 9. Guardar los datos necesarios en la nueva sesión para el siguiente paso
        Session::put('user_id_for_pin_verification', $user->getKey());
        Session::put('email_for_pin_verification', $email);
        Session::save();

        // 10. Redirigir a la vista de verificación de PIN
        return redirect()->route('verify-pin');
    }

    /**
     * Muestra el formulario de verificación de PIN.
     */
    public function showPinForm()
    {
        if (!Session::has('user_id_for_pin_verification')) {
            return redirect()->route('login')->withErrors(['document_number' => 'Por favor, introduce tu documento primero.']);
        }

        $email = Session::get('email_for_pin_verification');

        return view('verify-pin', ['email' => $email]);
    }

    /**
     * Verifica el PIN ingresado.
     */
    public function verifyPin()
    {
        // Validación de 6 dígitos
        request()->validate([
            'pin' => 'required|numeric|digits:6',
        ]);

        $userId = Session::pull('user_id_for_pin_verification');
        $email = Session::pull('email_for_pin_verification');

        if (!$userId) {
            return redirect()->route('login')->withErrors(['document_number' => 'Tu sesión ha expirado. Por favor, inicia sesión de nuevo.']);
        }

        $user = User::find($userId);

        $pinInput = (string) request('pin');

        if (!$user || (string)$user->pin !== $pinInput) {
            // Si el PIN es incorrecto, lo re-guarda en sesión para reintentar
            Session::put('user_id_for_pin_verification', $userId);
            Session::put('email_for_pin_verification', $email);
            return redirect()->back()->withErrors(['pin' => 'El PIN ingresado es incorrecto.']);
        }

        if (now()->gt($user->pin_expires_at)) {
            // Limpiar PIN expirado
            $user->pin = null;
            $user->pin_expires_at = null;
            $user->save(); // <-- ESTO TAMBIÉN REGISTRA UN "updated"
            return redirect()->route('login')->withErrors(['document_number' => 'El PIN ha expirado. Por favor, solicita uno nuevo.']);
        }

        // Limpiar PIN y loguear
        $user->pin = null;
        $user->pin_expires_at = null;
        $user->save(); // <-- Y ESTO TAMBIÉN REGISTRA UN "updated"

        Auth::login($user);
        request()->session()->regenerate();

        // Registrar auditoría
        AuditHelper::log('login', 'El usuario inició sesión');

        return redirect()->intended($this->redirectPath());
    }

    /**
     * Determina a dónde redirigir después del login.
     */
    protected function redirectPath()
    {
        $user = Auth::user();
        if ($user && $user->role === 'admin') {
            return '/admin/dashboard';
        } elseif ($user && $user->role === 'cashier') {
            return '/cashier/dashboard';
        }

        return '/dashboard';
    }

    /**
     * Cierra la sesión del usuario.
     */
    public function logout(Request $request)
    {
        // Registrar auditoría antes de cerrar sesión
        AuditHelper::log('logout', 'El usuario cerró sesión');
        
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        return redirect('/');
    }
}