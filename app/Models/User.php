<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'social_users';

    // Eloquent asume que cada tabla tiene una clave primaria con una columna llamada id.
    // Si éste no fuera el caso entonces hay que indicar cuál es nuestra clave primaria en la tabla:
    //protected $primaryKey = 'id';

    //public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'tipo', // 1=admin 2=cliente 3=bot 4=vendedor
        'status',
        'email', 
        'password',
        'telefono', 
        'last_login', 
        'bot_cliente_id', 
        'nombre',
        'eliminado',   
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'password',
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
        'status' => 'integer',
        'bot_cliente_id' => 'integer',
        'eliminado' => 'boolean',
    ];

    // Rest omitted for brevity

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    public function marcas()
    {
        return $this->hasMany(SocialBrand::class, 'user_id');
    }

    // --- Scopes ---
    public function scopeActivos($query)
    {
        return $query->where('status', 1)->where('eliminado', false);
    }

    public function scopeNoEliminados($query)
    {
        return $query->where('eliminado', false);
    }

    // --- Helpers ---
    public static function existeDuplicado($campo, $valor, $idExcluir = null)
    {
        $query = self::noEliminados()
            ->where($campo, $valor);

        if ($idExcluir) {
            $query->where('id', '<>', $idExcluir);
        }

        return $query->exists();
    }

    public static function buscarPorId($userId)
    {
        return self::where('id', $userId)
            ->noEliminados()
            ->first();
    }

}
