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

trait ApiMetaTrait
{
    //App Internor Social Freddy
    public static $base_url_meta = "https://graph.facebook.com";
    // public static $path_meta = "/v16.0";
    public static $path_meta = "/v22.0";
    public static $client_id_meta = "480739347512010"; //app id
    public static $client_secret_meta = "eee9a22c0fe7d55f759b70eb79095066"; //app secret

    // //App pruebas API Freddy
    // public static $base_url_meta = "https://graph.facebook.com";
    // public static $path_meta = "/v16.0";
    // public static $client_id_meta = "131198609825327"; //app id
    // public static $client_secret_meta = "71b539b493ab7571cf3675afdd9a1aab"; //app secret


    public static function _userAccesTokenLongLive($access_token)
    {
        set_time_limit(500);


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_meta.static::$path_meta."/oauth/access_token?grant_type=fb_exchange_token&client_id=".static::$client_id_meta."&client_secret=".static::$client_secret_meta."&fb_exchange_token=".$access_token);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        $err = curl_error($ch);

        curl_close($ch);

        if ($err) {
            //echo "cURL Error #:" . $err;
            return [
                'status'=>409,
                'error'=>'Error al conectar con Meta',
                'meta'=>$err
            ];
        } else {

            $meta_obj = json_decode($response);
          
            if (property_exists($meta_obj, 'access_token')) {

                return [
                    'status'=>200,
                    'meta'=>$meta_obj
                ];    

            }else{
                return [
                    'status'=>409,
                    'error'=>"Error al generar token de larga duración",
                    'meta'=>$meta_obj
                ];
            }  
          
        }  

    }

    //Facebook
    public static function _accounts($user_id,$access_token)
    {
        set_time_limit(500);


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_meta.static::$path_meta."/".$user_id."/accounts?access_token=".$access_token);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        $err = curl_error($ch);

        curl_close($ch);

        if ($err) {
            //echo "cURL Error #:" . $err;
            return [
                'status'=>409,
                'error'=>'Error al conectar con Meta',
                'meta'=>$err
            ];
        } else {

            $meta_obj = json_decode($response);
          
            if (property_exists($meta_obj, 'data')) {

                for ($i=0; $i < count($meta_obj->data); $i++) { 
                    $imagen = static::_photos($meta_obj->data[$i]->id,$meta_obj->data[$i]->access_token);

                    if (
                            $imagen['status'] == 200 && 
                            isset($imagen['meta']) && 
                            isset($imagen['meta']->data) && 
                            !empty($imagen['meta']->data)
                        ) {
                        $meta_obj->data[$i]->imagen_id = $imagen['meta']->data[0]->id;
                        // $meta_obj->data[$i]->imagen_url = 
                        //     "https://graph.facebook.com/v16.0/".$imagen['meta']->data[0]->id."/picture?type=thumbnail&access_token=".$meta_obj->data[$i]->access_token;
                        $meta_obj->data[$i]->imagen_url = 
                            "https://graph.facebook.com/v22.0/".$imagen['meta']->data[0]->id."/picture?type=thumbnail&access_token=".$meta_obj->data[$i]->access_token;
                    }else{
                        $meta_obj->data[$i]->imagen_id = null;
                        $meta_obj->data[$i]->imagen_url = null;

                        // Opcional: Log para depuración
                        \Log::warning('No se pudo obtener imagen para la página', [
                            'page_id' => $meta_obj->data[$i]->id,
                            'access_token' => $meta_obj->data[$i]->access_token,
                            'imagen_response' => $imagen ?? 'sin respuesta'
                        ]);
                    }
                }

                return [
                    'status'=>200,
                    'meta'=>$meta_obj
                ];    

            }else{
                return [
                    'status'=>409,
                    'error'=>"Error al consultar las páginas",
                    'meta'=>$meta_obj
                ];
            }  
          
        }  

    }

    //Facebook
    public static function _newPostPhoto($page_id,$message,$url,$access_token)
    {
        set_time_limit(500);

        //Armando la peticion cURL        
        $fields = array(
            "message" => $message,
            "url" => $url,
            "published"=>true,
            "access_token" => $access_token
        ); 
            
        $fields = json_encode($fields);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_meta.static::$path_meta."/".$page_id."/photos");
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
                'error'=>'Error al conectar con Meta',
                'meta'=>$err
            ];
        } else {

            $meta_obj = json_decode($response);
          
            if (property_exists($meta_obj, 'id')) {

                return [
                    'status'=>200,
                    'meta'=>$meta_obj
                ];    

            }else{
                return [
                    'status'=>409,
                    'error'=>"Error al crear el Post",
                    'meta'=>$meta_obj
                ];
            }  
          
        }  

    }

    //Facebook
    /*Obtener el id de la foto de perfil*/
    public static function _photos($page_id,$access_token)
    {
        set_time_limit(500);


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_meta.static::$path_meta."/".$page_id."/photos?type=profile&access_token=".$access_token);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        $err = curl_error($ch);

        curl_close($ch);

        if ($err) {
            //echo "cURL Error #:" . $err;
            return [
                'status'=>409,
                'error'=>'Error al conectar con Meta',
                'meta'=>$err
            ];
        } else {

            $meta_obj = json_decode($response);

            \Log::error("[MetaAPITrait] _photos: " . $response);
          
            if (property_exists($meta_obj, 'data')) {

                return [
                    'status'=>200,
                    'meta'=>$meta_obj
                ];    

            }else{
                return [
                    'status'=>409,
                    'error'=>"Error al consultar la foto de perfil",
                    'meta'=>$meta_obj
                ];
            }  
          
        }  

    }

    //Facebook
    /*Obtener la foto de perfil
    para mostrarla en el front*/
    public static function _picture($photo_id,$access_token)
    {
        set_time_limit(500);


        $ch = curl_init();
        //curl_setopt($ch, CURLOPT_URL, static::$base_url_meta.static::$path_meta."/".$photo_id."/picture?type=thumbnail&access_token=".$access_token);
        curl_setopt($ch, CURLOPT_URL, static::$base_url_meta.static::$path_meta."/".$photo_id."/picture?access_token=".$access_token);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        $err = curl_error($ch);

        curl_close($ch);

        if ($err) {
            //echo "cURL Error #:" . $err;
            return [
                'status'=>409,
                'error'=>'Error al conectar con Meta',
                'meta'=>$err
            ];
        } else {

            return $response;

            // $meta_obj = json_decode($response);
          
            // if (property_exists($meta_obj, 'data')) {

            //     return [
            //         'status'=>200,
            //         'meta'=>$meta_obj
            //     ];    

            // }else{
            //     return [
            //         'status'=>409,
            //         'error'=>"Error al consultar la foto de perfil",
            //         'meta'=>$meta_obj
            //     ];
            // }  
          
        }  

    }

    //Facebook
    public static function _getSubscribedApps($page_id,$access_token)
    {
        set_time_limit(500);


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_meta.static::$path_meta."/".$page_id."/subscribed_apps?access_token=".$access_token);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        $err = curl_error($ch);

        curl_close($ch);

        if ($err) {
            //echo "cURL Error #:" . $err;
            return [
                'status'=>409,
                'error'=>'Error al conectar con Meta',
                'meta'=>$err
            ];
        } else {

            $meta_obj = json_decode($response);
          
            if (property_exists($meta_obj, 'data')) {

                return [
                    'status'=>200,
                    'meta'=>$meta_obj
                ];    

            }else{
                return [
                    'status'=>409,
                    'error'=>"Error al consultar los Webhooks",
                    'meta'=>$meta_obj
                ];
            }  
          
        }  

    }

    //Facebook
    public static function _postSubscribedApps($page_id,$access_token)
    {
        set_time_limit(500);


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_meta.static::$path_meta."/".$page_id."/subscribed_apps?subscribed_fields=feed&access_token=".$access_token);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        //curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        $err = curl_error($ch);

        curl_close($ch);

        if ($err) {
            //echo "cURL Error #:" . $err;
            return [
                'status'=>409,
                'error'=>'Error al conectar con Meta',
                'meta'=>$err
            ];
        } else {

            $meta_obj = json_decode($response);
          
            if (property_exists($meta_obj, 'success')) {

                return [
                    'status'=>200,
                    'meta'=>$meta_obj
                ];    

            }else{
                return [
                    'status'=>409,
                    'error'=>"Error al suscribirse a los Webhooks",
                    'meta'=>$meta_obj
                ];
            }  
          
        }  

    }

    //Facebook
    public static function _feed($page_id,$access_token)
    {
        set_time_limit(500);


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_meta.static::$path_meta."/".$page_id."/feed?access_token=".$access_token);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        $err = curl_error($ch);

        curl_close($ch);

        if ($err) {
            //echo "cURL Error #:" . $err;
            return [
                'status'=>409,
                'error'=>'Error al conectar con Meta',
                'meta'=>$err
            ];
        } else {

            $meta_obj = json_decode($response);
          
            if (property_exists($meta_obj, 'data')) {

                return [
                    'status'=>200,
                    'meta'=>$meta_obj
                ];    

            }else{
                return [
                    'status'=>409,
                    'error'=>"Error al consultar el feed",
                    'meta'=>$meta_obj
                ];
            }  
          
        }  

    }

    //Facebook
    public static function _getComments($post_id,$access_token)
    {
        set_time_limit(500);


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_meta.static::$path_meta."/".$post_id."/comments?access_token=".$access_token);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        $err = curl_error($ch);

        curl_close($ch);

        if ($err) {
            //echo "cURL Error #:" . $err;
            return [
                'status'=>409,
                'error'=>'Error al conectar con Meta',
                'meta'=>$err
            ];
        } else {

            $meta_obj = json_decode($response);
          
            if (property_exists($meta_obj, 'data')) {

                return [
                    'status'=>200,
                    'meta'=>$meta_obj
                ];    

            }else{
                return [
                    'status'=>409,
                    'error'=>"Error al consultar los comentarios",
                    'meta'=>$meta_obj
                ];
            }  
          
        }  

    }

    //Facebook
    public static function _postComments($comment_id,$access_token,$message)
    {
        set_time_limit(500);

        //Armando la peticion cURL        
        // $fields = array(
        //     "message" => $message,
        //     "access_token" => $access_token
        // ); 
            
        //$fields = json_encode($fields);

        //$message = str_replace("%20", " ", $message);
        //$message = str_replace(" ", "%20", $message);
        $message = rawurlencode($message);

        $ch = curl_init();
        //curl_setopt($ch, CURLOPT_URL, static::$base_url_meta.static::$path_meta."/".$comment_id."/comments");
        curl_setopt($ch, CURLOPT_URL, static::$base_url_meta.static::$path_meta."/".$comment_id."/comments?message=".$message."&access_token=".$access_token);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        //curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        $err = curl_error($ch);

        curl_close($ch);

        if ($err) {
            //echo "cURL Error #:" . $err;
            return [
                'status'=>409,
                'error'=>'Error al conectar con Meta',
                'meta'=>$err
            ];
        } else {

            //return $response;

            $meta_obj = json_decode($response);
            
            if (property_exists($meta_obj, 'id')) {

                return [
                    'status'=>200,
                    'meta'=>$meta_obj
                ];    

            }else{
                return [
                    'status'=>409,
                    'error'=>"Error al crear comentario",
                    'meta'=>$meta_obj
                ];
            }  
          
        }  

    }

    //Instagram
    public static function _meAccounts($access_token)
    {
        set_time_limit(500);

        //$token_test = "EAAG1OtoJusoBANeuK1DFUKwx8Bb8igzEwr2UVB5V68E58o8lGwrP2p4s5CknXQCAEBPHtAXxdNsgQ9AZCaf7cnWgg6gaRihkOGE4qsQhzYNAq61ioZCor3dAWjZBq5bZAs6LprQqxwuzWBpZBarAZCBGbJabgNXbWkaEtqnpm8ZB7QMdJ5AwacAZBxRn16JUENoZD";


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_meta.static::$path_meta."/me/accounts?fields=instagram_business_account%7Bid%2Cname%2Cusername%2Cprofile_picture_url%7D&access_token=".$access_token);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        $err = curl_error($ch);

        curl_close($ch);

        if ($err) {
            //echo "cURL Error #:" . $err;
            return [
                'status'=>409,
                'error'=>'Error al conectar con Meta',
                'meta'=>$err
            ];
        } else {

            $meta_obj = json_decode($response);

            $data = [];
          
            if (property_exists($meta_obj, 'data')) {

                for ($i=0; $i < count($meta_obj->data); $i++) { 
                    if(property_exists($meta_obj->data[$i], 'instagram_business_account')){
                        array_push($data,$meta_obj->data[$i]);
                    }
                }

                $meta_obj->data = $data;

                return [
                    'status'=>200,
                    'meta'=>$meta_obj,
                    //'meta_obj'=>$meta_obj,
                ];    

            }else{
                return [
                    'status'=>409,
                    'error'=>"Error al consultar las páginas de Instagram",
                    'meta'=>$meta_obj
                ];
            }  
          
        }  

    }

    //Instagram
    public static function _media($page_id,$message,$url,$access_token)
    {
        set_time_limit(500);

        //Armando la peticion cURL        
        $fields = array(
            "caption" => $message,
            "image_url" => $url,
            //"image_url" => "https://api.goopy.app/internow_social/test.jpg",
            "access_token" => $access_token
        ); 
            
        $fields = json_encode($fields);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_meta.static::$path_meta."/".$page_id."/media");
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
                'error'=>'Error al conectar con Meta',
                'meta'=>$err
            ];
        } else {

            $meta_obj = json_decode($response);
          
            if (property_exists($meta_obj, 'id')) {

                return [
                    'status'=>200,
                    'meta'=>$meta_obj
                ];    

            }else{
                return [
                    'status'=>409,
                    'error'=>"Error al crear el Post",
                    'meta'=>$meta_obj
                ];
            }  
          
        }  

    }

    //Instagram
    public static function _mediaPublish($page_id,$creation_id,$access_token)
    {
        set_time_limit(500);

        //Armando la peticion cURL        
        $fields = array(
            "creation_id" => $creation_id,
            "access_token" => $access_token
        ); 
            
        $fields = json_encode($fields);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_meta.static::$path_meta."/".$page_id."/media_publish");
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
                'error'=>'Error al conectar con Meta',
                'meta'=>$err
            ];
        } else {

            $meta_obj = json_decode($response);
          
            if (property_exists($meta_obj, 'id')) {

                return [
                    'status'=>200,
                    'meta'=>$meta_obj
                ];    

            }else{
                return [
                    'status'=>409,
                    'error'=>"Error al crear el Post",
                    'meta'=>$meta_obj
                ];
            }  
          
        }  

    }

    //Instagram
    public static function _postReplies($comment_id,$access_token,$message)
    {
        set_time_limit(500);

        //Armando la peticion cURL        
        // $fields = array(
        //     "message" => $message,
        //     "access_token" => $access_token
        // ); 
            
        //$fields = json_encode($fields);

        //$message = str_replace("%20", " ", $message);
        //$message = str_replace(" ", "%20", $message);
        $message = rawurlencode($message);

        $ch = curl_init();
        //curl_setopt($ch, CURLOPT_URL, static::$base_url_meta.static::$path_meta."/".$comment_id."/replies");
        curl_setopt($ch, CURLOPT_URL, static::$base_url_meta.static::$path_meta."/".$comment_id."/replies?message=".$message."&access_token=".$access_token);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        //curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        $err = curl_error($ch);

        curl_close($ch);

        if ($err) {
            //echo "cURL Error #:" . $err;
            return [
                'status'=>409,
                'error'=>'Error al conectar con Meta',
                'meta'=>$err
            ];
        } else {

            //return $response;

            $meta_obj = json_decode($response);
            
            if (property_exists($meta_obj, 'id')) {

                return [
                    'status'=>200,
                    'meta'=>$meta_obj
                ];    

            }else{
                return [
                    'status'=>409,
                    'error'=>"Error al crear comentario",
                    'meta'=>$meta_obj
                ];
            }  
          
        }  

    }

}
