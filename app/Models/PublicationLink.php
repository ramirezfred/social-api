<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PublicationLink extends Model
{
    use HasFactory;

    protected $table = 'publication_links';

    protected $fillable = [
        'user_id',
        'name',
        'token',
        'token_plain',
        'status',
        'starts_at',
        'expires_at',
        'last_used_at',
        'views'
    ];

    protected $hidden = [];

    protected $casts = [
        'status' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'views' => 'integer',
        'user_id' => 'integer',
    ];

    /* =======================
       Relaciones
    ======================= */

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
