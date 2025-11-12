<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Proveedor;
use App\Models\Sucursal;
use Inertia\Inertia;

use Illuminate\Validation\ValidationException;

class ProveedoresController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $req)
    {
        $user = $req->user();

        // Base query
        $q = Proveedor::with('sucursal:id,nombre,casino_id')->orderBy('nombre');

        // 🔹 Si el usuario es master_admin o casino_admin, permitir filtrar por casino o sucursal
        if ($user->hasRole('master_admin')) {

            // Filtrar por casino si lo envía el frontend
            if ($req->filled('casino_id')) {
                $q->whereHas(
                    'sucursal',
                    fn($qq) =>
                    $qq->where('casino_id', $req->casino_id)
                );
            }

            // Filtrar por sucursal si se selecciona
            if ($req->filled('sucursal_id')) {
                $q->where('sucursal_id', $req->sucursal_id);
            }
        } elseif ($user->hasRole('casino_admin')) {

            // Casino admin solo puede ver sucursales de su casino
            $q->whereHas(
                'sucursal',
                fn($qq) =>
                $qq->where('casino_id', $user->casino_id)
            );

            // Si seleccionó una sucursal específica, aplicar el filtro
            if ($req->filled('sucursal_id')) {
                $q->where('sucursal_id', $req->sucursal_id);
            }
        } elseif ($user->hasAnyRole(['sucursal_admin', 'cajero'])) {

            // Roles bajos: solo su propia sucursal
            $q->where('sucursal_id', $user->sucursal_id);
        }

        // 🔹 Búsqueda opcional
        if ($req->filled('search')) {
            $search = $req->search;
            $q->where(function ($query) use ($search) {
                $query->where('nombre', 'like', "%$search%")
                    ->orWhere('identificacion', 'like', "%$search%");
            });
        }

        // Paginación con querystring (para mantener filtros)
        $proveedores = $q->paginate(20)->withQueryString();

        // Cargar sucursales según rol
        $sucursales = $user->hasAnyRole(['master_admin', 'casino_admin'])
            ? \App\Models\Sucursal::select('id', 'nombre', 'casino_id')
            ->when(
                $user->hasRole('casino_admin'),
                fn($qq) =>
                $qq->where('casino_id', $user->casino_id)
            )
            ->get()
            : [];

        // Cargar casinos solo si es master_admin
        $casinos = $user->hasRole('master_admin')
            ? \App\Models\Casino::select('id', 'nombre')->get()
            : [];

        return \Inertia\Inertia::render('Proveedores/Index', [
            'proveedores' => $proveedores,
            'sucursales' => $sucursales,
            'casinos' => $casinos,
            'filters' => [
                'search' => $req->search,
                'casino_id' => $req->casino_id,
                'sucursal_id' => $req->sucursal_id,
            ],
            'user' => $user->only(['id', 'name', 'sucursal_id', 'casino_id'])
                + ['roles' => $user->getRoleNames()],
        ]);
    }

    public function store(Request $req)
    {
        $data = $req->validate([
            'sucursal_id' => 'required|exists:sucursales,id',
            'nombre' => 'required|string|max:255',
            'identificacion' => 'required|string|max:20',
            'telefono' => 'nullable|string|max:20',
            'direccion' => 'nullable|string|max:255',
        ]);

        $exists = Proveedor::where('sucursal_id', $data['sucursal_id'])
            ->where('identificacion', $data['identificacion'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'identificacion' => 'Ya existe un proveedor con esa identificación en esta sucursal.',
            ]);
        }

        Proveedor::create($data + ['activo' => 1]);

        return back()->with('success', 'Proveedor registrado exitosamente.');
    }

    public function update(Request $req, Proveedor $proveedor)
    {
        $data = $req->validate([
            'nombre' => 'required|string|max:255',
            'identificacion' => 'required|string|max:20',
            'telefono' => 'nullable|string|max:20',
            'direccion' => 'nullable|string|max:255',
            'activo' => 'boolean',
        ]);

        $exists = Proveedor::where('sucursal_id', $proveedor->sucursal_id)
            ->where('identificacion', $data['identificacion'])
            ->where('id', '!=', $proveedor->id)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'identificacion' => 'Ya existe otro proveedor con esa identificación en esta sucursal.',
            ]);
        }

        $proveedor->update($data);

        return back()->with('success', 'Proveedor actualizado correctamente.');
    }

    public function toggleStatus(Proveedor $proveedor)
    {
        $proveedor->activo = !$proveedor->activo;
        $proveedor->save();

        return back()->with('success', 'Estado del proveedor actualizado.');
    }

    public function destroy(Proveedor $proveedor)
    {
        // 🔒 Solo eliminar si no tiene movimientos (ajusta si aplicas egresos o compras)
        if (method_exists($proveedor, 'egresos') && $proveedor->egresos()->exists()) {
            return back()->withErrors(['proveedor' => 'No se puede eliminar: tiene egresos asociados.']);
        }

        $proveedor->delete();

        return back()->with('success', 'Proveedor eliminado correctamente.');
    }
}
