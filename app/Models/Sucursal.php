<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sucursal extends Model
{
    protected $table = 'sucursales';

    protected $fillable = [
        'casino_id',
        'nombre',
        'telefono',
        'direccion',
        'base_monedas',
        'base_billetes',
        'activo',
    ];

    protected $casts = [
        'base_monedas' => 'decimal:2',
        'base_billetes' => 'decimal:2',
        'activo' => 'boolean',
    ];
    
    public function casino()
    {
        return $this->belongsTo(Casino::class);
    }
    public function maquinas()
    {
        return $this->hasMany(Maquina::class);
    }
    public function lecturasMaquinas()
{
    return $this->hasMany(LecturaMaquina::class, 'sucursal_id');
}
    public function usuarios()
    {
        return $this->hasMany(User::class);
    }
    public function proveedores()
    {
        return $this->hasMany(Proveedor::class);
    }
    public function gastos()
    {
        return $this->hasMany(Gasto::class);
    }
}
