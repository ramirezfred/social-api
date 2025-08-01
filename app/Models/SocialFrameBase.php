<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SocialFrameBase extends Model
{
    use HasFactory;

        /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'social_frames_base';

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
        'nombre',
        'url',
        'url_allow_origin',
        'base64',
        'img1_tipo',
        'img1_x',
        'img1_y',
        'img1_radius',
        'img1_width',
        'img1_height',
        'img2_tipo',
        'img2_x',
        'img2_y',
        'img2_radius',
        'img2_width',
        'img2_height',
        'text1_px',
        'text1_font',
        'text1_x',
        'text1_y',
        'text1_aling',
        'text1_color',
        'text2_px',
        'text2_font',
        'text2_x',
        'text2_y',
        'text2_aling',
        'text2_color',
        'text3_px',
        'text3_font',
        'text3_x',
        'text3_y',
        'text3_aling',
        'text3_color',
    ];

    //imgX_tipo 1=circular 2=cuadrada

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
        'img1_tipo' => 'integer',
        'img1_x' => 'integer',
        'img1_y' => 'integer',
        'img1_radius' => 'integer',
        'img1_width' => 'integer',
        'img1_height' => 'integer',
        'img2_tipo' => 'integer',
        'img2_x' => 'integer',
        'img2_y' => 'integer',
        'img2_radius' => 'integer',
        'img2_width' => 'integer',
        'img2_height' => 'integer',
        'text1_px' => 'integer',
        'text1_x' => 'integer',
        'text1_y' => 'integer',
        'text2_px' => 'integer',
        'text2_x' => 'integer',
        'text2_y' => 'integer',
        'text3_px' => 'integer',
        'text3_x' => 'integer',
        'text3_y' => 'integer',
    ];

    public function brands(){
        return $this->belongsToMany(SocialBrand::class,'social_brand_frames_base','frame_base_id','brand_id')
            ->withPivot('id','frame_id')/*->withTimestamps()*/; 
    }
}
