<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use App\Helpers\AuditHelper;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Muestra una lista de todos los usuarios.
     */
    public function index()
    {
        // Obtiene todos los usuarios, los más nuevos primero, y los pagina.
        $users = User::latest()->paginate(10); 

        // Envía la variable $users a la vista para que pueda ser usada.
        return view('admin.users.index', compact('users'));
    }

    /**
     * Show the form for creating a new resource.
     * (Lo llenaremos en el futuro)
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     * (Lo llenaremos en el futuro)
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $user = User::findOrFail($id);
        return view('admin.users.edit', compact('user'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->_id . ',_id',
            'document_number' => 'required|string|max:20|unique:users,document_number,' . $user->_id . ',_id',
            'phone' => 'nullable|string|max:20',
            'role' => 'required|in:admin,cashier,client',
            'password' => 'nullable|min:8|confirmed',
        ]);

        // Actualizar datos básicos
        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->document_number = $validated['document_number'];
        $user->phone = $validated['phone'] ?? null;
        $user->role = $validated['role'];

        // Solo actualizar contraseña si se proporcionó una nueva
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $oldData = [
            'name' => $user->getOriginal('name'),
            'email' => $user->getOriginal('email'),
            'role' => $user->getOriginal('role')
        ];
        
        $user->save();

        // Registrar auditoría con detalles de cambios
        $changes = [];
        if ($oldData['name'] != $user->name) $changes[] = "nombre";
        if ($oldData['email'] != $user->email) $changes[] = "email";
        if ($oldData['role'] != $user->role) $changes[] = "rol";
        if (!empty($validated['password'])) $changes[] = "contraseña";
        
        $changesText = !empty($changes) ? " (cambios: " . implode(', ', $changes) . ")" : "";
        AuditHelper::log('user_update', "Actualizó usuario {$user->name}{$changesText}");

        return redirect()->route('admin.users.index')->with('success', 'Usuario actualizado correctamente');
    }

    /**
     * Remove the specified resource from storage.
     * (Lo llenaremos en el futuro)
     */
    public function destroy(User $user)
    {
        //
    }
}