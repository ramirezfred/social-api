<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CfdiReceptor extends Model
{
    use HasFactory;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'cfdi_receptor';

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
        'Rfc',
        'Nombre',
        'DomicilioFiscalReceptor',
        'ResidenciaFiscal',
        'NumRegIdTrib',
        'RegimenFiscalReceptor',
        'UsoCFDI',
        'Email',
    ];

    //dalle bandera para controlar si esta activa la generacion de imagenes con dalle

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
    ];

    public function comprobante()
    {
        return $this->belongsTo(CfdiComprobante::class, 'comprobante_id');
    }

    public function mi_regimen_fiscal()
    {
        return $this->belongsTo(Cfdi40RegimenFiscal::class, 'RegimenFiscalReceptor');
    }

    public function mi_uso_cfdi()
    {
        return $this->belongsTo(Cfdi40UsoCfdi::class, 'UsoCFDI');
    }
}
