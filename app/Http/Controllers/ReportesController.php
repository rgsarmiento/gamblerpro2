<?php

namespace App\Http\Controllers;

use App\Models\Casino;
use App\Models\Sucursal;
use App\Models\Maquina;
use App\Models\LecturaMaquina;
use App\Models\Gasto;
use App\Models\ConfiguracionIva;
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

        // ❌ COMENTADO: Esta lógica reseteaba los filtros incorrectamente
        // El modo solo debe determinar QUÉ se muestra, no QUÉ filtros están disponibles
        // if ($mode !== 'casino') {
        //     $casinoId = null;
        // }
        // if ($mode !== 'sucursal') {
        //     $sucursalId = null;
        // }
        // if ($mode !== 'maquina') {
        //     $maquinaId = null;
        // }


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
                proveedores.nombre AS proveedor,
                gastos.descripcion,
                gastos.valor AS total
            ")
                    ->join('sucursales', 'sucursales.id', '=', 'gastos.sucursal_id')
                    ->join('tipos_gasto', 'gastos.tipo_gasto_id', '=', 'tipos_gasto.id')
                    ->leftJoin('proveedores', 'gastos.proveedor_id', '=', 'proveedores.id')
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
                // =======================================================
                // 🎰 REPORTE PERSONALIZADO DE CASINO (RESUMEN RECAUDO)
                // =======================================================

                // 1. Obtener Configuración de IVA para el año
                $anio = $fin->year;
                $configIva = ConfiguracionIva::where('anio', $anio)->first();
                $valorUvt = $configIva ? $configIva->valor_uvt : 0;
                $cantidadUvt = $configIva ? $configIva->cantidad_uvt : 0;
                $porcentajeIva = $configIva ? $configIva->porcentaje_iva : 0;

                // 2. Obtener Sucursales del Casino (o todas si es master_admin y no filtró)
                // Si el usuario es casino_admin ya tiene el filtro por rol. Si es master y seleccionó casino, también.
                // Si no hay casino_id, se muestran todas las sucursales agrupadas?
                // El reporte pide "por cada sucursal del casino". Asumiremos que hay un casino seleccionado o iteramos todas las sucursales filtradas.
                
                $sucursalesQuery = Sucursal::query();
                if ($casinoId) {
                    $sucursalesQuery->where('casino_id', $casinoId);
                }
                $sucursalesDb = $sucursalesQuery->get();

                // 3. Pre-calcular datos por sucursal
                // Necesitamos: Cantidad Maquinas, Recaudo (Venta+IVA), Gastos (Desglosados)
                
                // A. Maquinas por sucursal (Total activo)
                $maquinasPorSucursal = Maquina::select('sucursal_id', DB::raw('count(*) as total'))
                    ->whereIn('sucursal_id', $sucursalesDb->pluck('id'))
                    ->groupBy('sucursal_id')
                    ->pluck('total', 'sucursal_id');

                // B. Lecturas (Recaudo)
                $recaudoPorSucursal = LecturaMaquina::query()
                     ->whereIn('sucursal_id', $sucursalesDb->pluck('id'))
                     ->where('confirmado', 1)
                     ->whereBetween('fecha', [$inicio, $fin])
                     ->select('sucursal_id', DB::raw('SUM(total_recaudo) as recaudo'))
                     ->groupBy('sucursal_id')
                     ->pluck('recaudo', 'sucursal_id');

                // C. Gastos Agrupados (Excluyendo 7 y 10)
                $gastosOperativos = Gasto::query()
                    ->whereIn('sucursal_id', $sucursalesDb->pluck('id'))
                    ->whereBetween('fecha', [$inicio, $fin])
                    ->whereNotIn('tipo_gasto_id', [7, 10]) // 7=Consignaciones, 10=QR
                    ->select('sucursal_id', 'tipo_gasto_id', DB::raw('SUM(valor) as total'))
                    ->with('tipo')
                    ->groupBy('sucursal_id', 'tipo_gasto_id')
                    ->get();
                
                // D. Consignaciones y QR
                $gastosEspeciales = Gasto::query()
                    ->whereIn('sucursal_id', $sucursalesDb->pluck('id'))
                    ->whereBetween('fecha', [$inicio, $fin])
                    ->whereIn('tipo_gasto_id', [7, 10])
                    ->select('sucursal_id', 'tipo_gasto_id', DB::raw('SUM(valor) as total'))
                    ->groupBy('sucursal_id', 'tipo_gasto_id')
                    ->get();

                // Estructura de columnas (Sucursales + Total)
                $cols = $sucursalesDb->map(function($s){ return ['id' => $s->id, 'nombre' => $s->nombre]; });
                // Vamos a construir un array estructurado para el frontend

                $dataReporte = [
                    'config_iva' => $configIva,
                    'sucursales' => $cols, // Headers
                    'maquinas' => [],
                    'financiero' => [], // Venta Neta, IVA, Venta+IVA
                    'gastos_detalla' => [],
                    'total_gastos' => [],
                    'especiales' => [], // Consignaciones, QR
                    'saldos_finales' => [], // Saldo, %
                ];

                // --- FILA MAQUINAS ---
                $rowMaquinas = [];
                $totalMaquinas = 0;
                foreach($cols as $s) {
                    $cant = $maquinasPorSucursal[$s['id']] ?? 0;
                    $rowMaquinas[$s['id']] = $cant;
                    $totalMaquinas += $cant;
                }
                $dataReporte['maquinas'] = ['values' => $rowMaquinas, 'total' => $totalMaquinas];

                // --- BLOQUE FINANCIERO ---
                // Calculos:
                // Base Impuesto Unitario = UVT_VAL * UVT_QTY (Si config existe, sino 0)
                // IVA Unitario = Base * (Porcentaje / 100)
                // IVA Total Sucursal = IVA Unitario * Cantidad Maquinas Sucursal
                $baseImpuesto = $valorUvt * $cantidadUvt;
                $ivaUnitario = $baseImpuesto * ($porcentajeIva / 100);

                $rowVentaMasIva = []; // Recaudo Total
                $rowIva = [];
                $rowVentaNeta = []; 
                
                $sumVentaMasIva = 0;
                $sumIva = 0;
                $sumVentaNeta = 0;

                foreach($cols as $s) {
                    $recaudo = $recaudoPorSucursal[$s['id']] ?? 0; // Venta + IVA
                    $maquinas = $maquinasPorSucursal[$s['id']] ?? 0;
                    
                    // Calculo IVA fijo por máquina (según requerimiento)
                    // "IVA se calcula con el valor de la uvt ... multiplicado por la cantidad de maquinas"
                    // NOTA: El requerimiento dice: "valor de la uvt multiplicado por la cantidad (de UVTs?)... y a ese total base calculamos porcentaje".
                    // Y luego: "IVA se calcula... num maquina".
                    // Asumiremos: IVA_Total = (IVA_Unitario * Maquinas)
                    // Pero ojo: El IVA no puede ser mayor al recaudo? O es un impuesto fijo? 
                    // En los juegos de suerte y azar suele ser fijo por máquina. Asumimos fijo.
                    
                    $ivaTotalSucursal = $ivaUnitario * $maquinas;
                    
                    // Venta Neta = Recaudo - IVA
                    $ventaNeta = $recaudo - $ivaTotalSucursal;

                    $rowVentaMasIva[$s['id']] = $recaudo;
                    $rowIva[$s['id']] = $ivaTotalSucursal;
                    $rowVentaNeta[$s['id']] = $ventaNeta;

                    $sumVentaMasIva += $recaudo;
                    $sumIva += $ivaTotalSucursal;
                    $sumVentaNeta += $ventaNeta;
                }

                $dataReporte['financiero'] = [
                    'venta_neta' => ['values' => $rowVentaNeta, 'total' => $sumVentaNeta],
                    'iva' => ['values' => $rowIva, 'total' => $sumIva],
                    'venta_mas_iva' => ['values' => $rowVentaMasIva, 'total' => $sumVentaMasIva],
                ];

                // --- BLOQUE GASTOS (Dinámico) ---
                // Obtener todos los tipos de gasto que aparecen en estas sucursales (excl 7 y 10)
                $tiposGastoIds = $gastosOperativos->pluck('tipo_gasto_id')->unique();
                $nombresGastos = \App\Models\TipoGasto::whereIn('id', $tiposGastoIds)->pluck('nombre', 'id');

                $rowsGastos = [];
                $totalGastosOperativosPorSucursal = []; // Acumulador vertical
                $granTotalGastosOperativos = 0; // Acumulador total horizontal

                // Inicializar acumuladores
                foreach($cols as $s) $totalGastosOperativosPorSucursal[$s['id']] = 0;

                foreach($nombresGastos as $idTipo => $nombreTipo) {
                    $row = [];
                    $totalRow = 0;
                    foreach($cols as $s) {
                        // Buscar el gasto de este tipo en esta sucursal
                        $val = $gastosOperativos->where('sucursal_id', $s['id'])->where('tipo_gasto_id', $idTipo)->sum('total');
                        $row[$s['id']] = $val;
                        $totalRow += $val;
                        
                        $totalGastosOperativosPorSucursal[$s['id']] += $val;
                    }
                    $rowsGastos[] = ['nombre' => $nombreTipo, 'values' => $row, 'total' => $totalRow];
                    $granTotalGastosOperativos += $totalRow;
                }

                $dataReporte['gastos_detalla'] = $rowsGastos;
                $dataReporte['total_gastos'] = ['values' => $totalGastosOperativosPorSucursal, 'total' => $granTotalGastosOperativos];

                // --- ESPECIALES (Consignaciones y QR) ---
                // ID 7
                $rowConsignaciones = [];
                $totalConsignaciones = 0;
                foreach($cols as $s) {
                    $val = $gastosEspeciales->where('sucursal_id', $s['id'])->where('tipo_gasto_id', 7)->sum('total');
                    $rowConsignaciones[$s['id']] = $val;
                    $totalConsignaciones += $val;
                }
                
                // ID 10
                $rowQr = [];
                $totalQr = 0;
                foreach($cols as $s) {
                    $val = $gastosEspeciales->where('sucursal_id', $s['id'])->where('tipo_gasto_id', 10)->sum('total');
                    $rowQr[$s['id']] = $val;
                    $totalQr += $val;
                }

                $dataReporte['especiales'] = [
                    'consignaciones' => ['values' => $rowConsignaciones, 'total' => $totalConsignaciones],
                    'qr' => ['values' => $rowQr, 'total' => $totalQr],
                ];

                // --- SALDOS ---
                // Saldo = Venta+IVA - (Total Gastos Operativos + Consignaciones + QR)
                // "saldo q seia el total de venta+iva, menos el total de gatos - CONSIGNACIONES - CODIGOS QR"
                // Interpretación: Recaudo - (GastosOp + Cons + QR).
                
                $rowSaldo = [];
                $totalSaldo = 0;
                
                $rowPorcentaje1 = []; // Gastos / Recaudo
                $rowPorcentaje2 = []; // 100 - %1

                // Totales para % globales
                // $sumVentaMasIva
                // Total Egresos Global = $granTotalGastosOperativos + $totalConsignaciones + $totalQr
                // Requerimiento %: "total gastos * 100 / venta + iva". (Refiriéndose a todos los gastos? O solo Operativos?)
                // En imagen: 21.2M Expenses / 40.3M Sale = 52.5%. (21.2M es Total Gastos Operativos).
                // Image row "TOTAL GASTOS" matches 21.2M.
                // Consignaciones (12M) are NOT inside "TOTAL GASTOS".
                // So the percentage is (Total_Gastos_Operativos / Recaudo). confirmamos con la imagen.
                
                foreach($cols as $s) {
                    $ingreso = $rowVentaMasIva[$s['id']];
                    $egresoOp = $totalGastosOperativosPorSucursal[$s['id']];
                    $cons = $rowConsignaciones[$s['id']];
                    $qr = $rowQr[$s['id']];

                    $saldo = $ingreso - $egresoOp - $cons - $qr;
                    
                    $rowSaldo[$s['id']] = $saldo;
                    $totalSaldo += $saldo;

                    // Porcentajes
                    $pct = 0;
                    if ($ingreso > 0) {
                        $pct = ($egresoOp * 100) / $ingreso;
                    }
                    $rowPorcentaje1[$s['id']] = $pct;
                    $rowPorcentaje2[$s['id']] = 100 - $pct;
                }

                // Totales %
                $totalPct1 = 0;
                if ($sumVentaMasIva > 0) {
                    $totalPct1 = ($granTotalGastosOperativos * 100) / $sumVentaMasIva;
                }

                $dataReporte['saldos_finales'] = [
                    'saldo' => ['values' => $rowSaldo, 'total' => $totalSaldo],
                    'porcentaje_gastos' => ['values' => $rowPorcentaje1, 'total' => $totalPct1],
                    'porcentaje_utilidad' => ['values' => $rowPorcentaje2, 'total' => 100 - $totalPct1],
                ];

                // Asignar al prop especial
                $bloques['reporteCasino'] = $dataReporte;
                
                // --- RESTAURAR VISUALIZACION ANTERIOR (TABLA RESUMEN + CREAR GRAFICO) ---
                // Reutilizamos la lógica anterior para llenar tablaPrincipal y chart
                // (sucursal, recaudo) - SIMPLIFICADO
                // OJO: Ya tenemos $recaudoPorSucursal (Venta+IVA) y $totalGastosOperativosPorSucursal (Solo operativos)
                // En la versión anterior:
                // $porSucursal query directo...
                // Recalculemos rápido para ser consistentes con la tabla nueva
                
                $porSucursal = $sucursalesDb->map(function($s) use ($rowVentaMasIva, $totalGastosOperativosPorSucursal, $rowConsignaciones, $rowQr) {
                    $recaudo = $rowVentaMasIva[$s->id] ?? 0;
                    $gastos = ($totalGastosOperativosPorSucursal[$s->id] ?? 0) + ($rowConsignaciones[$s->id] ?? 0) + ($rowQr[$s->id] ?? 0);
                    $neto = $recaudo - $gastos;
                    
                    return (object)[
                        'sucursal' => $s->nombre,
                        'recaudo' => $recaudo,
                        'gastos' => $gastos,
                        'total_neto' => $neto
                    ];
                });
                
                $bloques['tablaPrincipal'] = $porSucursal;

                $chart = [
                    'labels' => $porSucursal->pluck('sucursal'),
                    'data'   => $porSucursal->pluck('recaudo'), // Graficamos recaudo o neto? Antes era recaudo.
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
                        proveedores.nombre AS proveedor,
                        gastos.descripcion,
                        gastos.valor AS total
                    ")
                    ->join('sucursales', 'sucursales.id', '=', 'gastos.sucursal_id')
                    ->join('tipos_gasto', 'gastos.tipo_gasto_id', '=', 'tipos_gasto.id')
                    ->leftJoin('proveedores', 'gastos.proveedor_id', '=', 'proveedores.id')
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

                // 3. Retenciones - Total y cantidad para el período
                $retencionesData = \App\Models\Retencion::query()
                    ->whereBetween('fecha', [$inicio, $fin])
                    ->when($sucursalId, fn($q) => $q->where('sucursal_id', $sucursalId))
                    ->selectRaw('
                        COUNT(*) as cantidad_retenciones,
                        COALESCE(SUM(valor_retencion), 0) as total_retenciones
                    ')
                    ->first();

                $bloques['tablaRetenciones'] = [
                    'total_retenciones' => (float)$retencionesData->total_retenciones,
                    'cantidad_retenciones' => (int)$retencionesData->cantidad_retenciones,
                ];

                // 4. Bases de monedas y billetes de la sucursal
                $sucursalData = null;
                if ($sucursalId) {
                    $sucursalData = Sucursal::select('nombre', 'base_monedas', 'base_billetes')
                        ->find($sucursalId);
                }

                $bloques['tablaBases'] = $sucursalData ? [
                    'sucursal_nombre' => $sucursalData->nombre,
                    'base_monedas' => (float)($sucursalData->base_monedas ?? 0),
                    'base_billetes' => (float)($sucursalData->base_billetes ?? 0),
                    'total_base' => (float)(($sucursalData->base_monedas ?? 0) + ($sucursalData->base_billetes ?? 0)),
                ] : null;

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
        $maquinas   = Maquina::select('id', 'ndi', 'nombre', 'denominacion', 'sucursal_id')->get()
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
            'tablaPrincipal'   => $bloques['tablaPrincipal'] ?? [],
            'tablaSecundaria'  => $bloques['tablaSecundaria'] ?? [],            
            'tablaGastosAgrupados'  => $bloques['tablaGastosAgrupados'] ?? [],
            'reporteCasino'    => $bloques['reporteCasino'] ?? null,
            'tablaRetenciones' => $bloques['tablaRetenciones'] ?? null,
            'tablaBases'       => $bloques['tablaBases'] ?? null,
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
