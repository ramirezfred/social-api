<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Publication extends Model
{
    use HasFactory;

    protected $table = 'publications';

    protected $fillable = [
        'supplier_id', 
        'texto',
        'estado', // borrador, publicada
        'publication_date'
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

    

}
