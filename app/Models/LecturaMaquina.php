<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LecturaMaquina extends Model
{
    protected $table = 'lecturas_maquinas';

    protected $fillable = [
        'sucursal_id',
        'maquina_id',
        'user_id',
        'entrada',
        'salida',
        'jackpots',
        'neto_inicial',
        'neto_final',
        'total_creditos',
        'total_recaudo',
        'fecha',
        'confirmado',
        'fecha_confirmacion',
    ];


    // protected static function booted()
    // {
    //     static::creating(function ($l) {
    //         $prev = self::where('maquina_id', $l->maquina_id)
    //             ->where('fecha', '<', $l->fecha)
    //             ->orderByDesc('fecha')
    //             ->first();
    //         $l->neto_inicial = $prev?->neto_final ?? 0;
    //         $l->neto_final = ($l->entrada - $l->salida - $l->jackpots);
    //         $l->total_creditos = ($l->neto_final - $l->neto_inicial);
    //         $l->total_recaudo = $l->total_creditos * ($l->maquina->denominacion ?? 0);
    //     });


    //     static::updating(function ($l) {
    //         if ($l->isDirty(['entrada', 'salida', 'jackpots', 'neto_inicial'])) {
    //             $l->neto_final = ($l->entrada - $l->salida - $l->jackpots);
    //             $l->total_creditos = ($l->neto_final - $l->neto_inicial);
    //             $l->total_recaudo = $l->total_creditos * ($l->maquina->denominacion ?? 0);
    //         }
    //     });
    // }


    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class);
    }
    public function maquina()
    {
        return $this->belongsTo(Maquina::class);
    }
    public function cierre()
    {
        return $this->belongsTo(CierreCaja::class, 'cierre_id');
    }
}
