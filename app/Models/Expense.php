<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;

    protected $table = 'expenses';

    protected $fillable = [
        'monto',
        'concepto', 
        'fecha',

        'corte_b_estado', // 'pendiente', 'cerrado'
        'cash_close_b_id', 

        'eliminado', // 0=no eliminado 1=eliminado
    ];

    protected $casts = [
        'eliminado' => 'boolean',
    ];

    /* =======================
       Scopes
    ======================= */
    public function scopeNoEliminados($query)
    {
        return $query->where('eliminado', false);
    }
}
