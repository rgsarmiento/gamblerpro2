<?php

namespace App\Http\Controllers;

use App\Models\LecturaMaquina;
use App\Models\Gasto;
use App\Models\Sucursal;
use App\Models\CierreCaja;
use App\Models\Casino;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $req)
    {
        $user = $req->user();
        $casinoId = $req->get('casino_id');
        $sucursalId = $req->get('sucursal_id');
        $range = $req->get('range', 'today'); // Valor por defecto

        // 🗓️ Definir rango de fechas según la opción seleccionada
        $hoy = Carbon::today();
        $inicio = null;
        $fin = Carbon::today();

        switch ($range) {
            case 'today':
                $inicio = $hoy;
                $fin = $hoy;
                break;

            case 'yesterday':
                $inicio = $hoy->copy()->subDay();
                $fin = $hoy->copy()->subDay();
                break;

            case 'last7':
                $inicio = $hoy->copy()->subDays(6); // hoy incluido
                break;

            case 'last30':
                $inicio = $hoy->copy()->subDays(29);
                break;

            case 'this_month':
                $inicio = $hoy->copy()->startOfMonth();
                break;

            case 'last_month':
                $inicio = $hoy->copy()->subMonth()->startOfMonth();
                $fin = $hoy->copy()->subMonth()->endOfMonth();
                break;

            case 'this_month_last_year':
                $inicio = $hoy->copy()->subYear()->startOfMonth();
                $fin = $hoy->copy()->subYear()->endOfMonth();
                break;

            case 'this_year':
                $inicio = $hoy->copy()->startOfYear();
                break;

            case 'last_year':
                $inicio = $hoy->copy()->subYear()->startOfYear();
                $fin = $hoy->copy()->subYear()->endOfYear();
                break;

            default:
                $inicio = $hoy->copy()->startOfMonth();
                break;
        }

        // 🔐 Filtros por rol
        $filtroCasino = null;
        $filtroSucursal = null;

        if ($user->hasRole('master_admin')) {
            $filtroCasino = $casinoId;
            $filtroSucursal = $sucursalId;
        } elseif ($user->hasRole('casino_admin')) {
            $filtroCasino = $user->casino_id;
            $filtroSucursal = $sucursalId;
        } else {
            $filtroSucursal = $user->sucursal_id;
        }

        // 🔹 Lecturas CERRADAS en el rango
        $lecturas = LecturaMaquina::query()
            ->whereNotNull('cierre_id')
            ->whereBetween('fecha', [$inicio, $fin])
            ->when(
                $filtroCasino,
                fn($q) =>
                $q->whereHas('sucursal', fn($s) => $s->where('casino_id', $filtroCasino))
            )
            ->when($filtroSucursal, fn($q) => $q->where('sucursal_id', $filtroSucursal));
                

        // 🔹 Gastos CERRADOS en el rango
        $gastos = Gasto::query()
            ->whereNotNull('cierre_id')
            ->whereBetween('fecha', [$inicio, $fin])
            ->when(
                $filtroCasino,
                fn($q) =>
                $q->whereHas('sucursal', fn($s) => $s->where('casino_id', $filtroCasino))
            )
            ->when($filtroSucursal, fn($q) => $q->where('sucursal_id', $filtroSucursal));
            
        // Totales
        $totalLecturas = $lecturas->sum('total_recaudo');
        $totalGastos = $gastos->sum('valor');
        $saldo = $totalLecturas - $totalGastos;

        // 📊 Agrupaciones para gráficas
        $lecturasPorDia = (clone $lecturas)
            ->selectRaw('CAST(fecha AS DATE) as dia, SUM(total_recaudo) as total')
            ->groupBy('dia')
            ->orderBy('dia')
            ->get();

        $gastosPorDia = (clone $gastos)
            ->selectRaw('CAST(fecha AS DATE) as dia, SUM(valor) as total')
            ->groupBy('dia')
            ->orderBy('dia')
            ->get();

        $recaudoPorSucursal = Sucursal::select('id', 'nombre')
            ->when($filtroCasino, fn($q) => $q->where('casino_id', $filtroCasino))
            ->withSum(['lecturasMaquinas as total_recaudo' => function ($q) use ($inicio, $fin) {
                $q->whereNotNull('cierre_id')
                    ->whereBetween('fecha', [$inicio, $fin]);
            }], 'total_recaudo')
            ->orderByDesc('total_recaudo')
            ->take(10)
            ->get()
            ->map(fn($s) => [
                'sucursal' => $s->nombre,
                'total' => $s->total_recaudo ?? 0,
            ]);

        return inertia('Dashboard', [
            'totales' => [
                'lecturas' => $totalLecturas,
                'gastos' => $totalGastos,
                'saldo' => $saldo,
            ],
            'lecturasPorDia' => $lecturasPorDia,
            'gastosPorDia' => $gastosPorDia,
            'recaudoPorSucursal' => $recaudoPorSucursal,
            'casinos' => Casino::select('id', 'nombre')->get(),
            'sucursales' => Sucursal::select('id', 'nombre', 'casino_id')->get(),
            'user'       => $user->only(['id', 'name', 'sucursal_id', 'casino_id']) + ['roles' => $user->getRoleNames()],
            'range' => $range,
            'inicio' => $inicio->toDateString(),
            'fin' => $fin->toDateString(),
        ]);
    }
}
