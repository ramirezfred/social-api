<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sistema extends Model
{
    use HasFactory;

        /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'sistema';

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
        'costo_marca',
        'key_1',
        'key_2',
        'key_3',
        'prompt_textos',
        'prompt_escenaA',
        'prompt_escenaB',
        'prompt_imagenesA',
        'prompt_imagenesB',
        'dalle',
        'costo_bot',
    ];

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
        'costo_marca' => 'double',
        'dalle' => 'integer',
        'costo_bot' => 'double',
    ];

}
