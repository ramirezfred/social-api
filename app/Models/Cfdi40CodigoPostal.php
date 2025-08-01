<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cfdi40CodigoPostal extends Model
{
    use HasFactory;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'cfdi_40_codigos_postales';

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
        'estado',
        'municipio',
        'localidad',
        'estimulo_frontera',
        'vigencia_desde',
        'vigencia_hasta',
        'huso_descripcion',
        'huso_verano_mes_inicio',
        'huso_verano_dia_inicio',
        'huso_verano_hora_inicio',
        'huso_verano_diferencia',
        'huso_invierno_mes_inicio',
        'huso_invierno_dia_inicio',
        'huso_invierno_hora_inicio',
        'huso_invierno_diferencia',
    ];

    //dalle bandera para controlar si esta activa la generacion de imagenes con dalle

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'estado',
        'municipio',
        'localidad',
        'estimulo_frontera',
        'vigencia_desde',
        'vigencia_hasta',
        'huso_descripcion',
        'huso_verano_mes_inicio',
        'huso_verano_dia_inicio',
        'huso_verano_hora_inicio',
        'huso_verano_diferencia',
        'huso_invierno_mes_inicio',
        'huso_invierno_dia_inicio',
        'huso_invierno_hora_inicio',
        'huso_invierno_diferencia',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
    ];
}
