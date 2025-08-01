<?php

namespace App\Http\Traits;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Crypt;

use App\Http\Requests;

use App\Models\User;
use App\Models\Bot;

use DB;

use Exception;

date_default_timezone_set('America/Mexico_City');

trait ApiWhatsAppTrait
{
    public static $base_url_whatsapp = "https://graph.facebook.com";
    public static $path_whatsapp = "/v17.0";

    public static function _messageText($bot_id, $to, $body)
    {
        set_time_limit(500);

        $bot = Bot::find($bot_id);
        if (!$bot)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'No existe el bot con id '.$bot_id,
                'whatsapp'=>null
            ];
        }

        if ($bot->status != 1)
        {
            // Devolvemos error codigo http 409
            return [
                'status'=>409,
                'error'=>'Bot inactivo',
                'whatsapp'=>null
            ];
        }

        $rest = substr($to, 0, 3);
        if($rest == 521){
            $to = str_replace("521", "52", $to);
        }

        //Armando la peticion cURL        
        $fields = array(
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'type'=> 'text',
            'to' => '+'.$to,
            //'to' => $to,
            'text' => array(
                'preview_url' => false,
                'body' => $body
            )
        ); 

            
        $fields = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $claveAdicional = config('app.lada_b');
        $cadenaDesencriptada = Crypt::decrypt($bot->access_token, $claveAdicional);
        $cadenaDesencriptada = substr($cadenaDesencriptada, 0, -5);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_whatsapp.static::$path_whatsapp."/".$bot->number_id."/messages");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer ".$cadenaDesencriptada,
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
                'error'=>'Error al conectar con WhatsApp',
                'whatsapp'=>$err
            ];

        } else {

            $whatsapp_obj = json_decode($response);

            return [
                'status'=>200,
                'whatsapp'=>$whatsapp_obj
            ];

            if (property_exists($whatsapp_obj, 'messages')) {

                return [
                    'status'=>200,
                    'whatsapp'=>$whatsapp_obj
                ]; 

            }else{
                return [
                    'status'=>409,
                    'error'=>'Error al enviar mensaje',
                    'whatsapp'=>$whatsapp_obj
                ];
            }

        }  

    }

    public static function _messageInteractive($bot_id, $to, $body)
    {
        set_time_limit(500);

        $bot = Bot::find($bot_id);
        if (!$bot)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'No existe el bot con id '.$bot_id,
                'whatsapp'=>null
            ];
        }

        if ($bot->status != 1)
        {
            // Devolvemos error codigo http 409
            return [
                'status'=>409,
                'error'=>'Bot inactivo',
                'whatsapp'=>null
            ];
        }

        $rest = substr($to, 0, 3);
        if($rest == 521){
            $to = str_replace("521", "52", $to);
        }

        //Armando la peticion cURL        
        $fields = array(
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => '+'.$to,
            'type'=> 'interactive',
            //'to' => $to,
            'interactive' => $body
        ); 

            
        $fields = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $claveAdicional = config('app.lada_b');
        $cadenaDesencriptada = Crypt::decrypt($bot->access_token, $claveAdicional);
        $cadenaDesencriptada = substr($cadenaDesencriptada, 0, -5);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_whatsapp.static::$path_whatsapp."/".$bot->number_id."/messages");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer ".$cadenaDesencriptada,
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
                'error'=>'Error al conectar con WhatsApp',
                'whatsapp'=>$err
            ];

        } else {

            $whatsapp_obj = json_decode($response);

            return [
                'status'=>200,
                'whatsapp'=>$whatsapp_obj
            ];

            if (property_exists($whatsapp_obj, 'messages')) {

                return [
                    'status'=>200,
                    'whatsapp'=>$whatsapp_obj
                ]; 

            }else{
                return [
                    'status'=>409,
                    'error'=>'Error al enviar mensaje',
                    'whatsapp'=>$whatsapp_obj
                ];
            }

        }  

    }

    public static function _messageImage($bot_id, $to, $body)
    {
        set_time_limit(500);

        $bot = Bot::find($bot_id);
        if (!$bot)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'No existe el bot con id '.$bot_id,
                'whatsapp'=>null
            ];
        }

        if ($bot->status != 1)
        {
            // Devolvemos error codigo http 409
            return [
                'status'=>409,
                'error'=>'Bot inactivo',
                'whatsapp'=>null
            ];
        }

        $rest = substr($to, 0, 3);
        if($rest == 521){
            $to = str_replace("521", "52", $to);
        }

        //Armando la peticion cURL        
        $fields = array(
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'type'=> 'image',
            'to' => '+'.$to,
            //'to' => $to,
            'image' => array(
                'link' => $body
            )
        ); 

            
        $fields = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $claveAdicional = config('app.lada_b');
        $cadenaDesencriptada = Crypt::decrypt($bot->access_token, $claveAdicional);
        $cadenaDesencriptada = substr($cadenaDesencriptada, 0, -5);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_whatsapp.static::$path_whatsapp."/".$bot->number_id."/messages");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer ".$cadenaDesencriptada,
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
                'error'=>'Error al conectar con WhatsApp',
                'whatsapp'=>$err
            ];

        } else {

            $whatsapp_obj = json_decode($response);

            return [
                'status'=>200,
                'whatsapp'=>$whatsapp_obj
            ];

            if (property_exists($whatsapp_obj, 'messages')) {

                return [
                    'status'=>200,
                    'whatsapp'=>$whatsapp_obj
                ]; 

            }else{
                return [
                    'status'=>409,
                    'error'=>'Error al enviar mensaje',
                    'whatsapp'=>$whatsapp_obj
                ];
            }

        }  

    }

    public static function _messageDocument($bot_id, $to, $body, $reference, $ext='pdf')
    {
        set_time_limit(500);

        $bot = Bot::find($bot_id);
        if (!$bot)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'No existe el bot con id '.$bot_id,
                'whatsapp'=>null
            ];
        }

        if ($bot->status != 1)
        {
            // Devolvemos error codigo http 409
            return [
                'status'=>409,
                'error'=>'Bot inactivo',
                'whatsapp'=>null
            ];
        }

        $rest = substr($to, 0, 3);
        if($rest == 521){
            $to = str_replace("521", "52", $to);
        }

        $fechaHora = date('Ymd_His');
        $nombreArchivo = "{$reference}_{$fechaHora}.{$ext}";

        //Armando la peticion cURL        
        $fields = array(
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'type'=> 'document',
            'to' => '+'.$to,
            //'to' => $to,
            'document' => array(
                'link' => $body,
                'filename' => $nombreArchivo
            )
        ); 

            
        $fields = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $claveAdicional = config('app.lada_b');
        $cadenaDesencriptada = Crypt::decrypt($bot->access_token, $claveAdicional);
        $cadenaDesencriptada = substr($cadenaDesencriptada, 0, -5);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_whatsapp.static::$path_whatsapp."/".$bot->number_id."/messages");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer ".$cadenaDesencriptada,
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
                'error'=>'Error al conectar con WhatsApp',
                'whatsapp'=>$err
            ];

        } else {

            $whatsapp_obj = json_decode($response);

            return [
                'status'=>200,
                'whatsapp'=>$whatsapp_obj
            ];

            if (property_exists($whatsapp_obj, 'messages')) {

                return [
                    'status'=>200,
                    'whatsapp'=>$whatsapp_obj
                ]; 

            }else{
                return [
                    'status'=>409,
                    'error'=>'Error al enviar mensaje',
                    'whatsapp'=>$whatsapp_obj
                ];
            }

        }  

    }

    public static function _messageTemplate($bot_id, $to, $name, $code = 'es_MX' )
    {
        set_time_limit(500);

        $bot = Bot::find($bot_id);
        if (!$bot)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'No existe el bot con id '.$bot_id,
                'whatsapp'=>null
            ];
        }

        if ($bot->status != 1)
        {
            // Devolvemos error codigo http 409
            return [
                'status'=>409,
                'error'=>'Bot inactivo',
                'whatsapp'=>null
            ];
        }

        $rest = substr($to, 0, 3);
        if($rest == 521){
            $to = str_replace("521", "52", $to);
        }

        //Armando la peticion cURL        
        $fields = array(
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'type'=> 'template',
            'to' => '+'.$to,
            //'to' => $to,
            'template' => array(
                'name' => $name,
                'language' => array(
                    'code' => $code
                )/*,
                'components' => array(
                    array(
                        'type' => 'body',
                        'parameters' => array(
                            array(
                                'type' => 'text',
                                'type' => 'TEXT_STRING',
                                )
                        )
                    )
                )*/
            )
        ); 

            
        $fields = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $claveAdicional = config('app.lada_b');
        $cadenaDesencriptada = Crypt::decrypt($bot->access_token, $claveAdicional);
        $cadenaDesencriptada = substr($cadenaDesencriptada, 0, -5);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_whatsapp.static::$path_whatsapp."/".$bot->number_id."/messages");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer ".$cadenaDesencriptada,
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
                'error'=>'Error al conectar con WhatsApp',
                'whatsapp'=>$err
            ];

        } else {

            $whatsapp_obj = json_decode($response);

            return [
                'status'=>200,
                'whatsapp'=>$whatsapp_obj
            ];

            if (property_exists($whatsapp_obj, 'messages')) {

                return [
                    'status'=>200,
                    'whatsapp'=>$whatsapp_obj
                ]; 

            }else{
                return [
                    'status'=>409,
                    'error'=>'Error al enviar mensaje',
                    'whatsapp'=>$whatsapp_obj
                ];
            }

        }  

    }

    public static function _messageTemplateParameters($bot_id, $to, $name, $code = 'es_MX', $type, $parameters)
    {
        set_time_limit(500);

        $bot = Bot::find($bot_id);
        if (!$bot)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'No existe el bot con id '.$bot_id,
                'whatsapp'=>null
            ];
        }

        if ($bot->status != 1)
        {
            // Devolvemos error codigo http 409
            return [
                'status'=>409,
                'error'=>'Bot inactivo',
                'whatsapp'=>null
            ];
        }

        $rest = substr($to, 0, 3);
        if($rest == 521){
            $to = str_replace("521", "52", $to);
        }

        //Armando la peticion cURL        
        $fields = array(
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'type'=> 'template',
            'to' => '+'.$to,
            //'to' => $to,
            'template' => array(
                'name' => $name,
                'language' => array(
                    'code' => $code
                ),
                'components' => array(
                    array(
                        'type' => $type,
                        'parameters' => $parameters
                    )
                )
            )
        ); 

            
        $fields = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $claveAdicional = config('app.lada_b');
        $cadenaDesencriptada = Crypt::decrypt($bot->access_token, $claveAdicional);
        $cadenaDesencriptada = substr($cadenaDesencriptada, 0, -5);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_whatsapp.static::$path_whatsapp."/".$bot->number_id."/messages");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer ".$cadenaDesencriptada,
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
                'error'=>'Error al conectar con WhatsApp',
                'whatsapp'=>$err
            ];

        } else {

            $whatsapp_obj = json_decode($response);

            return [
                'status'=>200,
                'whatsapp'=>$whatsapp_obj
            ];

            if (property_exists($whatsapp_obj, 'messages')) {

                return [
                    'status'=>200,
                    'whatsapp'=>$whatsapp_obj
                ]; 

            }else{
                return [
                    'status'=>409,
                    'error'=>'Error al enviar mensaje',
                    'whatsapp'=>$whatsapp_obj
                ];
            }

        }  

    }

}
