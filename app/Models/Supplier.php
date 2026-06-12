<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $table = 'suppliers';

    protected $fillable = [
        'user_id',
        'razon_social',
        'email',
        'telefono',
        'direccion',
        'contacto',
        'status',
        'categoria',
        'eliminado',
    ];

    protected $casts = [
        'status' => 'boolean',
        'eliminado' => 'boolean',
        'user_id' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // --- Scopes ---
    public function scopeActivos($query)
    {
        return $query->where('status', true)->where('eliminado', false);
    }

    public function scopeNoEliminados($query)
    {
        return $query->where('eliminado', false);
    }

    // --- Helpers ---
    public static function existeDuplicado($campo, $valor, /*$user_id,*/ $idExcluir = null)
    {
        $query = self::noEliminados()
            ->where($campo, $valor)
            /*->where('user_id', $user_id)*/;

        if ($idExcluir) {
            $query->where('id', '<>', $idExcluir);
        }

        return $query->exists();
    }
}
