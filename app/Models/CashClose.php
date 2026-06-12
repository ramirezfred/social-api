<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashClose extends Model
{
    use HasFactory;

    protected $table = 'cash_closes';

    protected $fillable = [
        'tipo', // 'proveedor', 'ingresos_gastos', 'comision_envios'
        'monto', // Monto final neto del corte
        'supplier_id', // Solo si tipo es 'proveedor'
        'metodo_pago', // 'efectivo', 'transferencia'

        'total_vendido', 
        'total_comision', 
        'total_envios', 
        'total_gastos',

        'pdf'
    ];

    protected $casts = [];

    /* =======================
       Relaciones
    ======================= */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

}
