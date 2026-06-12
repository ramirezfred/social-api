<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    use HasFactory;

    protected $table = 'payrolls';

    protected $fillable = [
        'monto',
        'concepto', 
        'fecha',

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
