<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SocialPost extends Model
{
    use HasFactory;

        /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'social_posts';

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
        'brand_id',
        'aprobada',
        'publicada',
        'texto',
        'escena',
        'tipo'
    ];

    //aprobada 0=No 1=Si
    //publicada 0=No 1=Si

    //tipo 0=post normal 1=post para publicar inmediatamente

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
        'brand_id' => 'integer',
        'aprobada' => 'integer',
        'publicada' => 'integer',
    ];

    public function marca()
    {
        return $this->belongsTo(SocialBrand::class, 'brand_id');
    }

    public function publications()
    {
        return $this->hasMany(SocialPublication::class, 'post_id');
    }

    public function imagenes()
    {
        return $this->hasMany(SocialImage::class, 'post_id');
    }
}
