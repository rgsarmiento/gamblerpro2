<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Casino extends Model
{
    protected $table = 'casinos';
    protected $fillable = ['nombre'];
    
    public function sucursales()
    {
        return $this->hasMany(Sucursal::class);
    }
}
