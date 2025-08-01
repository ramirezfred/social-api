<?php

namespace App\Http\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

use App\Http\Requests;

use App\Models\User;
use App\Models\SocialBrand;
use App\Models\SocialNetwork;
use App\Models\SocialPost;
use App\Models\Sistema;

use DB;

use Exception;

trait SmsTrait
{

    public static function smsAutoPBX_Trait($message, $number)
    {

        //https://api.portaldesms.com:8282/?username=Aaguirre&password=h7m$7G31JIvc&number=525527399115&message=PruebaDeChrome

        $username = "Aaguirre";
        $password = "h7m$7G31JIvc";

        //$mensaje = "Hola,%20te%20damos%20la%20bienvenida%20a%20GOOPY,%20confirmación%20de%20código%20".$codigo."%20¡Vive%20la%20experiencia%20Goopy!"; 

        //$message = rawurlencode($message);
        $message='PruebaSMS';

        // URL de la solicitud
        $url = "https://api.portaldesms.com:8282/?username=".$username."&password=".$password."&number=52".$number."&message=".$message;    

        // $ch = curl_init();
        // curl_setopt($ch, CURLOPT_URL, $url);
        // curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        /*$ch = curl_init();
        curl_setopt_array($ch, array(
          CURLOPT_URL => 'https://api.portaldesms.com:8282/?username=Aaguirre&password=h7m%247G31JIvc&number=52'.$number.'&message='.$message,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'GET',
          CURLOPT_SSL_VERIFYPEER => false,
        ));

        $response = curl_exec($ch);
        $err = curl_error($ch);

        curl_close($ch);

        if ($err) {
            //echo "cURL Error #:" . $err;
            return $err;
        } else {
            //echo $response;
            return $response;
        }*/

        /*$options = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
            ),
        );

        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);

        return $response;

        // Verifica si la solicitud falló
        if ($response === false) {
            //echo 'Error: no se puede obtener la respuesta del servidor';
            return 0;
        } else {
            //echo $response;
            return 1;
        }*/

        //$response = Http::get($url);
        $response = Http::withOptions(['verify' => false])->get($url);

        return $response;

        // Verificar si la solicitud fue exitosa (código de estado 2xx)
        if ($response->successful()) {
            return $response->json(); // Devuelve la respuesta en formato JSON
        } else {
            // Manejar el error en caso de no ser exitosa
            return $response->status(); // Devuelve el código de estado HTTP
        }
    
    }

    

}
