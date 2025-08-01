<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CfdiComprobante extends Model
{
    use HasFactory;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'cfdi_comprobante';

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
        'cliente_id',
        'status', // 0 creando 1 creada 2 cancelada 3 creando desde panel
        'flag_cancelada',
        'Serie',
        'Folio',
        'Fecha',
        'Sello',
        'FormaPago',
        'NoCertificado',
        'Certificado',
        'CondicionesDePago',
        'Subtotal',
        'Descuento',
        'Moneda',
        'TipoCambio',
        'Total',
        'TipoDeComprobante',
        'Exportacion',
        'MetodoPago',
        'LugarExpedicion',
        'Confirmacion',
        'estado',
        'function',
        'TasaIva', //para las retenciones
        'TasaIsr', //para las retenciones
        'Tipo', //1 factura neta 2 factura mas iba
    ];

    // estado = 1 pregunta si cliente nuevo o existente
    // estado = 2 pregunta de rfc de cliente para buscarlo
    // estado = 3 preguntando por los datos de la factura

    // TasaIva para las retenciones
    // TasaIsr para las retenciones
    // Tipo 1 factura neta 2 factura mas iba

    //dalle bandera para controlar si esta activa la generacion de imagenes con dalle

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
        'cliente_id' => 'integer',
        'status' => 'integer',
        'flag_cancelada' => 'integer',
        'Subtotal' => 'double',
        'Descuento' => 'double',
        'Total' => 'double',
        'estado' => 'integer',
        'TasaIva' => 'double',
        'TasaIsr' => 'double',
        'Tipo' => 'integer',
    ];

    public function informacion_global()
    {
        return $this->hasOne(CfdiInformacionGlobal::class, 'comprobante_id');
    }

    public function receptor()
    {
        return $this->hasOne(CfdiReceptor::class, 'comprobante_id');
    }

    public function conceptos()
    {
        return $this->hasMany(CfdiConcepto::class, 'comprobante_id');
    }

    public function impuesto()
    {
        return $this->hasOne(CfdiImpuesto::class, 'comprobante_id');
    }

    public function timbre_fiscal_digital()
    {
        return $this->hasOne(CfdiTimbreFiscalDigital::class, 'comprobante_id');
    }

    public function archivo()
    {
        return $this->hasOne(CfdiArchivo::class, 'comprobante_id');
    }

    public function mi_forma_pago()
    {
        return $this->belongsTo(Cfdi40FormaPago::class, 'FormaPago');
    }

    public function mi_metodo_pago()
    {
        return $this->belongsTo(Cfdi40MetodoPago::class, 'MetodoPago');
    }

    public function cliente_bot()
    {
        return $this->belongsTo(BotCliente::class, 'cliente_id');
    }
}
