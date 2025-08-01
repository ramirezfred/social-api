<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CfdiEmpresa extends Model
{
    use HasFactory;

        /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'cfdi_empresas';

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
        'bot_cliente_id',
        'tipo_persona',
        'Rfc',
        'RazonSocial', //Nombre
        'RegimenFiscal',
        'FacAtrAdquirente',
        'CP',
        'cer',
        'key',
        'pass',

        'flag_descuento',
        'flag_objetoImp',
        'flag_retencion',
        'flag_producto',
        
        
    ];

    //flag_stock usar stock 1=si 0=no


    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['created_at','updated_at'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'bot_cliente_id' => 'integer',
        'tipo_persona' => 'integer',
        'flag_descuento' => 'integer',
        'flag_objetoImp' => 'integer',
        'flag_retencion' => 'integer',
        'flag_producto' => 'integer',
    ];

    public function persona()
    {
        return $this->belongsTo(BotCliente::class, 'bot_cliente_id');
    }

    public function clientes()
    {
        return $this->hasMany(CfdiCliente::class, 'empresa_id');
    }

    public function mi_regimen_fiscal()
    {
        return $this->belongsTo(Cfdi40RegimenFiscal::class, 'RegimenFiscal');
    }

    public function producto()
    {
        return $this->hasOne(CfdiProducto::class, 'empresa_id');
    }

}
