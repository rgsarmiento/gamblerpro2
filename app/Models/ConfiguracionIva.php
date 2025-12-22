<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguracionIva extends Model
{
    use HasFactory;

    protected $table = 'configuracion_iva';

    protected $fillable = [
        'anio',
        'valor_uvt',
        'cantidad_uvt',
        'porcentaje_iva',
    ];
}
