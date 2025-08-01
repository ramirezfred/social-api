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

use Carbon\Carbon;

date_default_timezone_set('America/Mexico_City');

trait ApiChatPdfTrait
{
    public static $base_url_chat_pdf = "https://api.chatpdf.com";
    public static $path_chat_pdf = "/v1";
    public static $token_chat_pdf = "sec_0kViKgwqI6oI2LB25yuwNRWuXqjlJXEN";

    
    public static function _respuestaChatPdf($mensajes)
    {
        set_time_limit(500);

        $text_mensajes = '';
        for ($i=0; $i < count($mensajes); $i++) { 
            if($i == 0){
                $text_mensajes = $mensajes[$i];
            }else{
                $text_mensajes = $text_mensajes.', '.$mensajes[$i];
            }
        }

        //Armando la peticion cURL        
        $fields = array(
            "sourceId" => "src_z4fvGbsHmz2g3T0vrh40E",
            "messages" => array(
                array(
                    "role" => "user",
                    "content" => $text_mensajes
                )
            )
        );
   
        $fields_json = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_chat_pdf.static::$path_chat_pdf."/chats/message");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "x-api-key: ".static::$token_chat_pdf,
            "Content-Type: application/json"
        ));

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_json);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        $err = curl_error($ch);

        curl_close($ch);

        if ($err) {
            //echo "cURL Error #:" . $err;
            return [
                'status'=>409,
                'error'=>'Error al conectar con ChatPDF',
                'open_ai'=>$err
            ];
        } else {

            $chat_pdf_obj = json_decode($response);

            if (property_exists($chat_pdf_obj, 'content')) {

                $text =  $chat_pdf_obj->content;

                return [
                    'status'=>200,
                    'text'=>$text,
                    'chat_pdf'=>$chat_pdf_obj
                ];    

            }else{
                return [
                    'status'=>409,
                    'error'=>$chat_pdf_obj->error,
                    'chat_pdf'=>$chat_pdf_obj
                ];
            }
          
        }  

    }

}
