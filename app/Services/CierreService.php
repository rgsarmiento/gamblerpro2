<?php

namespace App\Services;

use App\Models\CierreCaja;
use App\Models\Gasto;
use App\Models\LecturaMaquina;
use Illuminate\Support\Facades\DB;

class CierreService
{
    /**
     * Cierra todos los movimientos pendientes (sin cierre) de una sucursal.
     * Calcula totales y asigna cierre_id a lecturas y gastos involucrados.
     *
     * @throws \RuntimeException si no hay movimientos pendientes.
     */
    public function cerrarSucursal(int $sucursalId, int $userId, ?string $observaciones = null): CierreCaja
    {
        return DB::transaction(function () use ($sucursalId, $userId, $observaciones) {
            // Movimientos pendientes (sin cierre)
            $lecturas = LecturaMaquina::where('sucursal_id', $sucursalId)
                ->whereNull('cierre_id')
                ->get();

            $gastos = Gasto::where('sucursal_id', $sucursalId)
                ->whereNull('cierre_id')
                ->get();

            if ($lecturas->isEmpty() && $gastos->isEmpty()) {
                throw new \RuntimeException('No hay movimientos pendientes para cerrar.');
            }

            // Fechas del período (desde el primer movimiento pendiente hasta ahora)
            $fechaInicio = collect([
                $lecturas->min('created_at'),
                $gastos->min('created_at'),
            ])->filter()->min() ?? now();

            $fechaFin = now();

            // Totales
            $totalRecaudado = $lecturas->sum('total_recaudo');
            $totalGastos    = $gastos->sum('valor');
            $totalCierre    = $totalRecaudado - $totalGastos;

            // Crear cierre
            $cierre = CierreCaja::create([
                'sucursal_id'     => $sucursalId,
                'user_id'         => $userId,
                'fecha_inicio'    => $fechaInicio,
                'fecha_fin'       => $fechaFin,
                'total_recaudado' => $totalRecaudado,
                'total_gastos'    => $totalGastos,
                'total_cierre'    => $totalCierre,
                'observaciones'   => $observaciones,
            ]);

            // Marcar movimientos con el cierre
            if ($lecturas->isNotEmpty()) {
                LecturaMaquina::whereIn('id', $lecturas->pluck('id'))
                    ->update(['cierre_id' => $cierre->id]);
            }

            if ($gastos->isNotEmpty()) {
                Gasto::whereIn('id', $gastos->pluck('id'))
                    ->update(['cierre_id' => $cierre->id]);
            }

            return $cierre;
        });
    }
}
