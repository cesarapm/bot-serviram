<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cita extends Model
{
    protected $fillable = [
        'estatus',
        'nombre',
        'servicio',
        'precio_servicio',
        'fecha',
        'hora',
        'numero_celular',
        'estado',
        'ciudad',
        'direccion',
    ];

    protected $casts = [
        'fecha'           => 'date',
        'precio_servicio' => 'float',
    ];

    /** Scope para filtrar por mes/año (útil para vista calendario) */
    public function scopeDelMes($query, int $year, int $month)
    {
        return $query->whereYear('fecha', $year)->whereMonth('fecha', $month);
    }
}
