<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CierreCaja extends Model
{
    protected $table = 'cierres_caja';

    protected $fillable = [
     'sucursal_id',
     'user_id',
     'fecha_inicio',
     'fecha_fin',
     'total_recaudado',
     'total_gastos',
     'total_cierre',
     'observaciones'];


    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class);
    }
    public function user() {
        return $this->belongsTo(User::class);
    }
    public function lecturas()
    {
        return $this->hasMany(LecturaMaquina::class, 'cierre_id');
    }
    public function gastos()
    {
        return $this->hasMany(Gasto::class, 'cierre_id');
    }
}
