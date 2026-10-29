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
        'notas',
        'comprobante'
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'fecha' => 'date:Y-m-d'
    ];

    protected $appends  = ['url'];  // agrega el atributo al JSON automáticamente

    public function getUrlAttribute()    {
        if($this->comprobante){
            // return Storage::url($this->image_path);
            return asset($this->comprobante);
        }else{
            return null;
        }
        
    }

    /* =======================
       Relaciones
    ======================= */
    public function quote()
    {
        return $this->belongsTo(Quote::class, 'quote_id');
    }
}
