<?php

namespace App\Http\Traits;

use Illuminate\Http\Request;

use App\Http\Requests;

use App\Models\User;
use App\Models\SocialBrand;
use App\Models\SocialNetwork;
use App\Models\SocialPost;
use App\Models\Sistema;
use App\Models\Bot;
use App\Models\BotConfig;

use DB;

use Exception;

trait ApiTextCortexTrait
{

    public static $base_url_textcortex = "https://api.textcortex.com";
    public static $path_textcortex = "/v1";
    //public static $token_textcortex = "gAAAAABkizh8noXAN_ChwAH0AZLVD6D0Ma3EDdKAoM1oo-cDYNVYVH1NBO_DobAYtxePdF5VwhaOEElyvuWnTgtrpjgI1UU9H70xPVvwinJnc6TE2itRDKAHMZLBRTdH1oEoR64VTLWr";
    //public static $token_textcortex = "gAAAAABkkNxh0amtfm5SGRPPU_oT-JqAqB4G6GvXYrxmkRF8yf9EOnDQljlKpBXsBuBbTleGU0iq1wVCri84XsgVpPJPHMkPp9sQJLbyw5Z2InqF6wQ4H24IqTveupIaCQ_O2Avh46kO";
    public static $token_textcortex = "gAAAAABklNH30O8a5DAGWMMZZvsP7a_MswGnCV22eULj0lhqx1kF0vInxOGJtrp3GpLrrmlQE-C6bz_VyWbOO9o3lFQfZJP9M49LuIrIpko-g_x4t2DRgUzYSuiQ6_ebQ0fBzIMebxaE";


