<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Maquina extends Model
{
    protected $table = 'maquinas';
    protected $fillable =
    ['ndi',
    'sucursal_id',
     'nombre',
     'denominacion',
     'codigo_interno',
     'ultimo_neto_final',
     'activa'];
     
    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class);
    }
    public function lecturas()
    {
        return $this->hasMany(LecturaMaquina::class);
    }
}
