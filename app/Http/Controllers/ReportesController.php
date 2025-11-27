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

        // 🔹 Determinar modo inicial según el rol
        $modoDefault = 'all_casinos'; // por defecto
        if ($user->hasRole('casino_admin')) {
            $modoDefault = 'casino';
        } elseif ($user->hasAnyRole(['sucursal_admin', 'cajero'])) {
            $modoDefault = 'sucursal';
        }

        // UI mode: all_casinos | casino | sucursal | maquina
        $mode = $req->get('mode', $modoDefault); // 👈 Usa el modo según rol

        // Rango de fechas (misma lógica de Dashboard)
        [$inicio, $fin] = $this->resolverRango($req);

        //console([$inicio, $fin]);

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
        // Tabla de gastos dinámica según modo
        // ============================

        switch ($mode) {

            // =======================================================
            // 1️⃣ MODOS AGRUPADOS → all_casinos y casino
            // =======================================================
            case 'all_casinos':
            case 'casino':
                $gastosPorTipo = (clone $gastosBase)
                    ->selectRaw("
                sucursales.nombre AS sucursal,
                SUM(gastos.valor) AS total
            ")
                    ->join('sucursales', 'sucursales.id', '=', 'gastos.sucursal_id')
                    ->groupBy('sucursales.nombre')
                    ->orderByDesc('total')
                    ->get();
                break;

            // =======================================================
            // 2️⃣ MODOS DETALLADOS → sucursal y maquina
            // =======================================================
            case 'sucursal':
            case 'maquina':
            default:
                $gastosPorTipo = (clone $gastosBase)
                    ->selectRaw("
                gastos.fecha,
                sucursales.nombre AS sucursal,
                tipos_gasto.nombre AS tipo,
                gastos.descripcion,
                gastos.valor AS total
            ")
                    ->join('sucursales', 'sucursales.id', '=', 'gastos.sucursal_id')
                    ->join('tipos_gasto', 'gastos.tipo_gasto_id', '=', 'tipos_gasto.id')
                    ->orderByDesc('gastos.fecha')
                    ->orderBy('sucursales.nombre')
                    ->orderBy('tipos_gasto.nombre')
                    ->get();
                break;
        }

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
                // (casino, recaudo) - SIMPLIFICADO
                $porCasino = (clone $lecturasBase)
                    ->selectRaw('casinos.id as casino_id, casinos.nombre AS casino,
                        SUM(total_recaudo)  AS recaudo')
                    ->join('sucursales', 'sucursales.id', '=', 'lecturas_maquinas.sucursal_id')
                    ->join('casinos', 'casinos.id', '=', 'sucursales.casino_id')
                    ->groupBy('casinos.id', 'casinos.nombre')
                    ->orderByDesc('recaudo')
                    ->get();

                // Calcular gastos por casino
                $gastosPorCasino = (clone $gastosBase)
                    ->selectRaw('sucursales.casino_id, SUM(gastos.valor) as total_gastos')
                    ->join('sucursales', 'sucursales.id', '=', 'gastos.sucursal_id')
                    ->groupBy('sucursales.casino_id')
                    ->pluck('total_gastos', 'sucursales.casino_id');

                $porCasino->transform(function ($item) use ($gastosPorCasino) {
                    $gastos = $gastosPorCasino[$item->casino_id] ?? 0;
                    $item->gastos = $gastos;
                    $item->total_neto = $item->recaudo - $gastos;
                    return $item;
                });

                $bloques['tablaPrincipal'] = $porCasino;

                $chart = [
                    'labels' => $porCasino->pluck('casino'),
                    'data'   => $porCasino->pluck('recaudo'),
                    'title'  => 'Recaudo por casino',
                ];
                break;

            case 'casino':
                // (sucursal, recaudo) - SIMPLIFICADO
                $porSucursal = (clone $lecturasBase)
                    ->selectRaw('sucursales.id as sucursal_id, sucursales.nombre AS sucursal,
                        SUM(total_recaudo)  AS recaudo')
                    ->join('sucursales', 'sucursales.id', '=', 'lecturas_maquinas.sucursal_id')
                    ->groupBy('sucursales.id', 'sucursales.nombre')
                    ->orderByDesc('recaudo')
                    ->get();

                // Calcular gastos por sucursal y agregarlos
                $gastosPorSucursal = (clone $gastosBase)
                    ->selectRaw('sucursal_id, SUM(valor) as total_gastos')
                    ->groupBy('sucursal_id')
                    ->pluck('total_gastos', 'sucursal_id');

                $porSucursal->transform(function ($item) use ($gastosPorSucursal) {
                    $gastos = $gastosPorSucursal[$item->sucursal_id] ?? 0;
                    $item->gastos = $gastos;
                    $item->total_neto = $item->recaudo - $gastos;
                    return $item;
                });

                $bloques['tablaPrincipal'] = $porSucursal;

                $chart = [
                    'labels' => $porSucursal->pluck('sucursal'),
                    'data'   => $porSucursal->pluck('recaudo'),
                    'title'  => 'Recaudo por sucursal',
                ];
                break;

            case 'sucursal':
                // 1. Tabla detallada de gastos
                $gastosDetallados = (clone $gastosBase)
                    ->selectRaw("
                        gastos.fecha,
                        sucursales.nombre AS sucursal,
                        tipos_gasto.nombre AS tipo,
                        gastos.descripcion,
                        gastos.valor AS total
                    ")
                    ->join('sucursales', 'sucursales.id', '=', 'gastos.sucursal_id')
                    ->join('tipos_gasto', 'gastos.tipo_gasto_id', '=', 'tipos_gasto.id')
                    ->orderByDesc('gastos.fecha')
                    ->get();
                

                // 2. Tabla agrupada por tipo de gasto (Calculada en PHP para asegurar consistencia)
                $gastosAgrupados = $gastosDetallados->groupBy('tipo')->map(function ($items, $tipo) {
                    return [
                        'tipo' => $tipo,
                        'cantidad' => $items->count(),
                        'total' => $items->sum('total'),
                        'porcentaje' => 0
                    ];
                })->values();
                
                // Calcular porcentajes
                $totalGastosSucursal = $gastosAgrupados->sum('total');
                $gastosAgrupados->transform(function($item) use ($totalGastosSucursal) {
                    $item['porcentaje'] = $totalGastosSucursal > 0 ? round(($item['total'] / $totalGastosSucursal) * 100, 1) : 0;
                    return $item;
                });

                // Asignar a bloques
                $bloques['tablaGastosPorTipo'] = $gastosDetallados;

                $bloques['tablaGastosAgrupados'] = $gastosAgrupados;

                // (maquina, entrada, salida, jackpots, neto_final, neto_inicial, creditos, recaudo)
                $porMaquina = (clone $lecturasBase)
                    ->selectRaw("
            maquinas.ndi,
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
                    ->groupBy('maquinas.ndi', 'maquina')
                    ->get()
                    ->sort(function ($a, $b) {
                        // Ordenamiento natural: números primero, luego strings
                        $aNdi = $a->ndi;
                        $bNdi = $b->ndi;

                        $aIsNumeric = is_numeric($aNdi);
                        $bIsNumeric = is_numeric($bNdi);

                        // Si uno es numérico y el otro no, el numérico va primero
                        if ($aIsNumeric && !$bIsNumeric) return -1;
                        if (!$aIsNumeric && $bIsNumeric) return 1;

                        // Si ambos son numéricos, comparar como números
                        if ($aIsNumeric && $bIsNumeric) {
                            return (float)$aNdi <=> (float)$bNdi;
                        }

                        // Si ambos son strings, comparar naturalmente
                        return strnatcasecmp($aNdi, $bNdi);
                    })
                    ->values(); // reindexar

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
                
                // En modo máquina NO mostramos gastos
                $bloques['tablaGastosPorTipo'] = [];
                break;
        }

        // combos
        $casinos    = Casino::select('id', 'nombre')->get();
        $sucursales = Sucursal::select('id', 'nombre', 'casino_id')->get();
        $maquinas   = Maquina::select('id', 'ndi', 'nombre', 'sucursal_id')->get()
            ->sort(function ($a, $b) {
                // Ordenamiento natural: números primero, luego strings
                $aNdi = $a->ndi;
                $bNdi = $b->ndi;

                $aIsNumeric = is_numeric($aNdi);
                $bIsNumeric = is_numeric($bNdi);

                // Si uno es numérico y el otro no, el numérico va primero
                if ($aIsNumeric && !$bIsNumeric) return -1;
                if (!$aIsNumeric && $bIsNumeric) return 1;

                // Si ambos son numéricos, comparar como números
                if ($aIsNumeric && $bIsNumeric) {
                    return (float)$aNdi <=> (float)$bNdi;
                }

                // Si ambos son strings, comparar naturalmente
                return strnatcasecmp($aNdi, $bNdi);
            })
            ->values(); // reindexar

        return Inertia::render('Reportes/Index', [
            'mode'             => $mode,
            'inicio'           => $inicio->toDateString(),
            'fin'              => $fin->toDateString(),

            'resumenGlobal'    => $resumenGlobal,
            'gastosPorTipo'    => $gastosPorTipo,
            'tablaPrincipal'   => $bloques['tablaPrincipal'],
            'tablaSecundaria'  => $bloques['tablaSecundaria'],            
            'tablaGastosAgrupados'  => $bloques['tablaGastosAgrupados'],
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
    // public function export(Request $req)
    // {
    //     // ✅ decodificar los JSON recibidos
    //     $headings = json_decode($req->input('headings', '[]'), true);
    //     $rows     = json_decode($req->input('rows', '[]'), true);
    //     $name     = $req->input('name', 'reporte.xlsx');

    //     // Validar que sean arrays
    //     if (!is_array($headings) || !is_array($rows)) {
    //         abort(400, 'Formato inválido para exportar');
    //     }

    //     return Excel::download(new TableExport($headings, $rows), $name);
    // }


    public function export(Request $req)
    {
        $headings = $req->input('headings', []);
        $rows     = $req->input('rows', []);
        $name     = $req->input('name', 'reporte.xlsx');

        if (!is_array($headings) || !is_array($rows)) {
            abort(400, 'Formato inválido');
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
                $inicio = Carbon::parse($req->input('start_date'))->startOfDay();
                $fin = Carbon::parse($req->input('end_date'))->endOfDay();
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
