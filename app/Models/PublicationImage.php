<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PublicationImage extends Model
{
    use HasFactory;

    protected $table = 'publication_image';

    protected $fillable = [
        'publication_id', 
        'image_path',
    ];

    protected $hidden = ['created_at','updated_at'];

    protected $appends  = ['url'];  // agrega el atributo al JSON automáticamente

    public function getUrlAttribute(): string
    {
        // return Storage::url($this->image_path);
        return asset($this->image_path);
    }

    /* =======================
       Relaciones
    ======================= */
    public function publication()
    {
        return $this->belongsTo(Publication::class, 'publication_id');
    }
}
