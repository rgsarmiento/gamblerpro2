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
        $maquinas = $query
            ->orderBy('sucursal_id', 'ASC')
            ->orderByRaw("
                CASE 
                    WHEN ndi REGEXP '^[0-9]+$' THEN 0 
                    ELSE 1 
                END ASC
            ")
            ->orderByRaw("CAST(ndi AS UNSIGNED) ASC")
            ->orderBy('ndi', 'ASC') // Para ordenar numéricamente cuando sea posible por alfanuméricos
            ->paginate(50)
            ->withQueryString();

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
            'ultimo_neto_final' => 'nullable|numeric|min:0', // 👈 Agregar validación
        ], [
            'ndi.unique' => 'Ya existe una máquina con este NDI',
            'ndi.required' => 'El campo ndi es obligatorio.',
            'nombre.required' => 'El campo nombre es obligatorio.',
            'denominacion.required' => 'El campo denominacion es obligatorio.',
            'sucursal_id.required' => 'Debe seleccionar una sucursal',
            'ultimo_neto_final.numeric' => 'El último neto final debe ser un número.',
        ]);

        $user = $request->user();

        // Validar que la sucursal le pertenece
        $sucursal = Sucursal::find($validated['sucursal_id']);

        if ($user->hasRole('casino_admin') && $user->casino_id !== $sucursal->casino_id) {
            return back()->withErrors(['sucursal_id' => 'No puedes crear máquinas en otros casinos.']);
        }

        if ($user->hasRole('sucursal_admin') && $user->sucursal_id != $validated['sucursal_id']) {
            return back()->withErrors(['sucursal_id' => 'No puedes crear máquinas en otras sucursales.']);
        }

        // Si no viene ultimo_neto_final, usar 0 por defecto
        $validated['ultimo_neto_final'] = $validated['ultimo_neto_final'] ?? 0;

        Maquina::create($validated);

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
            'ultimo_neto_final' => 'nullable|numeric|min:0', // 👈 Agregar validación
        ], [
            'ultimo_neto_final.numeric' => 'El último neto final debe ser un número.',
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


    public function transfer(Request $request, Maquina $maquina)
    {
        $validated = $request->validate([
            'nueva_sucursal_id' => 'required|exists:sucursales,id|different:' . $maquina->sucursal_id,
        ], [
            'nueva_sucursal_id.required' => 'Debe seleccionar una sucursal',
            'nueva_sucursal_id.different' => 'Debe seleccionar una sucursal diferente',
        ]);

        $user = $request->user();
        $nuevaSucursal = Sucursal::find($validated['nueva_sucursal_id']);

        // 🔹 Validar permisos
        if ($user->hasRole('casino_admin')) {
            // Validar que ambas sucursales pertenezcan a su casino
            if (
                $user->casino_id !== $maquina->sucursal->casino_id ||
                $user->casino_id !== $nuevaSucursal->casino_id
            ) {
                return back()->withErrors(['nueva_sucursal_id' => 'Solo puedes transferir entre sucursales de tu casino.']);
            }
        } elseif ($user->hasRole('sucursal_admin')) {
            return back()->withErrors(['nueva_sucursal_id' => 'No tienes permisos para transferir máquinas.']);
        }

        // 🔹 Registrar la transferencia (opcional: crear tabla de historial)
        // TransferenciaMaquina::create([...])

        // 🔹 Actualizar sucursal
        $maquina->update(['sucursal_id' => $validated['nueva_sucursal_id']]);

        return back()->with('success', "Máquina transferida a {$nuevaSucursal->nombre} correctamente");
    }
}
