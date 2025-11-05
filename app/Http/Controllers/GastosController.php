<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Gasto, Proveedor, Sucursal, Casino, TipoGasto};
use Inertia\Inertia;

class GastosController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $req)
    {
        $user = $req->user();
        $q = Gasto::with(['tipo', 'proveedor', 'sucursal:id,nombre,casino_id', 'usuario'])->orderByDesc('fecha');
       
        if ($user->hasRole('master_admin')) {
        } elseif ($user->hasRole('casino_admin')) {
            $q->whereHas('sucursal', fn($qq) => $qq->where('casino_id', $user->casino_id));
        } else {
            $q->where('sucursal_id', $user->sucursal_id);
        }

        if ($req->filled('fecha')) $q->where('fecha', $req->fecha);
        if ($req->filled('tipo_gasto_id')) $q->where('tipo_gasto_id', $req->tipo_gasto_id);

        $q->whereNull('cierre_id');
        
        $gastos = $q->paginate(20)->withQueryString();

        // Datos para selects
        $casinos = $user->hasRole('master_admin')
            ? Casino::select('id', 'nombre')->get()
            : [];

        $sucursales = $user->hasAnyRole(['master_admin', 'casino_admin'])
            ? Sucursal::select('id', 'nombre', 'casino_id')
            ->when($user->hasRole('casino_admin'), fn($qq) => $qq->where('casino_id', $user->casino_id))
            ->get()
            : [];

        $tipos_gasto = TipoGasto::select('id', 'nombre')->get();
        $proveedores = Proveedor::select('id', 'identificacion', 'nombre', 'sucursal_id')->get();

        return Inertia::render('Gastos/Index', [
            'gastos'   => $gastos,
            'total_registros' => $gastos->total(),
            'total_gastos' => $gastos->sum('valor'),
            'casinos'    => $casinos,
            'sucursales' => $sucursales,
            'tipos_gasto'   => $tipos_gasto,
            'proveedores'   => $proveedores,
            'user'       => $user->only(['id', 'name', 'sucursal_id', 'casino_id']) + ['roles' => $user->getRoleNames()],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $req)
    {
        $data = $req->validate([
            'sucursal_id' => 'required|integer',
            'tipo_gasto_id' => 'required|exists:tipos_gasto,id',
            'proveedor_id' => 'required|exists:proveedores,id',            
            'valor' => 'required|numeric|min:0.01',
            'descripcion' => 'nullable|string'
        ]);


        $gasto = new Gasto(array_merge($data, [
            'fecha' => now(),
            'user_id' => $req->user()->id,
        ]));

        $gasto->save();
        return redirect()->back()->with('success', 'Gasto registrado exitosamente');        
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
    public function update(Request $req, Gasto $gasto)
    {
        $this->authorize('update', $gasto);
        $data = $req->validate([
            'tipo_gasto_id' => 'required|exists:tipos_gasto,id',
            'proveedor_id' => 'required|exists:proveedores,id',
            'fecha' => 'required|date',
            'valor' => 'required|numeric|min:0.01',
            'descripcion' => 'nullable|string'
        ]);
        $gasto->fill($data)->save();
        return $gasto->fresh(['tipo', 'proveedor', 'sucursal']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $req, Gasto $gasto)
    {
        $this->authorize('update', $gasto);
        $gasto->delete();
        return redirect()->back()->with('success', 'Gasto eliminado correctamente');
    }
}
