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

    $q = Gasto::with(['tipo', 'proveedor', 'usuario', 'sucursal.casino'])
        ->orderByDesc('fecha');

    // =========================
    // 🔹 FILTROS POR ROL
    // =========================
    if ($user->hasRole('master_admin')) {
        // Ver todos los gastos, con filtros opcionales
        if ($req->filled('casino_id')) {
            $q->whereHas('sucursal', fn($qq) => $qq->where('casino_id', $req->casino_id));
        }
        if ($req->filled('sucursal_id')) {
            $q->where('sucursal_id', $req->sucursal_id);
        }
    } elseif ($user->hasRole('casino_admin')) {
        // Filtrar solo gastos del casino asignado
        $q->whereHas('sucursal', fn($qq) => $qq->where('casino_id', $user->casino_id));

        if ($req->filled('sucursal_id')) {
            $q->where('sucursal_id', $req->sucursal_id);
        }
    } elseif ($user->hasAnyRole(['sucursal_admin', 'cajero'])) {
        // Solo su sucursal
        $q->where('sucursal_id', $user->sucursal_id);
    }

    // =========================
    // 🔹 FILTRO POR FECHA
    // =========================
    if ($req->filled('fecha')) {
        $q->whereDate('fecha', $req->fecha);
    }else{
        // Por defecto, mostrar solo gastos del dia actual
        $q->whereDate('fecha', now());
    }

    // =========================
    // 🔹 PAGINACIÓN Y CÁLCULOS
    // =========================
    $gastos = $q->paginate(50)->withQueryString();

    $totalGastos = (clone $q)->sum('valor');
    $totalRegistros = $gastos->total();

    // =========================
    // 🔹 DATOS AUXILIARES
    // =========================
    $casinos = $user->hasRole('master_admin')
        ? Casino::select('id', 'nombre')->get()
        : [];

    $sucursales = $user->hasAnyRole(['master_admin', 'casino_admin'])
        ? Sucursal::select('id', 'nombre', 'casino_id')
            ->when($user->hasRole('casino_admin'), fn($qq) => $qq->where('casino_id', $user->casino_id))
            ->get()
        : [];

    $tiposGasto = TipoGasto::select('id', 'nombre')->get();

    $proveedores = Proveedor::select('id', 'identificacion', 'nombre', 'sucursal_id')->get();

    // =========================
    // 🔹 RETORNO A VUE
    // =========================
    return Inertia::render('Gastos/Index', [
        'gastos' => $gastos,
        'total_registros' => $totalRegistros,
        'total_gastos' => $totalGastos,
        'casinos' => $casinos,
        'sucursales' => $sucursales,
        'tipos_gasto' => $tiposGasto,
        'proveedores' => $proveedores,
        'user' => $user->only(['id', 'name', 'sucursal_id', 'casino_id']) + ['roles' => $user->getRoleNames()],
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
            'descripcion' => 'nullable|string',
            'fecha' => 'required|date',
        ]);


        $gasto = new Gasto(array_merge($data, [
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
