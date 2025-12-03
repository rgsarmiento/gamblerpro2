<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Retencion, Sucursal, Casino};
use Inertia\Inertia;

class RetencionesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $req)
    {
        $user = $req->user();

        $q = Retencion::with(['usuario', 'sucursal.casino'])
            ->orderByDesc('fecha');

        // =========================
        // 🔹 FILTROS POR ROL
        // =========================
        if ($user->hasRole('master_admin')) {
            // Ver todas las retenciones, con filtros opcionales
            if ($req->filled('casino_id')) {
                $q->whereHas('sucursal', fn($qq) => $qq->where('casino_id', $req->casino_id));
            }
            if ($req->filled('sucursal_id')) {
                $q->where('sucursal_id', $req->sucursal_id);
            }
        } elseif ($user->hasRole('casino_admin')) {
            // Filtrar solo retenciones del casino asignado
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
        } else {
            // Por defecto, mostrar solo retenciones del día actual
            $q->whereDate('fecha', now());
        }

        // =========================
        // 🔹 PAGINACIÓN Y CÁLCULOS
        // =========================
        $retenciones = $q->paginate(50)->withQueryString();

        // Clonar la query base para calcular totales (sin paginación)
        $qTotales = Retencion::query();
        
        // Aplicar los mismos filtros de rol
        if ($user->hasRole('master_admin')) {
            if ($req->filled('casino_id')) {
                $qTotales->whereHas('sucursal', fn($qq) => $qq->where('casino_id', $req->casino_id));
            }
            if ($req->filled('sucursal_id')) {
                $qTotales->where('sucursal_id', $req->sucursal_id);
            }
        } elseif ($user->hasRole('casino_admin')) {
            $qTotales->whereHas('sucursal', fn($qq) => $qq->where('casino_id', $user->casino_id));
            if ($req->filled('sucursal_id')) {
                $qTotales->where('sucursal_id', $req->sucursal_id);
            }
        } elseif ($user->hasAnyRole(['sucursal_admin', 'cajero'])) {
            $qTotales->where('sucursal_id', $user->sucursal_id);
        }
        
        // Aplicar filtro de fecha
        if ($req->filled('fecha')) {
            $qTotales->whereDate('fecha', $req->fecha);
        } else {
            $qTotales->whereDate('fecha', now());
        }

        $totalPremios = $qTotales->sum('valor_premio');
        $totalRetenciones = $qTotales->sum('valor_retencion');
        $totalRegistros = $retenciones->total();

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

        return Inertia::render('Retenciones/Index', [
            'retenciones' => $retenciones,
            'totalPremios' => $totalPremios,
            'totalRetenciones' => $totalRetenciones,
            'totalRegistros' => $totalRegistros,
            'casinos' => $casinos,
            'sucursales' => $sucursales,
            'filters' => [
                'fecha' => $req->fecha ?? now()->format('Y-m-d'),
                'casino_id' => $req->casino_id,
                'sucursal_id' => $req->sucursal_id,
            ],
            'user' => $user->only(['id', 'name', 'sucursal_id', 'casino_id'])
                + ['roles' => $user->getRoleNames()],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $req)
    {
        $data = $req->validate([
            'fecha' => 'required|date',
            'sucursal_id' => 'required|exists:sucursales,id',
            'cedula' => 'required|string|max:20',
            'nombre' => 'required|string|max:255',
            'valor_premio' => 'required|numeric|min:0',
            'observacion' => 'nullable|string',
        ]);

        // Calcular automáticamente el 20% de retención
        $data['valor_retencion'] = $data['valor_premio'] * 0.20;
        $data['user_id'] = $req->user()->id;

        Retencion::create($data);

        return back()->with('success', 'Retención registrada exitosamente.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $req, Retencion $retencion)
    {
        $data = $req->validate([
            'fecha' => 'required|date',
            'cedula' => 'required|string|max:20',
            'nombre' => 'required|string|max:255',
            'valor_premio' => 'required|numeric|min:0',
            'observacion' => 'nullable|string',
        ]);

        // Recalcular el 20% de retención
        $data['valor_retencion'] = $data['valor_premio'] * 0.20;

        $retencion->update($data);

        return back()->with('success', 'Retención actualizada correctamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Retencion $retencion)
    {
        $retencion->delete();

        return back()->with('success', 'Retención eliminada correctamente.');
    }
}
