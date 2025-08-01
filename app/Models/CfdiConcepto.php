<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CfdiConcepto extends Model
{
    use HasFactory;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'cfdi_conceptos';

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
        'comprobante_id',
        'ClaveProdServ',
        'NoIdentificacion',
        'Cantidad',
        'ClaveUnidad',
        'Unidad',
        'Descripcion',
        'ValorUnitario',
        'Importe',
        'Descuento',
        'ObjetoImp',
        'ObjetoImpRet',
    ];

    //ObjetoImp bandera para controlar si es obj de impuesto Traslado
    //ObjetoImpRet bandera para controlar si es obj de impuesto Retencion

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
        'comprobante_id' => 'integer',
        'Cantidad' => 'double',
        'ValorUnitario' => 'double',
        'Importe' => 'double',
        'Descuento' => 'double',
        'ClaveProdServ' => 'integer',
        'ClaveUnidad' => 'integer',
    ];

    public function comprobante()
    {
        return $this->belongsTo(CfdiComprobante::class, 'comprobante_id');
    }

    public function mi_clave_prod_serv()
    {
        return $this->belongsTo(Cfdi40ProductoServicio::class, 'ClaveProdServ');
    }

    public function mi_clave_unidad()
    {
        return $this->belongsTo(Cfdi40ClaveUnidad::class, 'ClaveUnidad');
    }
}
