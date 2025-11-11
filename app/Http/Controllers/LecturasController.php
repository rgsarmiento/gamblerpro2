<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\{LecturaMaquina, Maquina, Sucursal, Casino};
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

        // 🔹 Filtro dinámico según el rol
        if ($user->hasRole('master_admin')) {
            if ($req->filled('casino_id')) {
                $q->whereHas('sucursal', fn($qq) => $qq->where('casino_id', $req->casino_id));
            }
            if ($req->filled('sucursal_id')) {
                $q->where('sucursal_id', $req->sucursal_id);
            } else {
                // 👇 si no hay sucursal seleccionada, no mostrar nada
                $q->whereRaw('1=0');
            }
        } elseif ($user->hasRole('casino_admin')) {
            $q->whereHas('sucursal', fn($qq) => $qq->where('casino_id', $user->casino_id));

            if ($req->filled('sucursal_id')) {
                $q->where('sucursal_id', $req->sucursal_id);
            } else {
                $q->whereRaw('1=0');
            }
        } elseif ($user->hasAnyRole(['sucursal_admin', 'cajero'])) {
            $q->where('sucursal_id', $user->sucursal_id);
        }


        // 🔹 Filtros opcionales por fecha o máquina
        if ($req->filled('fecha')) {
            $q->where('fecha', $req->fecha);
        }
        if ($req->filled('maquina_id')) {
            $q->where('maquina_id', $req->maquina_id);
        }

        // 🔹 Mostrar solo las lecturas pendientes (sin confirmar)
        $q->where('confirmado', 0);

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

        $maquinas = Maquina::select('id', 'ndi', 'nombre', 'sucursal_id', 'denominacion', 'ultimo_neto_final')->get();

        $pendientes = $lecturas->total() > 0;

        return Inertia::render('Lecturas/Index', [
            'lecturas'   => $lecturas,
            'pendientes' => $pendientes,
            'total_registros' => $lecturas->total(),
            'total_recaudado' => $lecturas->sum('total_recaudo'),
            'casinos'    => $casinos,
            'sucursales' => $sucursales,
            'maquinas'   => $maquinas,
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
            'maquina_id' => 'required|integer',
            'entrada' => 'required|numeric',
            'salida' => 'nullable|numeric',
            'jackpots' => 'nullable|numeric',
            'neto_inicial' => 'required|numeric',
            'neto_final' => 'required|numeric',
            'total_creditos' => 'required|numeric',
            'total_recaudo' => 'required|numeric',
        ]);

        // si están vacíos, poner 0
        $data['salida'] = $data['salida'] ?? 0;
        $data['jackpots'] = $data['jackpots'] ?? 0;

        // Si el usuario es cajero, forzar neto_inicial con el último neto_final de la máquina
        if ($req->user()->hasRole('cajero')) {
            $maquina = Maquina::find($data['maquina_id']);
            $data['neto_inicial'] = $maquina->ultimo_neto_final ?? 0;
        }

        $existe = LecturaMaquina::where('maquina_id', $data['maquina_id'])
            ->where('sucursal_id', $data['sucursal_id'])
            ->where('confirmado', 0)
            ->exists();

        if ($existe) {
            throw ValidationException::withMessages([
                'maquina_id' => 'Ya existe una lectura para esta máquina.',
            ]);
        }


        LecturaMaquina::create($data + [
            'user_id' => $req->user()->id,
            'fecha' => now(),
        ]);


        // 🔄 Actualizar el último neto final de la máquina
        $maquina = Maquina::find($data['maquina_id']);
        if ($maquina) {
            $maquina->ultimo_neto_final = $data['neto_final'];
            $maquina->save();
        }

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
        $this->authorize('update', $lectura);
        $data = $req->validate([
            'entrada' => 'required|numeric',
            'salida' => 'required|numeric',
            'jackpots' => 'required|numeric',
        ]);
        $lectura->fill($data)->save();
        return $lectura->fresh(['maquina', 'sucursal']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $req, LecturaMaquina $lectura)
    {
        // 🚫 No permitir eliminar lecturas con cierre
        if ($lectura->confirmado) {
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
