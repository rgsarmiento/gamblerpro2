<?php

namespace App\Http\Controllers;

use App\Models\Casino;
use App\Models\Sucursal;
use App\Models\Maquina;
use App\Models\LecturaMaquina;
use App\Models\Gasto;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Exports\TableExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;

class ReportesController extends Controller
{
    /** Vista + datos */
    public function index(Request $req)
    {
        $user = $req->user();

        // UI mode: all_casinos | casino | sucursal | maquina
        $mode = $req->get('mode', 'all_casinos');

        // Rango de fechas (misma lógica de Dashboard)
        [$inicio, $fin] = $this->resolverRango($req);

        // Filtros jerárquicos
        $casinoId   = $req->integer('casino_id') ?: null;
        $sucursalId = $req->integer('sucursal_id') ?: null;
        $maquinaId  = $req->integer('maquina_id') ?: null;

        if ($mode !== 'casino') {
            $casinoId = null;
        }

        if ($mode !== 'sucursal') {
            $sucursalId = null;
        }

        if ($mode !== 'maquina') {
            $maquinaId = null;
        }


        // Reglas por rol
        if ($user->hasRole('master_admin')) {
            // usa lo que venga de filtros
        } elseif ($user->hasRole('casino_admin')) {
            $casinoId = $user->casino_id;                // fija casino
            // sucursal opcional (si llega en el request)
        } else { // sucursal_admin | cajero
            $sucursalId = $user->sucursal_id;            // fija sucursal
        }

        // ============================
        // Bloques reusables de query
        // ============================
        $lecturasBase = LecturaMaquina::query()
            ->where('confirmado', 1)
            ->whereBetween('fecha', [$inicio, $fin])
            ->when(
                $casinoId,
                fn($q) =>
                $q->whereHas('sucursal', fn($s) => $s->where('casino_id', $casinoId))
            )
            ->when($sucursalId, fn($q) => $q->where('lecturas_maquinas.sucursal_id', $sucursalId)) // ✅ Fijo
            ->when($maquinaId, fn($q) => $q->where('lecturas_maquinas.maquina_id', $maquinaId));   // ✅ Fijo

        $gastosBase = Gasto::query()
            ->whereBetween('fecha', [$inicio, $fin])
            ->when(
                $casinoId,
                fn($q) =>
                $q->whereHas('sucursal', fn($s) => $s->where('casino_id', $casinoId))
            )
            ->when($sucursalId, fn($q) => $q->where('gastos.sucursal_id', $sucursalId)); // ✅ Fijo

        // ============================
        // Totales globales (siempre)
        // ============================
        $sumLecturas = (clone $lecturasBase)->selectRaw("
            COALESCE(SUM(entrada),0) AS entrada,
            COALESCE(SUM(salida),0) AS salida,
            COALESCE(SUM(jackpots),0) AS jackpots,
            COALESCE(SUM(neto_final),0) AS neto_final,
            COALESCE(SUM(neto_inicial),0) AS neto_inicial,
            COALESCE(SUM(total_creditos),0) AS creditos,
            COALESCE(SUM(total_recaudo),0) AS recaudo
        ")->first();

        $sumGastos = (clone $gastosBase)->sum('valor');

        $resumenGlobal = [
            'neto_final'    => (float)$sumLecturas->neto_final,
            'neto_inicial'  => (float)$sumLecturas->neto_inicial,
            'creditos'      => (float)$sumLecturas->creditos,
            'recaudo'       => (float)$sumLecturas->recaudo,
            'gastos'        => (float)$sumGastos,
            'saldo'         => (float)$sumLecturas->recaudo - (float)$sumGastos,
        ];

        // ============================
        // Gastos agrupados por tipo
        // ============================
        $gastosPorTipo = (clone $gastosBase)
            ->selectRaw('tipos_gasto.nombre AS tipo, SUM(gastos.valor) AS total')
            ->join('tipos_gasto', 'gastos.tipo_gasto_id', '=', 'tipos_gasto.id')
            ->groupBy('tipos_gasto.nombre')
            ->orderByDesc('total')
            ->get();

        // ============================
        // Tablas según modo
        // ============================
        $bloques = [
            'tablaGastosPorTipo' => $gastosPorTipo,
            'tablaPrincipal'     => [], // se llena por modo
            'tablaSecundaria'    => [], // se llena por modo
        ];
        $chart = [
            'labels' => [],
            'data'   => [],
            'title'  => '',
        ];

        switch ($mode) {
            case 'all_casinos':
                // (casino, neto_final, neto_inicial, creditos, recaudo)
                $porCasino = (clone $lecturasBase)
                    ->selectRaw('casinos.nombre AS casino,
                        SUM(neto_final)   AS neto_final,
                        SUM(neto_inicial) AS neto_inicial,
                        SUM(total_creditos) AS creditos,
                        SUM(total_recaudo)  AS recaudo')
                    ->join('sucursales', 'sucursales.id', '=', 'lecturas_maquinas.sucursal_id')
                    ->join('casinos', 'casinos.id', '=', 'sucursales.casino_id')
                    ->groupBy('casinos.nombre')
                    ->orderByDesc('recaudo')
                    ->get();

                $bloques['tablaPrincipal'] = $porCasino;

                $chart = [
                    'labels' => $porCasino->pluck('casino'),
                    'data'   => $porCasino->pluck('recaudo'),
                    'title'  => 'Recaudo por casino',
                ];
                break;

            case 'casino':
                // (sucursal, neto_final, neto_inicial, creditos, recaudo)
                $porSucursal = (clone $lecturasBase)
                    ->selectRaw('sucursales.nombre AS sucursal,
                        SUM(neto_final)   AS neto_final,
                        SUM(neto_inicial) AS neto_inicial,
                        SUM(total_creditos) AS creditos,
                        SUM(total_recaudo)  AS recaudo')
                    ->join('sucursales', 'sucursales.id', '=', 'lecturas_maquinas.sucursal_id')
                    ->groupBy('sucursales.nombre')
                    ->orderByDesc('recaudo')
                    ->get();

                $bloques['tablaPrincipal'] = $porSucursal;

                $chart = [
                    'labels' => $porSucursal->pluck('sucursal'),
                    'data'   => $porSucursal->pluck('recaudo'),
                    'title'  => 'Recaudo por sucursal',
                ];
                break;

            case 'sucursal':
                // (maquina, entrada, salida, jackpots, neto_final, neto_inicial, creditos, recaudo)
                $porMaquina = (clone $lecturasBase)
                    ->selectRaw("
                        CONCAT(maquinas.ndi,' - ',maquinas.nombre) AS maquina,
                        SUM(entrada)        AS entrada,
                        SUM(salida)         AS salida,
                        SUM(jackpots)       AS jackpots,
                        SUM(neto_final)     AS neto_final,
                        SUM(neto_inicial)   AS neto_inicial,
                        SUM(total_creditos) AS creditos,
                        SUM(total_recaudo)  AS recaudo
                    ")
                    ->join('maquinas', 'maquinas.id', '=', 'lecturas_maquinas.maquina_id')
                    ->groupBy('maquina')
                    ->orderByDesc('recaudo')
                    ->get();

                // (usuario, neto_final, neto_inicial, creditos, recaudo, %)
                $porUsuario = (clone $lecturasBase)
                    ->selectRaw("
                        users.name AS usuario,
                        SUM(neto_final)     AS neto_final,
                        SUM(neto_inicial)   AS neto_inicial,
                        SUM(total_creditos) AS creditos,
                        SUM(total_recaudo)  AS recaudo
                    ")
                    ->join('users', 'users.id', '=', 'lecturas_maquinas.user_id')
                    ->groupBy('users.name')
                    ->orderByDesc('recaudo')
                    ->get()
                    ->map(function ($x) use ($sumLecturas) {
                        $total = (float)$sumLecturas->recaudo ?: 1;
                        $x->porcentaje = round(($x->recaudo / $total) * 100, 2);
                        return $x;
                    });

                $bloques['tablaPrincipal']  = $porMaquina;
                $bloques['tablaSecundaria'] = $porUsuario;
                break;

            case 'maquina':
                // Historial de esa máquina (fecha DESC)
                $historial = (clone $lecturasBase)
                    ->with('maquina:id,ndi,nombre')
                    ->orderByDesc('fecha')
                    ->get(['fecha', 'maquina_id', 'entrada', 'salida', 'jackpots', 'neto_final', 'neto_inicial', 'total_creditos', 'total_recaudo']);

                // (usuario, …, %)
                $porUsuarioM = (clone $lecturasBase)
                    ->selectRaw("
                        users.name AS usuario,
                        SUM(neto_final)     AS neto_final,
                        SUM(neto_inicial)   AS neto_inicial,
                        SUM(total_creditos) AS creditos,
                        SUM(total_recaudo)  AS recaudo
                    ")
                    ->join('users', 'users.id', '=', 'lecturas_maquinas.user_id')
                    ->groupBy('users.name')
                    ->orderByDesc('recaudo')
                    ->get()
                    ->map(function ($x) use ($sumLecturas) {
                        $total = (float)$sumLecturas->recaudo ?: 1;
                        $x->porcentaje = round(($x->recaudo / $total) * 100, 2);
                        return $x;
                    });

                $bloques['tablaPrincipal']  = $historial;
                $bloques['tablaSecundaria'] = $porUsuarioM;
                break;
        }

        // combos
        $casinos    = Casino::select('id', 'nombre')->get();
        $sucursales = Sucursal::select('id', 'nombre', 'casino_id')->get();
        $maquinas   = Maquina::select('id', 'ndi', 'nombre', 'sucursal_id')->get();

        return Inertia::render('Reportes/Index', [
            'mode'             => $mode,
            'inicio'           => $inicio->toDateString(),
            'fin'              => $fin->toDateString(),

            'resumenGlobal'    => $resumenGlobal,
            'gastosPorTipo'    => $gastosPorTipo,
            'tablaPrincipal'   => $bloques['tablaPrincipal'],
            'tablaSecundaria'  => $bloques['tablaSecundaria'],
            'chart'            => $chart,

            'casinos'          => $casinos,
            'sucursales'       => $sucursales,
            'maquinas'         => $maquinas,

            'filtros' => [
                'casino_id'   => $casinoId,
                'sucursal_id' => $sucursalId,
                'maquina_id'  => $maquinaId,
                'range'       => $req->get('range', 'this_month'),
                'start_date'  => $req->get('start_date'),
                'end_date'    => $req->get('end_date'),
            ],
            'user' => $user->only(['id', 'name', 'sucursal_id', 'casino_id']) + ['roles' => $user->getRoleNames()],
        ]);
    }

    /** Exportar cualquier tabla visible */
    public function export(Request $req)
    {
        // ✅ decodificar los JSON recibidos
        $headings = json_decode($req->input('headings', '[]'), true);
        $rows     = json_decode($req->input('rows', '[]'), true);
        $name     = $req->input('name', 'reporte.xlsx');

        // Validar que sean arrays
        if (!is_array($headings) || !is_array($rows)) {
            abort(400, 'Formato inválido para exportar');
        }

        return Excel::download(new TableExport($headings, $rows), $name);
    }

    /** === Helpers === */
    private function resolverRango(Request $req): array
    {
        $hoy = Carbon::today();
        $range = $req->get('range', 'this_month');

        $inicio = null;
        $fin = Carbon::today();

        switch ($range) {
            case 'today':
                $inicio = $hoy;
                break;
            case 'yesterday':
                $inicio = $hoy->copy()->subDay();
                $fin = $inicio;
                break;
            case 'custom':
                $inicio = Carbon::parse($req->input('start_date'));
                $fin = Carbon::parse($req->input('end_date'));
                break;
            case 'last7':
                $inicio = $hoy->copy()->subDays(6);
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

        return [$inicio, $fin];
    }
}
