<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SocialNetwork extends Model
{
    use HasFactory;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'social_networks';

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
        'tipo',
        'login',
        'alias',
        'user',
        'password',
        'meta_user_id',
        'page_id',
        'page_name',
        'page_image',
        'access_token',
    ];

    //tipo 1=Facebook 2=Instagram 3=Twitter
    //login 0=No 1=Si

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'tipo' => 'integer',
        'login' => 'integer',
        'brand_id' => 'integer',
    ];

    public function marca()
    {
        return $this->belongsTo(SocialBrand::class, 'brand_id');
    }

    public function publicaciones()
    {
        return $this->hasMany(SocialPublication::class, 'network_id');
    }
}
