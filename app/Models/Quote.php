<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quote extends Model
{
    use HasFactory;

    protected $table = 'quotes';

    protected $fillable = [
        'folio', 
        'cliente',
        'email', 
        'telefono', 

        'estado', // en curso, finalizada, cancelada

        'moneda', //MXN y USD
        'envio', 
        'subtotal', 
        'descuento',
        'impuesto', 
        'total',

        'pago_estado', // pendiente | adelantado | pagado

        'tipo_entrega', // plaza, envio

        'api_tipo_pago',
        'conekta_id',
        'conekta_customer_id',
        'stripe_id',
        'flag_reembolso', // 0= no reembolso 1= reembolso

        'notas',
        'pdf',
        'eliminado', // 0=no eliminada 1=eliminada
    ];

    // pago_estado Significado
    // pendiente Sin ningún pago registrado
    // adelantado Tiene adelanto, falta liquidar
    // pagado Monto total cubierto

    protected $casts = [
        'flag_reembolso' => 'boolean',
        'eliminado' => 'boolean',
    ];

    protected $appends = [
        'total_pagado',
        'saldo_restante', //restante por pagar
    ];

    /* =======================
       Relaciones
    ======================= */
    public function detalles()
    {
        return $this->hasMany(QuoteDetail::class, 'quote_id');
    }

    public function pagos()
    {
        return $this->hasMany(QuotePago::class, 'quote_id');
    }

    /* =======================
       Scopes
    ======================= */
    public function scopeNoEliminados($query)
    {
        return $query->where('eliminado', false);
    }

    // Accessors dinámicos (no se guardan en BD)
    public function getTotalPagadoAttribute()
    {
        // Si la relación ya está cargada, usa la colección en memoria
        if ($this->relationLoaded('pagos')) {
            return $this->pagos->sum('monto');
        }
        return $this->pagos()->sum('monto');
    }

    public function getSaldoRestanteAttribute()
    {
        return $this->total - $this->total_pagado;
    }

}
