<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cfdi40FormaPago extends Model
{
    use HasFactory;

        /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'cfdi_40_formas_pago';

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
        'texto',
        'es_bancarizado',
        'requiere_numero_operacion',
        'permite_banco_ordenante_rfc',
        'permite_cuenta_ordenante',
        'patron_cuenta_ordenante',
        'permite_banco_beneficiario_rfc',
        'permite_cuenta_beneficiario',
        'patron_cuenta_beneficiario',
        'permite_tipo_cadena_pago',
        'requiere_banco_ordenante_nombre_ext',
        'vigencia_desde',
        'vigencia_hasta',
    ];

    //dalle bandera para controlar si esta activa la generacion de imagenes con dalle

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'es_bancarizado',
        'requiere_numero_operacion',
        'permite_banco_ordenante_rfc',
        'permite_cuenta_ordenante',
        'patron_cuenta_ordenante',
        'permite_banco_beneficiario_rfc',
        'permite_cuenta_beneficiario',
        'patron_cuenta_beneficiario',
        'permite_tipo_cadena_pago',
        'requiere_banco_ordenante_nombre_ext',
        'vigencia_desde',
        'vigencia_hasta',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'es_bancarizado' => 'integer',
        'requiere_numero_operacion' => 'integer',
        'permite_banco_ordenante_rfc' => 'integer',
        'permite_cuenta_ordenante' => 'integer',
        'permite_banco_beneficiario_rfc' => 'integer',
        'permite_cuenta_beneficiario' => 'integer',
        'permite_tipo_cadena_pago' => 'integer',
        'requiere_banco_ordenante_nombre_ext' => 'integer',

    ];
}
