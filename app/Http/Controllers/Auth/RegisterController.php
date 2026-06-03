<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Helpers\AuditHelper;

class RegisterController extends Controller
{
    /**
     * Muestra el formulario de registro.
     */
    public function showRegistrationForm()
    {
        return view('register');
    }

    /**
     * Maneja la solicitud de registro.
     */
    public function register(Request $request)
    {
        // 1. Validación de los datos de entrada
        $request->validate([
            'name' => 'required|string|max:255',
            'document_number' => 'required|string|max:20|unique:users,document_number',
            'email' => 'required|string|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:15',
        ]);

        // 2. Creación del nuevo usuario
        $user = User::create([
            'name' => $request->name,
            'document_number' => $request->document_number,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make(uniqid()),
            'role' => 'client',
        ]);

        // Registrar auditoría (sin usuario autenticado, se registra como Sistema)
        AuditHelper::log('user_register', "Nuevo usuario registrado: {$user->name} ({$user->email})");

        // 3. Redirección a la página de login con un mensaje de éxito
        return redirect()->route('login')->with('success', '¡Registro exitoso! Ahora puedes iniciar sesión con tu número de documento.');
    }
}