<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SocialBrand extends Model
{
    use HasFactory;

        /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'social_brands';

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
        'user_id',
        'nombre',
        'logo',
        'logo_allow_origin',
        'servicios',
        'horario',
        'status',
        'bandera_reembolso',
        'conekta_id',
        'conekta_customer_id',
        'paypal_id',
        'paypal_subscription_id',
        'pay_next_day',
        'pay_next_month',
        'pay_next_year',
        'pay_amount',
        'tipo_pago',
        'parametros',
        'parametros_imagen',
        'bandera_flujo',
        'prompt_imagen',
        'pago',
        'pexels_status',
        'pexels_frase',
        'comment_status',
        'comment_auto',
        'comment_prompt',
        'comment_footer',
        'prompt_textos',
        'color_a',
        'color_b',
        'color_c',
        'test_day',
        'test_month',
        'test_year',
        'font',
        
    ];

    //parametros para la escena
    //parametros_imagen caracteristicas adicionales para las imagenes

    //tipo_pago 1=Tarjeta 2=PayPal

    /*bandera_flujo=0 se genera una escena en base a los parametros
     y luego se genera la imagen en base a la escena*/
    /*bandera_flujo=1 se genera la imagen en base al prompt por defecto prompt_imagen*/

    //pago 0=Sin Pago 1=Pagada


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
        'status' => 'integer',
        'pay_next_day' => 'integer',
        'pay_next_month' => 'integer',
        'pay_next_year' => 'integer',
        'pay_amount' => 'double',
        'bandera_flujo' => 'integer',
        'tipo_pago' => 'integer',
        'pago' => 'integer',
        'pexels_status' => 'integer',
        'comment_status' => 'integer',
        'test_day' => 'integer',
        'test_month' => 'integer',
        'test_year' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function redes()
    {
        return $this->hasMany(SocialNetwork::class, 'brand_id');
    }

    public function posts()
    {
        return $this->hasMany(SocialPost::class, 'brand_id');
    }

    public function frames_base(){
        return $this->belongsToMany(SocialFrameBase::class,'social_brand_frames_base','brand_id','frame_base_id')
            ->withPivot('id','frame_id')->withTimestamps(); 
    }
}
