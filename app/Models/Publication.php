<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Publication extends Model
{
    use HasFactory;

    protected $table = 'publications';

    protected $fillable = [
        'user_id',
        'supplier_id', 
        'texto',
        'estado', // borrador, publicada
        'publication_date'
    ];

    protected $casts = [
        'user_id' => 'integer',
    ];

    /* =======================
       Relaciones
    ======================= */
    public function images()
    {
        return $this->hasMany(PublicationImage::class, 'publication_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    

}
