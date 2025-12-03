<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Retencion extends Model
{
    protected $table = 'retenciones';

    protected $fillable = [
        'fecha',
        'sucursal_id',
        'user_id',
        'cedula',
        'nombre',
        'valor_premio',
        'valor_retencion',
        'observacion',
    ];

    protected $casts = [
        'fecha' => 'date',
        'valor_premio' => 'decimal:2',
        'valor_retencion' => 'decimal:2',
    ];

    /**
     * Relación con Sucursal
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    /**
     * Relación con User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Alias para la relación con User (para consistencia con otras tablas)
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
