<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CierreCaja;
use App\Models\LecturaMaquina;
use App\Models\Gasto;
use App\Models\Sucursal;
use App\Models\Casino;
use Illuminate\Support\Facades\DB;


class CierreCajaController extends Controller
{
    public function store(Request $req)
    {
        $user = $req->user();

        $sucursalId = $user->sucursal_id ?? $req->sucursal_id;

        DB::transaction(function () use ($user, $sucursalId) {
            // 🔹 Obtener lecturas y gastos pendientes
            $lecturas = LecturaMaquina::where('sucursal_id', $sucursalId)
                ->whereNull('cierre_id')
                ->orderBy('fecha')
                ->get();

            $gastos = Gasto::where('sucursal_id', $sucursalId)
                ->whereNull('cierre_id')
                ->orderBy('fecha')
                ->get();

            if ($lecturas->isEmpty() && $gastos->isEmpty()) {
                throw new \Exception("No hay registros pendientes para cerrar en esta sucursal.");
            }

            // 🔹 Calcular totales
            $totalRecaudado = $lecturas->sum('total_recaudo');
            $totalGastos = $gastos->sum('valor');
            $totalCierre = $totalRecaudado - $totalGastos;

            // 🔹 Determinar fechas de inicio y fin
            $primerFechaLectura = $lecturas->min('fecha');
            $primerFechaGasto   = $gastos->min('fecha');

            $ultimaFechaLectura = $lecturas->max('fecha');
            $ultimaFechaGasto   = $gastos->max('fecha');

            $fechaInicio = collect([$primerFechaLectura, $primerFechaGasto])
                ->filter()
                ->min();

            $fechaFin = collect([$ultimaFechaLectura, $ultimaFechaGasto])
                ->filter()
                ->max();

            // 🔹 Crear cierre
            $cierre = CierreCaja::create([
                'user_id'         => $user->id,
                'sucursal_id'     => $sucursalId,
                'fecha_inicio'    => $fechaInicio,
                'fecha_fin'       => $fechaFin,
                'total_recaudado' => $totalRecaudado,
                'total_gastos'    => $totalGastos,
                'total_cierre'    => $totalCierre,
            ]);

            // 🔹 Actualizar registros
            LecturaMaquina::where('sucursal_id', $sucursalId)
                ->whereNull('cierre_id')
                ->update(['cierre_id' => $cierre->id]);

            Gasto::where('sucursal_id', $sucursalId)
                ->whereNull('cierre_id')
                ->update(['cierre_id' => $cierre->id]);
        });

        return redirect()->back()->with('success', 'Cierre de caja generado correctamente.');
    }


    public function index(Request $req)
    {
        $user = $req->user();

        $casinoId   = $req->casino_id;
        $sucursalId = $req->sucursal_id;

        $q = CierreCaja::with(['user:id,name', 'casino:id,nombre', 'sucursal:id,nombre'])
            ->orderByDesc('created_at');

        if ($user->hasRole('master_admin')) {
            if ($casinoId) {
                $q->where('casino_id', $casinoId);
            }
            if ($sucursalId) {
                $q->where('sucursal_id', $sucursalId);
            }
        } elseif ($user->hasRole('casino_admin')) {
            $q->whereHas('sucursal', fn($qq) => $qq->where('casino_id', $user->casino_id));
            if ($sucursalId) {
                $q->where('sucursal_id', $sucursalId);
            }
        } else {
            $q->where('sucursal_id', $user->sucursal_id);
        }

        $cierres = $q->paginate(10)->withQueryString();

        // Datos para selects
        $casinos = $user->hasRole('master_admin')
            ? Casino::select('id', 'nombre')->get()
            : [];

        $sucursales = $user->hasAnyRole(['master_admin', 'casino_admin'])
            ? Sucursal::select('id', 'nombre', 'casino_id')
            ->when($user->hasRole('casino_admin'), fn($qq) => $qq->where('casino_id', $user->casino_id))
            ->get()
            : [];

        return inertia('Cierres/Index', [
            'cierres' => $cierres,
            'casinos' => $casinos,
            'sucursales' => $sucursales,
            'user' => $user->only(['id', 'name', 'sucursal_id', 'casino_id']) + ['roles' => $user->getRoleNames()],
        ]);
    }

}
