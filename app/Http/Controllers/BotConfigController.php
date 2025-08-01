<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\User;
use App\Models\Sistema;
use App\Models\Bot;
use App\Models\BotCliente;
use App\Models\BotChat;
use App\Models\BotConfig;

//use Hash;
use DB;
//use Validator;
use Exception;


use Carbon\Carbon;

use App\Http\Traits\ApiWhatsAppTrait;
use App\Http\Traits\ApiTextCortexTrait;

date_default_timezone_set('America/Mexico_City');

class BotConfigController extends Controller
{
    public function store(Request $request)
    {
        // Primero comprobaremos si estamos recibiendo todos los campos.
        $validator = Validator::make($request->all(),[
            'bot_id'=>'required|integer',
            'palabra_clave'=>'required|string',
            'prompt'=>'required|string',

        ]);
        if ($validator->fails()) { 
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 422 Unprocessable Entity – [Entidad improcesable] Utilizada para errores de validación.
            return response()->json(['error'=>'Error de validación',
                'detalle'=>$validator->errors(),
            ],422);
        }

        $bot = Bot::find($request->input('bot_id'));
        if (!$bot)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe el Bot con id '.$request->input('bot_id')], 404);
        }

        if (strlen($request->input('prompt')) > 4800)
        {
            // Devolvemos error codigo http 409
            return response()->json([
                'error'=>'El prompt no puede contener más de 4800 caracteres',
                'count_prompt'=>strlen($request->input('prompt'))
            ], 409);
        }

        //verificar la precencia de {{mensaje}}
        $mensaje = strpos($request->input('prompt'), '{{mensaje}}');
        if ($mensaje === false) {
            return response()->json(['error'=>'El prompt debe contener la subcadena {{mensaje}}'], 409);
        }

        $palabra_clave = strtoupper($request->input('palabra_clave'));

        $config_aux = BotConfig::
            select('id','palabra_clave')
            ->where('palabra_clave', $palabra_clave)
            ->where('bot_id', $request->input('bot_id'))
            ->get();
        if (count($config_aux)>0) {
            return response()->json(['error'=>'Ya existe otra entrada con esa palabra clave.'], 409);
        }

        if($nuevoObj=BotConfig::create([
            'bot_id'=>$request->input('bot_id'),
            'palabra_clave'=>$palabra_clave,
            'prompt'=>$request->input('prompt'),
            //'acciones'=>$request->input('acciones'),
            'tipo'=>1,
            'status'=>1,
            
        ])){

            return response()->json([
                'message'=>'Prompt agregado con éxito.',
                'config'=>$nuevoObj,
            ], 200);

        }else{
            return response()->json(['error'=>'Error al crear el prompt.'], 500);
        }

    }

    public function getConfig($bot_id)
    {
        $objs = BotConfig::
            select('id','bot_id','palabra_clave',
                'prompt','acciones','tipo','status')
            ->where('bot_id',$bot_id)->get();

        return response()->json(['config'=>$objs], 200);
    }

    public function update(Request $request, $config_id)
    {
        // Comprobamos si lo que nos están pasando existe o no.

        $config = BotConfig::find($config_id);

        if (!$config)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe el Prompt con id '.$config_id], 404);
        }

        // Listado de campos recibidos teóricamente.
        $palabra_clave=$request->input('palabra_clave'); 
        $prompt=$request->input('prompt');

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Actualización parcial de campos.
        if ($palabra_clave != null && $palabra_clave!='')
        {
            $palabra_clave = strtoupper($request->input('palabra_clave'));

            $aux = BotConfig::where('palabra_clave', $palabra_clave)
                ->where('id', '<>', $config->id)
                ->where('bot_id', $config->bot_id)
                ->get();

            if(count($aux)!=0){
               // Devolvemos un código 409 Conflict. 
                return response()->json(['error'=>'Ya existe otra entrada con esa palabra clave.'], 409);
            }

            $config->palabra_clave = $palabra_clave;
            $bandera=true;
        }

        if ($prompt != null && $prompt!='')
        {
            //verificar la precencia de {{mensaje}}
            $mensaje = strpos($request->input('prompt'), '{{mensaje}}');
            if ($mensaje === false) {
                return response()->json(['error'=>'El prompt debe contener la subcadena {{mensaje}}'], 409);
            }

            $config->prompt = $prompt;
            $bandera=true;
        }
        
        if ($bandera)
        {
            // Almacenamos en la base de datos el registro.
            if ($config->save()) {
                return response()->json(['message'=>'Prompt editado con éxito.',
                    'config'=>$config], 200);
            }else{
                return response()->json(['error'=>'Error al actualizar el prompt.'], 500);
            }
            
        }
        else
        {
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 304 Not Modified – [No Modificada] Usado cuando el cacheo de encabezados HTTP está activo
            // Este código 304 no devuelve ningún body, así que si quisiéramos que se mostrara el mensaje usaríamos un código 200 en su lugar.
            return response()->json(['error'=>'No se ha modificado ningún dato al prompt.'],409);
        }
    }

    public function destroy($config_id)
    {
        $config = BotConfig::find($config_id);

        if (!$config)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Prompt no encontrado'], 404);
        }

        $count = BotConfig::
            where('bot_id',$config->bot_id)
            ->count();

        if($count == 1){
            return response()->json(['error'=>'El Bot debe tener al menos un Prompt.'], 404);
        }

        $config->delete();

        return response()->json([
            'message'=>'Prompt eliminado correctamente.',
        ], 200);
    }
}
