<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Sucursal, Casino};
use Inertia\Inertia;

class SucursalesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $req)
    {
        $user = $req->user();

        // Solo master_admin puede acceder
        if (!$user->hasRole('master_admin')) {
            abort(403, 'No autorizado');
        }

        $query = Sucursal::with('casino:id,nombre');

        // Filtro por casino
        if ($req->filled('casino_id')) {
            $query->where('casino_id', $req->casino_id);
        }

        // Búsqueda por nombre
        if ($req->filled('search')) {
            $query->where('nombre', 'like', '%' . $req->search . '%');
        }

        $sucursales = $query->orderBy('nombre')
            ->paginate(50)
            ->withQueryString();

        $casinos = Casino::select('id', 'nombre')->orderBy('nombre')->get();

        return Inertia::render('Sucursales/Index', [
            'sucursales' => $sucursales,
            'casinos' => $casinos,
            'filters' => [
                'casino_id' => $req->casino_id,
                'search' => $req->search,
            ],
            'user' => $user->only(['id', 'name'])
                + ['roles' => $user->getRoleNames()],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $req)
    {
        $data = $req->validate([
            'casino_id' => 'required|exists:casinos,id',
            'nombre' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:20',
            'direccion' => 'nullable|string|max:255',
            'base_monedas' => 'nullable|numeric|min:0',
            'base_billetes' => 'nullable|numeric|min:0',
            'activo' => 'boolean',
        ]);

        Sucursal::create($data);

        return back()->with('success', 'Sucursal creada exitosamente.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $req, Sucursal $sucursal)
    {
        $data = $req->validate([
            'casino_id' => 'required|exists:casinos,id',
            'nombre' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:20',
            'direccion' => 'nullable|string|max:255',
            'base_monedas' => 'nullable|numeric|min:0',
            'base_billetes' => 'nullable|numeric|min:0',
            'activo' => 'boolean',
        ]);

        $sucursal->update($data);

        return back()->with('success', 'Sucursal actualizada correctamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Sucursal $sucursal)
    {
        // Verificar que no tenga lecturas asociadas
        if ($sucursal->lecturasMaquinas()->exists()) {
            return back()->withErrors([
                'delete' => 'No se puede eliminar la sucursal porque tiene lecturas asociadas.'
            ]);
        }

        // Verificar que no tenga gastos asociados
        if ($sucursal->gastos()->exists()) {
            return back()->withErrors([
                'delete' => 'No se puede eliminar la sucursal porque tiene gastos asociados.'
            ]);
        }

        $sucursal->delete();

        return back()->with('success', 'Sucursal eliminada correctamente.');
    }
}
