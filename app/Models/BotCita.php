<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BotCita extends Model
{
    use HasFactory;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'bot_citas';

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
        'nombre',
        'telefono',
        'email',
        'motivo',
        'fecha',
        'day',
        'month',
        'year',
        'hora',
        'hour',
        'minutes',
        'status',
        'status_sms'
        
    ];


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
        'day' => 'integer',
        'month' => 'integer',
        'year' => 'integer',
        'hour' => 'integer',
        'minutes' => 'integer',
        'status' => 'integer',
        'status_sms' => 'integer',

    ];

    public function bot()
    {
        return $this->belongsTo(Bot::class, 'bot_id');
    }

    public function cliente()
    {
        return $this->belongsTo(BotCliente::class, 'cliente_id');
    }

}
