<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuoteDetail extends Model
{
    use HasFactory;

    protected $table = 'quote_details';

    protected $fillable = [
        'quote_id', 
        'supplier_id', 

        'modelo', 
        'talla',
        'color', 

        'cantidad', 
        'precio_unitario',
        'porcentaje_desc', 
        'porcentaje_impuesto', 

        'subtotal',
        'impuesto',
        'descuento', 
        'total',
    ];

    protected $casts = [
        'cantidad' => 'decimal:4',
        'precio_unitario' => 'decimal:4',
        'porcentaje_desc' => 'decimal:2',
        'porcentaje_impuesto' => 'decimal:2',
        'descuento' => 'decimal:4',
        'impuesto' => 'decimal:4',
        'subtotal' => 'decimal:4',
        'total' => 'decimal:4'
    ];

    /* =======================
       Relaciones
    ======================= */
    // public function product()
    // {
    //     return $this->belongsTo(ErpProduct::class, 'product_id');
    // }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
