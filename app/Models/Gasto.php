<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gasto extends Model
{
    protected $table = 'gastos';
    
    protected $fillable = ['sucursal_id',
     'tipo_gasto_id',
     'proveedor_id',
     'user_id',
     'fecha',
     'valor',
     'descripcion',
     'cierre_id'];
    
    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class);
    }
    public function tipo()
    {
        return $this->belongsTo(TipoGasto::class, 'tipo_gasto_id');
    }
    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }
    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function cierre()
    {
        return $this->belongsTo(CierreCaja::class, 'cierre_id');
    }
}
