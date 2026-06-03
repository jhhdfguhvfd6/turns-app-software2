<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use App\Helpers\AuditHelper;

class ServiceController extends Controller
{
    public function index()
    {
        $services = Service::orderBy('name', 'asc')->paginate(10);
        return view('admin.services.index', compact('services'));
    }

    public function create()
    {
        return view('admin.services.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'prefix' => 'required|string|max:5|unique:services,prefix',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $service = Service::create($validated);

        AuditHelper::log('service_create', "Servicio '{$service->name}' creado");

        return redirect()->route('admin.services.index')
            ->with('success', 'Servicio creado correctamente');
    }

    public function edit($id)
    {
        $service = Service::findOrFail($id);
        return view('admin.services.edit', compact('service'));
    }

    public function update(Request $request, $id)
    {
        $service = Service::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'prefix' => 'required|string|max:5|unique:services,prefix,' . $service->_id . ',_id',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $service->update($validated);

        AuditHelper::log('service_update', "Servicio '{$service->name}' actualizado");

        return redirect()->route('admin.services.index')
            ->with('success', 'Servicio actualizado correctamente');
    }

    public function destroy($id)
    {
        $service = Service::findOrFail($id);
        $serviceName = $service->name;
        
        $service->delete();

        AuditHelper::log('service_delete', "Servicio '{$serviceName}' eliminado");

        return redirect()->route('admin.services.index')
            ->with('success', 'Servicio eliminado correctamente');
    }
}
