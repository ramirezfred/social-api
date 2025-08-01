<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cotizacion extends Model
{
    use HasFactory;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'cotizaciones';

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
        'status',
        'subtotal',
        'iva',
        'envio',
        'total',
        'cliente',
        'telefono',
        'email',
        'orden',
        'pdf',
        'imagen',
        'tipo', //1 factura neta 2 factura mas iba
    ];

    //status 0=en curso (creando) 1=creado 2=finalizado 3=cancelado

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
        'subtotal' => 'double',
        'iva' => 'double',
        'envio' => 'double',
        'total' => 'double',
        'orden' => 'integer',
        'tipo' => 'integer',
    ];

    public function gastos()
    {
        return $this->hasMany(CotizacionGasto::class, 'cotizacion_id');
    }
}
