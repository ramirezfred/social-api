<?php

namespace App\Http\Traits;

use Illuminate\Http\Request;

use App\Http\Requests;

use App\Models\User;
use App\Models\SocialBrand;
use App\Models\SocialNetwork;
use App\Models\SocialPost;
use App\Models\Sistema;

use DB;

use Exception;

trait VpsTrait
{

    public static $base_url_vps = "https://api.goopy.app";


    //Facebook
    public static function _deleteImagen($link)
    {
        set_time_limit(500);

        //Armando la peticion cURL        
        $fields = array(
            "link" => $link,
        ); 
            
        $fields = json_encode($fields);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_vps."/internow_social/borrar/link/imagen");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            //"Authorization: Bearer ".$token,
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
                'error'=>'Error al conectar con Goopy',
                'goopy'=>$err
            ];
        } else {

            $goopy_obj = json_decode($response);
          
            if (property_exists($goopy_obj, 'id')) {

                return [
                    'status'=>200,
                    'goopy'=>$goopy_obj
                ];    

            }else{
                return [
                    'status'=>409,
                    'error'=>"Error al eliminar la imagen",
                    'goopy'=>$goopy_obj
                ];
            }  
          
        }  

    }

    

}
