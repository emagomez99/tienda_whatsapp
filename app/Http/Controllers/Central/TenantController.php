<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function index()
    {
        $tenants = Tenant::with('domains')->orderBy('created_at', 'desc')->get();
        return view('central.tenants.index', compact('tenants'));
    }

    public function create()
    {
        return view('central.tenants.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'slug'    => 'required|alpha_dash|max:50|unique:tenants,id',
            'nombre'  => 'required|string|max:255',
            'email'   => 'required|email|unique:tenants,email',
            'dominio' => 'required|string|max:255',
            'plan'    => 'in:free,basic,pro',
        ]);

        $tenant = Tenant::create([
            'id'     => $request->slug,
            'nombre' => $request->nombre,
            'email'  => $request->email,
            'plan'   => $request->plan ?? 'free',
            'activo' => true,
        ]);

        $tenant->domains()->create(['domain' => $request->dominio]);

        if ($request->boolean('seed')) {
            tenancy()->initialize($tenant);
            \Artisan::call('db:seed', ['--class' => 'TenantDatabaseSeeder', '--force' => true]);
            tenancy()->end();
        }

        return redirect()->route('tenants.index')
            ->with('success', "Tenant \"{$tenant->nombre}\" creado exitosamente.");
    }

    public function destroy(Tenant $tenant)
    {
        $tenant->delete(); // Elimina el tenant y su schema PostgreSQL

        return redirect()->route('tenants.index')
            ->with('success', "Tenant eliminado.");
    }
}
