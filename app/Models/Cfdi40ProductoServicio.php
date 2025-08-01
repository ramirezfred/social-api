<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cfdi40ProductoServicio extends Model
{
    use HasFactory;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'cfdi_40_productos_servicios';

    // Eloquent asume que cada tabla tiene una clave primaria con una columna llamada id.
    // Si éste no fuera el caso entonces hay que indicar cuál es nuestra clave primaria en la tabla:
    protected $primaryKey = 'id_aux';

    //public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'texto',
        'iva_trasladado',
        'ieps_trasladado',
        'complemento',
        'vigencia_desde',
        'vigencia_hasta',
        'estimulo_frontera',
        'similares',
    ];

    //dalle bandera para controlar si esta activa la generacion de imagenes con dalle

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'iva_trasladado',
        'ieps_trasladado',
        'complemento',
        'vigencia_desde',
        'vigencia_hasta',
        'estimulo_frontera',
        'similares',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'iva_trasladado' => 'integer',
        'ieps_trasladado' => 'integer',
        'estimulo_frontera' => 'integer',
    ];
}
