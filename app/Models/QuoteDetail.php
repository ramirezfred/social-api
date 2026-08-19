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
        // 'publication_id',

        'modelo', 
        'talla',
        'color', 

        'cantidad', 
        'cantidad_recolectada',
        'precio_unitario',
        'porcentaje_desc', 
        'porcentaje_impuesto', 

        'subtotal',
        'impuesto',
        'descuento', 
        'total',
        'pago_proveedor_estado', // 'pendiente', 'pagado'
        'cash_close_id',
    ];

    protected $casts = [
        'cantidad' => 'decimal:4',
        'cantidad_recolectada' => 'decimal:4',
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

    public function quote()
    {
        return $this->belongsTo(Quote::class, 'quote_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function publication()
    {
        return $this->belongsTo(Publication::class, 'publication_id');
    }

    /* =======================
       Scopes
    ======================= */

    public function scopePendientesRecoleccion($query)
    {
        return $query
            ->whereNotNull('supplier_id')
            ->whereColumn('cantidad', '>', 'cantidad_recolectada')
            ->whereHas('quote', function ($q) {
                $q->noEliminados()
                // ->where('estado', 'en curso');
                ->where('id', '>=', 247); // Temporalmente, para pruebas
            });
    }
}
