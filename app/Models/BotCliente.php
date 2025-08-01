<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BotCliente extends Model
{
    use HasFactory;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'bot_clientes';

    // Eloquent asume que cada tabla tiene una clave primaria con una columna llamada id.
    // Si éste no fuera el caso entonces hay que indicar cuál es nuestra clave primaria en la tabla:
    //protected $primaryKey = 'id';

    //public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'bot_id',
        'nombre',
        'telefono',
        'empresa',
        'status_empresa',

        'status',
        'bandera_reembolso',
        'conekta_id',
        'conekta_customer_id',
        'paypal_id',
        'paypal_subscription_id',
        'pay_next_day',
        'pay_next_month',
        'pay_next_year',
        'pay_amount',
        'tipo_pago',
        'pago',
        'test_day',
        'test_month',
        'test_year',
        'count_querys',
        'flag_msg_querys',

        'flow_id',
        'flow_stage_item',

        'flag_colores',
        'flag_stock',
        'costo_envio',

        'color_a',
        'color_b',
        'color_c',
        'header',
        'footer',
        'logo',
        'logo_allow_origin',

        'hab_citas',
        'hab_redes',
        'hab_pedidos',
        'hab_cotizaciones',
        'hab_facturas',

        'flag_bienvenida',

        'max_facturas',
        'count_facturas',

        'last_pay_date',

        'count_alertas',
        'fecha_alerta',
  
    ];

    //flag_colores usar tallas y colores en productos 1=si 0=no
    //flag_stock usar stock 1=si 0=no

    //count_alertas para notificar a los clientes nuevos cada 8 horas


    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'bot_id' => 'integer',

        'status' => 'integer',
        'status_empresa' => 'integer',
        'pay_next_day' => 'integer',
        'pay_next_month' => 'integer',
        'pay_next_year' => 'integer',
        'pay_amount' => 'double',
        'tipo_pago' => 'integer',
        'pago' => 'integer',
        'test_day' => 'integer',
        'test_month' => 'integer',
        'test_year' => 'integer',
        'count_querys' => 'integer',
        'flag_msg_querys' => 'integer',

        'flow_id' => 'integer',
        'flow_stage_item' => 'integer',

        'flag_colores' => 'integer',
        'flag_stock' => 'integer',
        'costo_envio' => 'double',

        'hab_citas' => 'integer',
        'hab_redes' => 'integer',
        'hab_pedidos' => 'integer',
        'hab_cotizaciones' => 'integer',
        'hab_facturas' => 'integer',

        'flag_bienvenida' => 'integer',

        'max_facturas' => 'integer',
        'count_facturas' => 'integer',

        'count_alertas' => 'integer',

    ];

    public function bot()
    {
        return $this->belongsTo(User::class, 'bot_id');
    }

    public function mensajes()
    {
        return $this->hasMany(BotChat::class, 'cliente_id');
    }

    public function cfdi_empresa()
    {
        return $this->hasOne(CfdiEmpresa::class, 'bot_cliente_id');
    }
}
