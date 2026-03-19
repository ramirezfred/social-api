<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuotePago extends Model
{
    use HasFactory;

    protected $table = 'quote_pagos';

    protected $fillable = [
        'quote_id', 
        'monto', 
        'tipo', 
        'fecha',
        'referencia', 
        'notas'
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'fecha' => 'date'
    ];

    /* =======================
       Relaciones
    ======================= */
    public function quote()
    {
        return $this->belongsTo(Quote::class, 'quote_id');
    }
}