    public static function _completions($bot_id, $mensaje)
    {
        set_time_limit(500);
        
        $token = static::$token_textcortex;

        $bot = Bot::find($bot_id);
        if (!$bot)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'No existe el bot con id '.$bot_id,
                'textcortex'=>null
            ];
        }

        //$prompt = 'Actúa como un Chat Bot de WhatsApp y responde al siguiente mensaje de un cliente: {{mensaje}}';

        $prompt = 'Actúa como un asistente de Internow para responder a las preguntas de los clientes. Esta es la pregunta del cliente : {{mensaje}}. No uses más de 4000 caracteres para tu respuesta.';
        
        $prompt = str_replace("{{mensaje}}", $mensaje, $prompt);

        //Armando la peticion cURL        
        $fields = array(
            "max_tokens" => 2048,
            "model" => "sophos-1", 
            "n" => 1,
            "source_lang" => "es",
            "target_lang" => "es",
            "temperature" => 0.65,
            "text" => $prompt,
        ); 

            
        $fields = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_textcortex.static::$path_textcortex."/texts/completions");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer ".$token,
            "Content-Type: application/json"
        ));

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        $err = curl_error($ch);

        curl_close($ch);

        if ($err) {
            //echo "cURL Error #:" . $err;
            return [
                'status'=>409,
                'error'=>'Error al conectar con TextCortext',
                'textcortex'=>$err
            ];

        } else {

            $textcortex_obj = json_decode($response);

            if (
                property_exists($textcortex_obj, 'status') && 
                $textcortex_obj->status == 'success'
            ) {

                $text =  $textcortex_obj->data->outputs[0]->text;

                return [
                    'status'=>200,
                    'prompt'=>$prompt,
                    'text'=>$text,
                    'textcortex'=>$textcortex_obj
                ]; 

            }else{
                return [
                    'status'=>409,
                    //'error'=>$textcortex_obj->error->message,
                    'textcortex'=>$textcortex_obj
                ];
            }

        }  

    }

    public static function _palabraClave($bot_id, $mensajes)
    {
        set_time_limit(500);
        
        $token = static::$token_textcortex;

        $bot = Bot::find($bot_id);
        if (!$bot)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'No existe el bot con id '.$bot_id,
                'textcortex'=>null
            ];
        }

        $bot_config = BotConfig::
            select('id','palabra_clave')
            ->where('bot_id', $bot_id)
            ->get();
        if (count($bot_config)==0)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'El Bot no tiene configuración.',
                'textcortex'=>null
            ];
        }

        //Buscar la config del bot para sacar las palabras clave
        // $array_palabras_clave = [
        //     'INFORMACION',
        //     'PEDIDO',
        //     'OTRO'
        // ];
        $palabras_clave = '';
        for ($i=0; $i < count($bot_config); $i++) { 
            if($i == 0){
                $palabras_clave = $bot_config[$i]->palabra_clave;
            }else if($i > 0 && $i < count($bot_config)-1){
                $palabras_clave = $palabras_clave.', '.$bot_config[$i]->palabra_clave;
            }else if($i > 0 && $i == count($bot_config)-1){
                $palabras_clave = $palabras_clave.' o '.$bot_config[$i]->palabra_clave;
            }
        }

        $text_mensajes = '';
        for ($i=0; $i < count($mensajes); $i++) { 
            if($i == 0){
                $text_mensajes = $mensajes[$i];
            }else{
                $text_mensajes = $text_mensajes.', '.$mensajes[$i];
            }
        }

        $prompt = 'Un cliente nos envió este mensaje por chat: {{text_mensajes}}. Relaciona ese mensaje con una de las siguientes palabras: {{palabras_clave}}. Solo retorna una de las palabras que te estoy dando.';
        
        $prompt = str_replace("{{text_mensajes}}", $text_mensajes, $prompt);
        $prompt = str_replace("{{palabras_clave}}", $palabras_clave, $prompt);

        //Armando la peticion cURL        
        $fields = array(
            "max_tokens" => 2048,
            "model" => "sophos-1", 
            "n" => 1,
            "source_lang" => "es",
            "target_lang" => "es",
            "temperature" => 0.65,
            "text" => $prompt,
        ); 

            
        $fields = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_textcortex.static::$path_textcortex."/texts/completions");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer ".$token,
            "Content-Type: application/json"
        ));

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        $err = curl_error($ch);

        curl_close($ch);

        if ($err) {
            //echo "cURL Error #:" . $err;
            return [
                'status'=>409,
                'error'=>'Error al conectar con TextCortext',
                'textcortex'=>$err
            ];

        } else {

            $textcortex_obj = json_decode($response);

            if (
                property_exists($textcortex_obj, 'status') && 
                $textcortex_obj->status == 'success'
            ) {

                $text =  $textcortex_obj->data->outputs[0]->text;

                $text = str_replace("\n\n", "", $text);
                $text = str_replace("\n", "", $text);
                $text = strtoupper($text);

                return [
                    'status'=>200,
                    'prompt'=>$prompt,
                    'text'=>$text,
                    'textcortex'=>$textcortex_obj
                ]; 

            }else{
                return [
                    'status'=>409,
                    //'error'=>$textcortex_obj->error->message,
                    'textcortex'=>$textcortex_obj
                ];
            }

        }  

    }

    public static function _respuesta($bot_id, $mensajes, $palabra_clave)
    {
        set_time_limit(500);
        
        $token = static::$token_textcortex;

        $bot = Bot::find($bot_id);
        if (!$bot)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'No existe el bot con id '.$bot_id,
                'textcortex'=>null
            ];
        }

        $bot_config = BotConfig::
            select('id','palabra_clave','prompt')
            ->where('palabra_clave', $palabra_clave)
            ->get();
        if (count($bot_config)==0)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'Configuración no encontrada.',
                'textcortex'=>null
            ];
        }

        $text_mensajes = '';
        for ($i=0; $i < count($mensajes); $i++) { 
            if($i == 0){
                $text_mensajes = $mensajes[$i];
            }else{
                $text_mensajes = $text_mensajes.', '.$mensajes[$i];
            }
        }

        //$prompt = 'Actúa como un Chat Bot de WhatsApp y responde al siguiente mensaje de un cliente: {{mensaje}}';

        $prompt = $bot_config[0]->prompt;
        
        $prompt = str_replace("{{mensaje}}", $text_mensajes, $prompt);

        //Armando la peticion cURL        
        $fields = array(
            "max_tokens" => 2048,
            "model" => "sophos-1", 
            "n" => 1,
            "source_lang" => "es",
            "target_lang" => "es",
            "temperature" => 0.65,
            "text" => $prompt,
        ); 

            
        $fields = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_textcortex.static::$path_textcortex."/texts/completions");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer ".$token,
            "Content-Type: application/json"
        ));

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        $err = curl_error($ch);

        curl_close($ch);

        if ($err) {
            //echo "cURL Error #:" . $err;
            return [
                'status'=>409,
                'error'=>'Error al conectar con TextCortext',
                'textcortex'=>$err
            ];

        } else {

            $textcortex_obj = json_decode($response);

            if (
                property_exists($textcortex_obj, 'status') && 
                $textcortex_obj->status == 'success'
            ) {

                $text =  $textcortex_obj->data->outputs[0]->text;

                return [
                    'status'=>200,
                    'prompt'=>$prompt,
                    'text'=>$text,
                    'textcortex'=>$textcortex_obj
                ]; 

            }else{
                return [
                    'status'=>409,
                    //'error'=>$textcortex_obj->error->message,
                    'textcortex'=>$textcortex_obj
                ];
            }

        }  

    }

    

}
