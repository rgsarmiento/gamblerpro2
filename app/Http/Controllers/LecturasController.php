<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\{LecturaMaquina, Maquina, Sucursal, Casino, Gasto};
use Illuminate\Validation\ValidationException;

use Illuminate\Support\Facades\DB;

class LecturasController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $req)
    {

        $user = $req->user();

        $q = LecturaMaquina::with(['maquina:id,ndi,nombre,denominacion', 'sucursal:id,nombre,casino_id'])
            ->orderByDesc('fecha');

        // 🔹 Clonar query base para obtener última fecha confirmada con los mismos filtros de rol
        $qUltimaFecha = clone $q;

        // 🔹 Filtro dinámico según el rol (aplicado a ambas queries)
        $aplicarFiltrosRol = function ($query) use ($user, $req) {
            if ($user->hasRole('master_admin')) {
                if ($req->filled('casino_id')) {
                    $query->whereHas('sucursal', fn($qq) => $qq->where('casino_id', $req->casino_id));
                }
                if ($req->filled('sucursal_id')) {
                    $query->where('sucursal_id', $req->sucursal_id);
                } else {
                    // 👇 si no hay sucursal seleccionada, no mostrar nada
                    $query->whereRaw('1=0');
                }
            } elseif ($user->hasRole('casino_admin')) {
                $query->whereHas('sucursal', fn($qq) => $qq->where('casino_id', $user->casino_id));

                if ($req->filled('sucursal_id')) {
                    $query->where('sucursal_id', $req->sucursal_id);
                } else {
                    $query->whereRaw('1=0');
                }
            } elseif ($user->hasAnyRole(['sucursal_admin', 'cajero'])) {
                $query->where('sucursal_id', $user->sucursal_id);
            }
        };

        // Aplicar filtros de rol a la query de última fecha confirmada
        $aplicarFiltrosRol($qUltimaFecha);

        $ultimaFechaConfirmada = $qUltimaFecha->where('confirmado', 1)
            ->orderByDesc('fecha')
            ->value('fecha'); // puede ser null si no existen lecturas

        // Aplicar filtros de rol a la query principal
        $aplicarFiltrosRol($q);


        // 🔹 Filtros opcionales por fecha o máquina
        if ($req->filled('fecha')) {
            $q->where('fecha', $req->fecha);
        }
        if ($req->filled('maquina_id')) {
            $q->where('maquina_id', $req->maquina_id);
        }

        // 🔹 Mostrar solo las lecturas pendientes (sin confirmar)
        //$q->where('confirmado', 0);


        $fecha = $req->fecha;

        // 🔹 Siempre filtrar por fecha si llega
        if ($req->filled('fecha')) {
            $q->whereDate('fecha', $fecha);
        }else{
            $q->whereDate('fecha', now()->toDateString());
        }

        // --------------------------------------------------
        // 1️⃣ ¿Hay lecturas PENDIENTES en esta fecha?
        // --------------------------------------------------
        $hayPendientes = (clone $q)->where('confirmado', 0)->count() > 0;

        if ($hayPendientes) {
            // Mostrar solo las pendientes
            $q->where('confirmado', 0);
            $lecturasConfirmadas = false;
        } else {
            // --------------------------------------------------
            // 2️⃣ Si no hay pendientes, mostrar confirmadas
            // --------------------------------------------------
            $q->where('confirmado', 1);
            $lecturasConfirmadas = $q->count() > 0;
        }


        // 🔹 OBTENER EL TOTAL ANTES DE PAGINAR
        $totalRecaudado = (clone $q)->sum('total_recaudo');
        $cantidadRegistros = (clone $q)->count();

        $lecturas = $q->paginate(50)->withQueryString();

        // Datos para selects
        $casinos = $user->hasRole('master_admin')
            ? Casino::select('id', 'nombre')->get()
            : [];

        $sucursales = $user->hasAnyRole(['master_admin', 'casino_admin'])
            ? Sucursal::select('id', 'nombre', 'casino_id')
            ->when($user->hasRole('casino_admin'), fn($qq) => $qq->where('casino_id', $user->casino_id))
            ->get()
            : [];

        $maquinas = Maquina::select('id', 'ndi', 'nombre', 'sucursal_id', 'denominacion', 'ultimo_neto_final')->get()
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

        $pendientes = $lecturas->total() > 0;

        // =========================
        // 🔹 DETERMINAR TIPO DE CONSULTA
        // =========================
        $tipoConsulta = 'sucursal'; // default
        
        if ($req->filled('maquina_id')) {
            $tipoConsulta = 'maquina';
        } elseif ($req->filled('casino_id') && !$req->filled('sucursal_id')) {
            $tipoConsulta = 'casino';
        } elseif ($req->filled('sucursal_id')) {
            $tipoConsulta = 'sucursal';
        }

        // =========================
        // 🔹 OBTENER GASTOS DEL PERÍODO
        // =========================
        $gastosQuery = Gasto::with(['tipo', 'proveedor', 'sucursal'])
            ->when($req->filled('fecha'), fn($q) => $q->whereDate('fecha', $req->fecha))
            ->when(!$req->filled('fecha'), fn($q) => $q->whereDate('fecha', now()));

        // Aplicar filtros de rol a gastos
        if ($user->hasRole('master_admin')) {
            if ($req->filled('casino_id')) {
                $gastosQuery->whereHas('sucursal', fn($qq) => $qq->where('casino_id', $req->casino_id));
            }
            if ($req->filled('sucursal_id')) {
                $gastosQuery->where('sucursal_id', $req->sucursal_id);
            }
        } elseif ($user->hasRole('casino_admin')) {
            $gastosQuery->whereHas('sucursal', fn($qq) => $qq->where('casino_id', $user->casino_id));
            if ($req->filled('sucursal_id')) {
                $gastosQuery->where('sucursal_id', $req->sucursal_id);
            }
        } elseif ($user->hasAnyRole(['sucursal_admin', 'cajero'])) {
            $gastosQuery->where('sucursal_id', $user->sucursal_id);
        }

        $gastosPeriodo = $gastosQuery->get();
        $totalGastos = $gastosPeriodo->sum('valor');

        // =========================
        // 🔹 GASTOS AGRUPADOS POR TIPO (solo para consulta por sucursal)
        // =========================
        $gastosPorTipo = [];
        if ($tipoConsulta === 'sucursal' && $gastosPeriodo->count() > 0) {
            $gastosPorTipo = $gastosPeriodo->groupBy('tipo_gasto_id')->map(function ($items) use ($totalGastos) {
                $total = $items->sum('valor');
                return [
                    'tipo' => $items->first()->tipo->nombre ?? 'Sin tipo',
                    'cantidad' => $items->count(),
                    'total' => $total,
                    'porcentaje' => $totalGastos > 0 ? round(($total / $totalGastos) * 100, 2) : 0,
                ];
            })->values();
        }

        // =========================
        // 🔹 RECAUDO POR SUCURSAL (para consulta por casino)
        // =========================
        $recaudoPorSucursal = [];
        if ($tipoConsulta === 'casino') {
            // Agrupar lecturas por sucursal
            $recaudoPorSucursal = DB::table('lectura_maquinas')
                ->join('sucursales', 'lectura_maquinas.sucursal_id', '=', 'sucursales.id')
                ->where('sucursales.casino_id', $req->casino_id)
                ->when($req->filled('fecha'), fn($q) => $q->whereDate('lectura_maquinas.fecha', $req->fecha))
                ->when(!$req->filled('fecha'), fn($q) => $q->whereDate('lectura_maquinas.fecha', now()))
                ->select(
                    'sucursales.id as sucursal_id',
                    'sucursales.nombre as sucursal',
                    DB::raw('SUM(lectura_maquinas.total_recaudo) as recaudo')
                )
                ->groupBy('sucursales.id', 'sucursales.nombre')
                ->get();

            // Agregar gastos a cada sucursal
            foreach ($recaudoPorSucursal as $item) {
                $gastosSucursal = $gastosPeriodo->where('sucursal_id', $item->sucursal_id)->sum('valor');
                $item->gastos = $gastosSucursal;
                $item->total_neto = $item->recaudo - $gastosSucursal;
            }
        }

        return Inertia::render('Lecturas/Index', [
            'lecturas'   => $lecturas,
            'ultimaFechaConfirmada' => $ultimaFechaConfirmada,
            'lecturas_confirmadas' => $lecturasConfirmadas,
            'pendientes' => $pendientes,
            'total_registros' => $cantidadRegistros,
            'total_recaudado' => $totalRecaudado,
            'total_gastos' => $totalGastos,
            'casinos'    => $casinos,
            'sucursales' => $sucursales,
            'maquinas'   => $maquinas,
            'gastos_periodo' => $gastosPeriodo,
            'gastos_por_tipo' => $gastosPorTipo,
            'recaudo_por_sucursal' => $recaudoPorSucursal,
            'tipo_consulta' => $tipoConsulta,
            'user'       => $user->only(['id', 'name', 'sucursal_id', 'casino_id']) + ['roles' => $user->getRoleNames()],
        ]);
    }




    public function store(Request $req)
    {
        // 1️⃣ Validar entrada, incluyendo la fecha
        $data = $req->validate([
            'sucursal_id' => 'required|integer',
            'maquina_id' => 'required|integer',
            'fecha' => 'required|date',
            'entrada' => 'required|numeric',
            'salida' => 'nullable|numeric',
            'jackpots' => 'nullable|numeric',
            'neto_inicial' => 'required|numeric',
            'neto_final' => 'required|numeric',
            'total_creditos' => 'required|numeric',
            'total_recaudo' => 'required|numeric',
        ]);

        // 3️⃣ Si vienen vacíos, asignar 0
        $data['neto_inicial'] = $data['neto_inicial'] ?? 0;
        $data['entrada'] = $data['entrada'] ?? 0;
        $data['salida'] = $data['salida'] ?? 0;
        $data['jackpots'] = $data['jackpots'] ?? 0;

        // 2️⃣ Si están vacíos, poner 0
        $data['salida'] = $data['salida'] ?? 0;
        $data['jackpots'] = $data['jackpots'] ?? 0;

        $maquina = Maquina::findOrFail($data['maquina_id']);

        // 3️⃣ Validar que la fecha no sea anterior al último cierre de esa máquina
        $ultimaConfirmada = LecturaMaquina::where('maquina_id', $data['maquina_id'])
            ->where('sucursal_id', $data['sucursal_id'])
            ->where('confirmado', 1)
            ->orderByDesc('fecha')
            ->value('fecha');

        // if ($ultimaConfirmada && $data['fecha'] < $ultimaConfirmada) {
        //     throw ValidationException::withMessages([
        //         'fecha' => "No puedes registrar lecturas antes de la última fecha confirmada ($ultimaConfirmada).",
        //     ]);
        // }

        // 4️⃣ Si el usuario es cajero, se recalcula el neto inicial al último neto final
        if ($req->user()->hasRole('cajero')) {
            $data['neto_inicial'] = $maquina->ultimo_neto_final ?? 0;
        }

        // 5️⃣ Verificar duplicados NO confirmados (pendientes)

        if ($req->user()->hasRole('master_admin')) {
            $confirmado = 1;
        } else {
            $confirmado = 0;
        }

        $existePendiente = LecturaMaquina::where('maquina_id', $data['maquina_id'])
            ->where('sucursal_id', $data['sucursal_id'])
            ->where('confirmado', 0)
            ->exists();

        if ($existePendiente) {
            throw ValidationException::withMessages([
                'maquina_id' => 'Ya existe una lectura pendiente para esta máquina.',
            ]);
        }

        // 6️⃣ Verificar si ya existe una lectura confirmada en la MISMA FECHA
        $existeConfirmadaMismaFecha = LecturaMaquina::where('maquina_id', $data['maquina_id'])
            ->where('sucursal_id', $data['sucursal_id'])
            ->where('confirmado', 1)
            ->whereDate('fecha', $data['fecha'])
            ->exists();

        if ($existeConfirmadaMismaFecha) {
            throw ValidationException::withMessages([
                'fecha' => "Ya existe una lectura confirmada para esta máquina en la fecha {$data['fecha']}.",
            ]);
        }

        if ($confirmado) {
            // 7️⃣ Guardar lectura con la fecha seleccionada (NO usar now())
            $lectura = LecturaMaquina::create($data + [
                'user_id' => $req->user()->id,
                'fecha_confirmacion' => now(),
                'confirmado' => $confirmado
            ]);
        } else {
            // 7️⃣ Guardar lectura con la fecha seleccionada (NO usar now())
            $lectura = LecturaMaquina::create($data + [
                'user_id' => $req->user()->id,
            ]);
        }

        // 8️⃣ Actualizar el último neto final de la máquina
        $maquina->ultimo_neto_final = $data['neto_final'];
        $maquina->save();

        return redirect()->back()->with('success', 'Lectura registrada exitosamente');
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
    public function update(Request $req, LecturaMaquina $lectura)
    {
        // 1️⃣ Validar permisos
        if ($lectura->confirmado && !$req->user()->hasRole('master_admin')) {
            throw ValidationException::withMessages([
                'lectura' => 'No tienes permisos para editar una lectura confirmada.'
            ]);
        }
        
        // 2️⃣ Validar datos
        $data = $req->validate([
            'neto_inicial' => 'required|numeric',
            'entrada' => 'required|numeric',
            'salida' => 'required|numeric',
            'jackpots' => 'required|numeric',
        ]);

        // 3️⃣ Si vienen vacíos, asignar 0
        $data['neto_inicial'] = $data['neto_inicial'] ?? 0;
        $data['entrada'] = $data['entrada'] ?? 0;
        $data['salida'] = $data['salida'] ?? 0;
        $data['jackpots'] = $data['jackpots'] ?? 0;

        DB::transaction(function () use ($lectura, $data) {
            // 3️⃣ Recalcular y actualizar la lectura actual
            $nuevoNetoFinal = $data['entrada'] - $data['salida'] - $data['jackpots'];
            $nuevosCreditos = $nuevoNetoFinal - $data['neto_inicial'];
            $nuevoRecaudo = $nuevosCreditos * ($lectura->maquina->denominacion ?? 0);

            $lectura->update([
                'neto_inicial' => $data['neto_inicial'],
                'entrada' => $data['entrada'],
                'salida' => $data['salida'],
                'jackpots' => $data['jackpots'],
                'neto_final' => $nuevoNetoFinal,
                'total_creditos' => $nuevosCreditos,
                'total_recaudo' => $nuevoRecaudo,
            ]);
            
            // 4️⃣ Propagar a todas las lecturas siguientes de la misma máquina
            $siguientes = LecturaMaquina::with('maquina')
                ->where('maquina_id', $lectura->maquina_id)
                ->where('fecha', '>', $lectura->fecha)
                ->orderBy('fecha', 'asc')
                ->get();

            $prevNeto = $nuevoNetoFinal;

            foreach ($siguientes as $s) {
                // neto_inicial para esta lectura es el neto_final anterior
                $s->neto_inicial = $prevNeto;

                $s->neto_final = $s->entrada - $s->salida - $s->jackpots;

                // total_creditos y total_recaudo se recalculan (neto_final permanece)
                $s->total_creditos = $s->neto_final - $s->neto_inicial;
                $den = $s->maquina->denominacion ?? $lectura->maquina->denominacion ?? 0;
                $s->total_recaudo = $s->total_creditos * $den;

                $s->save();

                // preparar para la siguiente iteración
                $prevNeto = $s->neto_final;
            }

            // 5️⃣ Actualizar ultimo_neto_final de la máquina al último neto final conocido
            $maquina = $lectura->maquina;
            $maquina->ultimo_neto_final = $prevNeto;
            $maquina->save();
        });

        return back()->with('success', 'Lectura actualizada y valores recalculados correctamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $req, LecturaMaquina $lectura)
    {
        // 🚫 No permitir eliminar lecturas con cierre
        if ($lectura->confirmado && !$req->user()->hasRole('master_admin')) {
            return back()->withErrors([
                'lectura' => 'No se puede eliminar una lectura ya confirmada.'
            ]);
        }

        // Guardamos el ID de la máquina
        $maquinaId = $lectura->maquina_id;

        // 🔙 Guardamos el neto inicial ANTES de eliminar
        $netoAnterior = $lectura->neto_inicial;

        // Eliminamos la lectura
        $lectura->delete();

        // 🔄 Actualizar el último neto final en la máquina al valor anterior
        $maquina = Maquina::find($maquinaId);
        if ($maquina) {
            $maquina->ultimo_neto_final = $netoAnterior;
            $maquina->save();
        }

        return redirect()->back()->with('success', 'Lectura eliminada correctamente');
    }


    public function confirmarLecturas(Request $req)
    {
        $user = $req->user();

        // Validar que venga la sucursal (para los roles que la requieren)
        if ($user->hasAnyRole(['master_admin', 'casino_admin'])) {
            $req->validate([
                'sucursal_id' => 'required|exists:sucursales,id'
            ]);

            $sucursalId = $req->sucursal_id;
        } else {
            $sucursalId = $user->sucursal_id;
        }

        // Obtener las lecturas pendientes para esa sucursal
        $lecturas = LecturaMaquina::where('sucursal_id', $sucursalId)
            ->where('confirmado', 0)
            ->get();

        if ($lecturas->isEmpty()) {
            return back()->withErrors(['lecturas' => 'No hay lecturas pendientes por confirmar en esta sucursal.']);
        }

        DB::transaction(function () use ($lecturas) {
            foreach ($lecturas as $lectura) {
                // Marcar como confirmada
                $lectura->update([
                    'confirmado' => 1,
                    'fecha_confirmacion' => now(),
                ]);

                // // Actualizar el neto final de la máquina
                // $lectura->maquina->update([
                //     'ultimo_neto_final' => $lectura->neto_final,
                // ]);
            }
        });

        return back()->with('success', 'Lecturas confirmadas correctamente.');
    }
}
