<?php

namespace App\Http\Traits;

use Illuminate\Http\Request;

use App\Http\Requests;

use Illuminate\Support\Facades\Cache;

use Illuminate\Support\Facades\Crypt;


use App\Models\User;
use App\Models\Sistema;
use App\Models\Bot;
use App\Models\BotConfig;
use App\Models\BotCliente;
use App\Models\BotChat;

use App\Models\BotFlow;
use App\Models\BotFlowStage;
use App\Models\BotStageValidation;
use App\Models\BotCita;

//use Hash;
use DB;
//use Validator;
use Exception;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

use Carbon\Carbon;

trait BotFunctionsTrait
{

    public static function _botFunctionVerCitas($bot_id, $mensajes, $palabra_clave, $cliente)
    {
        
        set_time_limit(500);

        $bot_cliente = BotCliente::find($bot_id);
        if (!$bot_cliente)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'No existe el cliente con id '.$cliente->id
            ];
        }

        $user_token=User::find(10);
        $token = JWTAuth::fromUser($user_token);

        $claveAdicional = config('app.lada_d');
        $cadenaEncriptada = Crypt::encrypt($cliente->id, $claveAdicional);

        /*$cadenaMsg = 'Ingresa en el siguiente enlace para ver tus citas:

https://apisocial.internow.com.mx/api/bots_cita/mis_citas/'.$cadenaEncriptada.'?token='.$token;*/

        $cadenaMsg = 'Ingresa en el siguiente enlace para ver tus citas:

https://social.internow.com.mx/#/citas-bot/'.$cadenaEncriptada.'/'.$token;

        $message = $cadenaMsg;

        return [
            'status'=>200,
            'text'=>$message,
        ]; 

    }

}
