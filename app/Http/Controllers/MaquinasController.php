<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\{Maquina, Sucursal, Casino};

class MaquinasController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $search = $request->input('search');
        $casinoId = $request->input('casino_id');
        $sucursalId = $request->input('sucursal_id');

        $query = Maquina::with('sucursal.casino');

        // 🔹 Filtrar por rol
        if ($user->hasRole('master_admin')) {
            if ($casinoId) {
                $query->whereHas('sucursal', fn($q) => $q->where('casino_id', $casinoId));
            }
            if ($sucursalId) {
                $query->where('sucursal_id', $sucursalId);
            }
        } elseif ($user->hasRole('casino_admin')) {
            $query->whereHas('sucursal', fn($q) => $q->where('casino_id', $user->casino_id));
            if ($sucursalId) {
                $query->where('sucursal_id', $sucursalId);
            }
        } elseif ($user->hasRole('sucursal_admin')) {
            $query->where('sucursal_id', $user->sucursal_id);
        } else {
            abort(403, 'No autorizado');
        }

        // 🔹 Filtro de búsqueda
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('ndi', 'like', "%{$search}%")
                    ->orWhere('codigo_interno', 'like', "%{$search}%");
            });
        }

        // 🔹 Paginación con 10 por página
        $maquinas = $query->orderBy('nombre')->paginate(10)->withQueryString();

        // Datos para selects
        $casinos = $user->hasRole('master_admin')
            ? Casino::select('id', 'nombre')->get()
            : [];

        $sucursales = $user->hasAnyRole(['master_admin', 'casino_admin'])
            ? Sucursal::select('id', 'nombre', 'casino_id')
            ->when($user->hasRole('casino_admin'), fn($qq) => $qq->where('casino_id', $user->casino_id))
            ->get()
            : [];

        return Inertia::render('Maquinas/Index', [
            'maquinas' => $maquinas,
            'sucursales' => $sucursales,
            'casinos' => $casinos,
            'filters' => [
                'search' => $search,
            ],
            'user'       => $user->only(['id', 'name', 'sucursal_id', 'casino_id']) + ['roles' => $user->getRoleNames()],
        ]);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'ndi' => 'required|string|max:255|unique:maquinas,ndi',
            'denominacion' => 'required|numeric|min:1',
            'sucursal_id' => 'required|exists:sucursales,id',
        ]);

        $user = $request->user();

        // 🔹 Validar que la sucursal le pertenece (solo para casino/sucursal_admin)
        if ($user->hasRole('casino_admin') && $user->casino_id !== Sucursal::find($validated['sucursal_id'])->casino_id) {
            abort(403, 'No puedes crear máquinas en otras casinos.');
        }

        if ($user->hasRole('sucursal_admin') && $user->sucursal_id != $validated['sucursal_id']) {
            abort(403, 'No puedes crear máquinas en otras sucursales.');
        }

        Maquina::create($validated + ['ultimo_neto_final' => 0]);

        return back()->with('success', 'Máquina creada correctamente');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Maquina $maquina)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'ndi' => 'required|string|max:255|unique:maquinas,ndi,' . $maquina->id,
            'denominacion' => 'required|numeric|min:1',
            'sucursal_id' => 'required|exists:sucursales,id',
        ]);

        $user = $request->user();

        // 🔹 Restringir actualización
        if ($user->hasRole('casino_admin') && $user->casino_id !== $maquina->sucursal->casino_id) {
            abort(403, 'No puedes editar máquinas fuera de tu casino.');
        }

        if ($user->hasRole('sucursal_admin') && $user->sucursal_id != $maquina->sucursal_id) {
            abort(403, 'No puedes editar máquinas fuera de tu sucursal.');
        }

        $maquina->update($validated);

        return back()->with('success', 'Máquina actualizada correctamente');
    }

    public function toggle(Request $request, Maquina $maquina)
    {
        $maquina->activa = $request->input('activa', 0);
        $maquina->save();

        return back()->with('success', 'Estado de la máquina actualizado');
    }



    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Maquina $maquina)
    {
        $user = $request->user();

        // 🔹 Validar rol antes de eliminar
        if ($user->hasRole('casino_admin') && $user->casino_id !== $maquina->sucursal->casino_id) {
            abort(403, 'No puedes eliminar máquinas fuera de tu casino.');
        }

        if ($user->hasRole('sucursal_admin') && $user->sucursal_id != $maquina->sucursal_id) {
            abort(403, 'No puedes eliminar máquinas fuera de tu sucursal.');
        }

        // 🔹 Validar si tiene lecturas
        if ($maquina->lecturas()->exists()) {
            return back()->withErrors(['maquina' => 'No se puede eliminar una máquina con lecturas registradas.']);
        }

        $maquina->delete();

        return back()->with('success', 'Máquina eliminada correctamente');
    }
}
