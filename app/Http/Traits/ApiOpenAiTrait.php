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

trait ApiOpenAiTrait
{
    public static $base_url_openai = "https://api.openai.com";
    public static $path_openai = "/v1";

    //public static $model_openai = "text-davinci-003";
    public static $model_openai = "gpt-3.5-turbo-instruct";

    public static function _davinciTextos($brand_id)
    {
        set_time_limit(500);

        $sistema = Sistema::all();
        if (count($sistema)==0)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'Sistema no configurado',
                'open_ai'=>null
            ];
        }
        $token = $sistema[0]->key_1;

        $marca = SocialBrand::find($brand_id);
        if (!$marca)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'No existe la marca con id '.$brand_id,
                'open_ai'=>null
            ];
        }

        $servicios = '';

        $aux_serv = json_decode($marca->servicios);
        for ($i=0; $i < count($aux_serv); $i++) { 
            // if($i == 0){
            //     $servicios = $aux_serv[$i];
            // }else if($i > 0 && $i < count($aux_serv)-1){
            //     $servicios = $servicios.', '.$aux_serv[$i];
            // }else if($i > 0 && $i == count($aux_serv)-1){
            //     $servicios = $servicios.' y '.$aux_serv[$i];
            // }

            $servicios = $servicios.' '.($i+1).'. '.$aux_serv[$i];
        }

        $cantidad = 5;

        // $prompt = 'Genera una lista de '.$cantidad.' textos para publicar en redes sociales de una marca que se llama '.$marca->nombre.' y ofrece los servicios de '.$servicios;

        //$prompt_aux = $sistema[0]->prompt_textos;
        $prompt_aux = $marca->prompt_textos;

        if ($marca->prompt_textos == null || $marca->prompt_textos == '')
        {
            return [
                'status'=>409,
                'error'=>'Configure el prompt para los textos de la marca.',
                'open_ai'=>null
            ];
        }

        //extraer la cantidad
        $posicionA = strpos($prompt_aux, '<');
        $posicionB = strpos($prompt_aux, '>');

        $cantidad = substr($prompt_aux,$posicionA+1,$posicionB-($posicionA+1));
        $cantidad = intval($cantidad);

        $prompt = str_replace("<", "", $prompt_aux);
        $prompt = str_replace(">", "", $prompt);
        $prompt = str_replace("{{marca}}", $marca->nombre, $prompt);
        $prompt = str_replace("{{servicios}}", $servicios, $prompt);

        // return [
        //     'status'=>200,
        //     'prompt'=>$prompt
        // ];


        //Armando la peticion cURL        
        $fields = array(
            //"model" => "text-davinci-003",
            "model" => static::$model_openai,
            "prompt" => $prompt,
            "temperature" => 0.5,
            "max_tokens" => 2048 
        ); 

            
        $fields = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_openai.static::$path_openai."/completions");
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
                'error'=>'Error al conectar con OpenAi',
                'open_ai'=>$err
            ];

        } else {

            $open_ai_obj = json_decode($response);

            if (property_exists($open_ai_obj, 'id')) {

                $textos =  $open_ai_obj->choices[0]->text;

                $textos = str_replace("\n\n", "\n", $textos);
                $textos = str_replace("\\\"", "", $textos);
                
                $remplace = [];
                for ($i=1; $i <= $cantidad; $i++) { 
                    array_push($remplace, $i.". ");
                }

                $textos = str_replace($remplace, "", $textos);

                $array_textos = explode("\n", $textos);

                $array_textos_redes = [];

                $i = 0;
                if (count($array_textos) > $cantidad) {
                    $i = 1;
                }

                for ($i; $i < count($array_textos); $i++) { 
                    if ($array_textos[$i] != "") {

                        if (strstr($array_textos[$i], "English: ")) {
                            $array_textos[$i] = str_replace("English: ", "", $array_textos[$i]);
                        }
                        if (strstr($array_textos[$i], "English:")) {
                            $array_textos[$i] = str_replace("English:", "", $array_textos[$i]);
                        }
                        if (strstr($array_textos[$i], "English")) {
                            $array_textos[$i] = str_replace("English", "", $array_textos[$i]);
                        }

                        if (strstr($array_textos[$i], "Inglés: ")) {
                            $array_textos[$i] = str_replace("Inglés: ", "", $array_textos[$i]);
                        }
                        if (strstr($array_textos[$i], "Inglés:")) {
                            $array_textos[$i] = str_replace("Inglés:", "", $array_textos[$i]);
                        }
                        if (strstr($array_textos[$i], "Inglés")) {
                            $array_textos[$i] = str_replace("Inglés", "", $array_textos[$i]);
                        }

                        if (strstr($array_textos[$i], "Spanish: ")) {
                            $array_textos[$i] = str_replace("Spanish: ", "", $array_textos[$i]);
                        }
                        if (strstr($array_textos[$i], "Spanish:")) {
                            $array_textos[$i] = str_replace("Spanish:", "", $array_textos[$i]);
                        }
                        if (strstr($array_textos[$i], "Spanish")) {
                            $array_textos[$i] = str_replace("Spanish", "", $array_textos[$i]);
                        }

                        if (strstr($array_textos[$i], "Español: ")) {
                            $array_textos[$i] = str_replace("Español: ", "", $array_textos[$i]);
                        }
                        if (strstr($array_textos[$i], "Español:")) {
                            $array_textos[$i] = str_replace("Español:", "", $array_textos[$i]);
                        }
                        if (strstr($array_textos[$i], "Español")) {
                            $array_textos[$i] = str_replace("Español", "", $array_textos[$i]);
                        }

                        array_push($array_textos_redes, $array_textos[$i]);
                    }
                }

                return [
                    'status'=>200,
                    'prompt'=>$prompt,
                    'array_textos_redes'=>$array_textos_redes,
                    'open_ai'=>$open_ai_obj
                ]; 

            }else{
                return [
                    'status'=>409,
                    'error'=>$open_ai_obj->error->message,
                    'open_ai'=>$open_ai_obj
                ];
            }

        }  

    }

    public static function _davinciEscena($brand_id, $texto)
    {
        set_time_limit(500);

        $sistema = Sistema::all();
        if (count($sistema)==0)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'Sistema no configurado',
                'open_ai'=>null
            ];
        }
        $token = $sistema[0]->key_2;

        $marca = SocialBrand::find($brand_id);
        if (!$marca)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'No existe la marca con id '.$brand_id,
                'open_ai'=>null
            ];
        }


        // $prompt = 'Genera una escena de 20 palabras sin usar marcas o nombres de empresas, basándose en el siguente texto: '.$texto.',  para una imagen que se va a publicar en redes sociales sin textos.';

        try {
            $array_parametros = json_decode($marca->parametros);
        } catch (Exception $e) {
            $array_parametros = [];
        }

        if (count($array_parametros) == 0) {
            $prompt_aux = $sistema[0]->prompt_escenaA;
            $prompt = str_replace("{{texto}}", $texto, $prompt_aux);
        }else{
            
            $parametros = '';
            for ($i=0; $i < count($array_parametros); $i++) { 
                if($i == 0){
                    $parametros = $array_parametros[$i];
                }else if($i > 0 && $i < count($array_parametros)-1){
                    $parametros = $parametros.', '.$array_parametros[$i];
                }else if($i > 0 && $i == count($array_parametros)-1){
                    $parametros = $parametros.' y '.$array_parametros[$i];
                }
            }

            $prompt_aux = $sistema[0]->prompt_escenaB;
            $prompt = str_replace("{{parametros}}", $parametros, $prompt_aux);
            $prompt = str_replace("{{texto}}", $texto, $prompt);

        }

        // return [
        //     'status'=>200,
        //     'prompt'=>$prompt
        // ];


        //Armando la peticion cURL        
        $fields = array(
            "model" => static::$model_openai,
            "prompt" => $prompt,
            "temperature" => 0.5,
            "max_tokens" => 2048 
        ); 

            
        $fields = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_openai.static::$path_openai."/completions");
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
                'error'=>'Error al conectar con OpenAi',
                'open_ai'=>$err
            ];
        } else {

            $open_ai_obj = json_decode($response);

            if (property_exists($open_ai_obj, 'id')) {

                $escena =  $open_ai_obj->choices[0]->text;

                $escena = str_replace(". \n\n", "", $escena);
                $escena = str_replace(".\n\n", "", $escena);
                $escena = str_replace("\n", "", $escena);
                $escena = str_replace("\r", "", $escena);

                return [
                    'status'=>200,
                    'prompt'=>$prompt,
                    'escena'=>$escena,
                    'open_ai'=>$open_ai_obj
                ];    

            }else{
                return [
                    'status'=>409,
                    'error'=>$open_ai_obj->error->message,
                    'open_ai'=>$open_ai_obj
                ];
            }
          
        }  

    }

    public static function _dalle($brand_id,$escena)
    {
        set_time_limit(500);

        $sistema = Sistema::all();
        if (count($sistema)==0)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'Sistema no configurado',
                'open_ai'=>null
            ];
        }
        $token = $sistema[0]->key_3;

        $marca = SocialBrand::find($brand_id);
        if (!$marca)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'No existe la marca con id '.$brand_id,
                'open_ai'=>null
            ];
        }

        if ($marca->bandera_flujo == 0) {
            
            try {
                $array_parametros = json_decode($marca->parametros_imagen);
            } catch (Exception $e) {
                $array_parametros = [];
            }

            if (count($array_parametros) == 0) {
                $prompt_aux = $sistema[0]->prompt_imagenesA;
                $prompt = str_replace("{{escena}}", $escena, $prompt_aux);
            }else{
                
                $parametros = '';
                for ($i=0; $i < count($array_parametros); $i++) { 
                    if($i == 0){
                        $parametros = $array_parametros[$i];
                    }else if($i > 0 && $i < count($array_parametros)-1){
                        $parametros = $parametros.', '.$array_parametros[$i];
                    }else if($i > 0 && $i == count($array_parametros)-1){
                        //$parametros = $parametros.' y '.$array_parametros[$i];
                        $parametros = $parametros.' '.$array_parametros[$i].'.';
                    }
                }

                $prompt_aux = $sistema[0]->prompt_imagenesB;
                $prompt = str_replace("{{parametros}}", $parametros, $prompt_aux);
                $prompt = str_replace("{{escena}}", $escena, $prompt);

            }

        }

        if ($marca->bandera_flujo == 1) {
            $prompt = $escena;
        }

        //Armando la peticion cURL        
        $fields = array(
            "prompt" => $prompt,
            "n" => 2,
            //"size" => "256x256",
            "size" => "512x512",
            //"size" => "1024x1024",
            "response_format" => "b64_json" 
        ); 
            
        $fields = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_openai.static::$path_openai."/images/generations");
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
                'error'=>'Error al conectar con OpenAi',
                'open_ai'=>$err
            ];
        } else {

            $open_ai_obj = json_decode($response);
          
            if (property_exists($open_ai_obj, 'created')) {

                return [
                    'status'=>200,
                    'open_ai'=>$open_ai_obj
                ];    

            }else{
                return [
                    'status'=>409,
                    'error'=>$open_ai_obj->error->message,
                    'open_ai'=>$open_ai_obj
                ];
            }  
          
        }  

    }

    public static function _davinciPalabrasClaveEmpresa($brand_id)
    {
        set_time_limit(500);

        $sistema = Sistema::all();
        if (count($sistema)==0)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'Sistema no configurado',
                'open_ai'=>null
            ];
        }
        $token = $sistema[0]->key_1;

        $marca = SocialBrand::find($brand_id);
        if (!$marca)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'No existe la marca con id '.$brand_id,
                'open_ai'=>null
            ];
        }

        $servicios = '';

        $aux_serv = json_decode($marca->servicios);
        for ($i=0; $i < count($aux_serv); $i++) { 
            if($i == 0){
                $servicios = $aux_serv[$i];
            }else if($i > 0 && $i < count($aux_serv)-1){
                $servicios = $servicios.', '.$aux_serv[$i];
            }else if($i > 0 && $i == count($aux_serv)-1){
                $servicios = $servicios.' y '.$aux_serv[$i];
            }
        }

        $cantidad = 2;

        /*$prompt = 'Tengo una empresa que ofrece los servicios de '.$servicios.'. Genera una lista de '.$cantidad.' palabras o frases clave, relacionadas con esos servicios para consultar imágenes a la API de Pexels. La lista solo debe contener las palabras o frases clave.';*/

        $prompt = 'Genera una lista de '.$cantidad.' palabras o frases clave, relacionadas con una empresa que ofrece los servicios de: '.$servicios.'. La lista solo debe contener las palabras o frases clave. No incluyas nombres de marcas ni caracteres especiales, solo usa letras.';

        // return [
        //     'status'=>200,
        //     'prompt'=>$prompt
        // ];

        //Armando la peticion cURL        
        $fields = array(
            "model" => static::$model_openai,
            "prompt" => $prompt,
            "temperature" => 0.8,
            "max_tokens" => 2048 
        ); 

            
        $fields = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_openai.static::$path_openai."/completions");
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
                'error'=>'Error al conectar con OpenAi',
                'open_ai'=>$err
            ];

        } else {

            $open_ai_obj = json_decode($response);

            if (property_exists($open_ai_obj, 'id')) {

                $textos =  $open_ai_obj->choices[0]->text;

                $textos = str_replace("#", "", $textos);
                $textos = str_replace("\n\n", "\n", $textos);

                $remplace = [];
                for ($i=1; $i <= $cantidad; $i++) { 
                    array_push($remplace, $i.". ");
                }

                $textos = str_replace($remplace, "", $textos);

                $array_textos = explode("\n", $textos);

                $array_palabras_clave = [];

                $i = 0;
                if (count($array_textos) > $cantidad) {
                    $i = 1;
                }

                for ($i; $i < count($array_textos); $i++) { 
                    if ($array_textos[$i] != "") {
                        $palabra_clave = str_replace("-", "", $array_textos[$i]);
                        $string_sin_espacios = trim($palabra_clave);
                        //array_push($array_palabras_clave, $string_sin_espacios);
                        array_unshift($array_palabras_clave, $string_sin_espacios);
                    }
                }

                return [
                    'status'=>200,
                    'prompt'=>$prompt,
                    'array_palabras_clave'=>$array_palabras_clave,
                    'open_ai'=>$open_ai_obj
                ]; 

            }else{
                return [
                    'status'=>409,
                    'error'=>$open_ai_obj->error->message,
                    'open_ai'=>$open_ai_obj
                ];
            }

        }  

    }

    public static function _davinciPalabrasClavePost($post_id)
    {
        set_time_limit(500);

        $sistema = Sistema::all();
        if (count($sistema)==0)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'Sistema no configurado',
                'open_ai'=>null
            ];
        }
        $token = $sistema[0]->key_1;

        $post = SocialPost::find($post_id);
        if (!$post)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'No existe el post con id '.$post_id,
                'open_ai'=>null
            ];
        }

        $cantidad = 2;

        /*$prompt = 'Genera una lista de '.$cantidad.' palabras o frases clave, relacionadas con el siguiente texto: '.$post->texto.' La lista solo debe contener las palabras o frases clave. Las palabras o frases clave de la lista se van a usar para consulatar imagenes a la API de Pexels. No incluyas nombres de marcas.';*/

        $prompt = 'Genera una lista de '.$cantidad.' palabras o frases clave, relacionadas con el siguiente texto: '.$post->texto.' La lista solo debe contener las palabras o frases clave. No incluyas nombres de marcas ni caracteres especiales, solo usa letras.';

        // return [
        //     'status'=>200,
        //     'prompt'=>$prompt
        // ];

        //Armando la peticion cURL        
        $fields = array(
            "model" => static::$model_openai,
            "prompt" => $prompt,
            "temperature" => 0.5,
            "max_tokens" => 2048 
        ); 

            
        $fields = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_openai.static::$path_openai."/completions");
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
                'error'=>'Error al conectar con OpenAi',
                'open_ai'=>$err
            ];

        } else {

            $open_ai_obj = json_decode($response);

            if (property_exists($open_ai_obj, 'id')) {

                $textos =  $open_ai_obj->choices[0]->text;

                $textos = str_replace("#", "", $textos);
                $textos = str_replace("\n\n", "\n", $textos);

                $remplace = [];
                for ($i=1; $i <= $cantidad; $i++) { 
                    array_push($remplace, $i.". ");
                }

                $textos = str_replace($remplace, "", $textos);

                $array_textos = explode("\n", $textos);

                $array_palabras_clave = [];

                $i = 0;
                if (count($array_textos) > $cantidad) {
                    $i = 1;
                }

                for ($i; $i < count($array_textos); $i++) { 
                    if ($array_textos[$i] != "") {
                        $palabra_clave = str_replace("-", "", $array_textos[$i]);
                        $string_sin_espacios = trim($palabra_clave);
                        array_push($array_palabras_clave, $string_sin_espacios);
                    }
                }

                return [
                    'status'=>200,
                    'prompt'=>$prompt,
                    'array_palabras_clave'=>$array_palabras_clave,
                    'open_ai'=>$open_ai_obj
                ]; 

            }else{
                return [
                    'status'=>409,
                    'error'=>$open_ai_obj->error->message,
                    'open_ai'=>$open_ai_obj
                ];
            }

        }  

    }

    public static function _davinciPalabrasClaveEmpresaSinonimos($brand_id)
    {
        set_time_limit(500);

        $sistema = Sistema::all();
        if (count($sistema)==0)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'Sistema no configurado',
                'open_ai'=>null
            ];
        }
        $token = $sistema[0]->key_1;

        $marca = SocialBrand::find($brand_id);
        if (!$marca)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'No existe la marca con id '.$brand_id,
                'open_ai'=>null
            ];
        }

        if ($marca->pexels_frase == '' || $marca->pexels_frase == null)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'La marca no tiene su palabra clave configurada',
                'open_ai'=>null
            ];
        }

        $palabra_clave = $marca->pexels_frase;

        $cantidad = 3;

        $prompt = 'Genera una lista numerada de '.$cantidad.' sinónimos de: '.$palabra_clave.'. La lista solo debe contener los sinónimos. No incluyas caracteres especiales.';

        // return [
        //     'status'=>200,
        //     'prompt'=>$prompt
        // ];

        //Armando la peticion cURL        
        $fields = array(
            "model" => static::$model_openai,
            "prompt" => $prompt,
            "temperature" => 0.8,
            "max_tokens" => 2048 
        ); 

            
        $fields = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_openai.static::$path_openai."/completions");
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
                'error'=>'Error al conectar con OpenAi',
                'open_ai'=>$err
            ];

        } else {

            $open_ai_obj = json_decode($response);

            if (property_exists($open_ai_obj, 'id')) {

                $textos =  $open_ai_obj->choices[0]->text;

                $textos = str_replace("#", "", $textos);
                $textos = str_replace("\n\n", "\n", $textos);

                $remplace = [];
                for ($i=1; $i <= $cantidad; $i++) { 
                    array_push($remplace, $i.". ");
                }

                $textos = str_replace($remplace, "", $textos);

                $array_textos = explode("\n", $textos);

                $array_palabras_clave = [];

                $i = 0;
                if (count($array_textos) > $cantidad) {
                    $i = 1;
                }

                for ($i; $i < count($array_textos); $i++) { 
                    if ($array_textos[$i] != "") {
                        $palabra_clave = str_replace("-", "", $array_textos[$i]);
                        $string_sin_espacios = trim($palabra_clave);
                        //array_push($array_palabras_clave, $string_sin_espacios);
                        array_unshift($array_palabras_clave, $string_sin_espacios);
                    }
                }

                return [
                    'status'=>200,
                    'prompt'=>$prompt,
                    'array_palabras_clave'=>$array_palabras_clave,
                    'open_ai'=>$open_ai_obj
                ]; 

            }else{
                return [
                    'status'=>409,
                    'error'=>$open_ai_obj->error->message,
                    'open_ai'=>$open_ai_obj
                ];
            }

        }  

    }

    public static function _davinciRespuesta($brand_id, $texto)
    {
        set_time_limit(500);

        $sistema = Sistema::all();
        if (count($sistema)==0)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'Sistema no configurado',
                'open_ai'=>null
            ];
        }
        $token = $sistema[0]->key_1;

        $marca = SocialBrand::find($brand_id);
        if (!$marca)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'No existe la marca con id '.$brand_id,
                'open_ai'=>null
            ];
        }

        if ($marca->comment_status != 1)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'Función inactiva para esta marca.',
                'open_ai'=>null
            ];
        }

        if ($marca->comment_prompt === null || $marca->comment_prompt === '')
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'Prompt no configurado.',
                'open_ai'=>null
            ];
        }

        $prompt_aux = $marca->comment_prompt;
        $prompt = str_replace("{{comentario}}", $texto, $prompt_aux);

        //Armando la peticion cURL        
        $fields = array(
            "model" => static::$model_openai,
            "prompt" => $prompt,
            "temperature" => 0.5,
            "max_tokens" => 2048 
        ); 
   
        $fields = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_openai.static::$path_openai."/completions");
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
                'error'=>'Error al conectar con OpenAi',
                'open_ai'=>$err
            ];
        } else {

            $open_ai_obj = json_decode($response);

            if (property_exists($open_ai_obj, 'id')) {

                $respuesta =  $open_ai_obj->choices[0]->text;

                $respuesta = str_replace(". \n\n", "", $respuesta);
                $respuesta = str_replace(".\n\n", "", $respuesta);
                $respuesta = str_replace("\n", "", $respuesta);
                $respuesta = str_replace("\r", "", $respuesta);

                return [
                    'status'=>200,
                    //'prompt'=>$prompt,
                    'respuesta'=>$respuesta,
                    'open_ai'=>$open_ai_obj
                ];    

            }else{
                return [
                    'status'=>409,
                    'error'=>$open_ai_obj->error->message,
                    'open_ai'=>$open_ai_obj
                ];
            }
          
        }  

    }

    public static function _davinciPalabraClaveBot($bot_id, $mensajes)
    {
        set_time_limit(500);

        $sistema = Sistema::all();
        if (count($sistema)==0)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'Sistema no configurado',
                'open_ai'=>null
            ];
        }
        $token = $sistema[0]->key_1;

        $bot = Bot::find($bot_id);
        if (!$bot)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'No existe el bot con id '.$bot_id,
                'open_ai'=>null
            ];
        }

        // $bot_config = BotConfig::
        //     select('id','palabra_clave')
        //     ->where('bot_id', $bot_id)
        //     ->get();

        $bot_config = BotConfig::
            select('id','palabra_clave')
            ->get();
        if (count($bot_config)==0)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'El Bot no tiene configuración.',
                'open_ai'=>null
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

        //$prompt = 'Un cliente nos envió este mensaje por chat: {{text_mensajes}}. Relaciona ese mensaje con una de las siguientes palabras: {{palabras_clave}}. Solo retorna una de las palabras que te estoy dando.';

        //$prompt = 'Un cliente nos envió este mensaje por chat: {{text_mensajes}}. Relaciona ese mensaje con una de las siguientes palabras: {{palabras_clave}}. Solo retorna una de las palabras que te estoy dando.';

        $prompt = 'Palabra Clave: CITAS
Ejemplos de mensaje:
- Quiero crear una cita
- Nueva cita
- Agendar
- Quiero agendar una cita
- Quiero ver mis citas
- Ver mi agenda
- Muéstrame mi agenda
- Muéstrame mis citas
- Cuales son mis citas
- Ver citas 
- Cancelar cita

Palabra Clave: SALUDO
Ejemplos de mensaje:
- Hola
- Quién eres
- Qué eres
- Qué puedes hacer
- Qué sabes hacer
- Cuáles son tus habilidades
- Habilidades

Palabra Clave: DESPEDIDA
Ejemplos de mensaje:
- Gracias
- Adiós
- Chao
- Hasta pronto

Palabra Clave: REDES SOCIALES
Ejemplos de mensaje:
- Redes sociales
- Facebook
- Instagram
- Gestionar resdes sociales
- Publicar en resdes sociales

Palabra Clave: PRODUCTOS
Ejemplos de mensaje:
- Mis productos
- Nuevo producto
- Inventario
- Eliminar producto

Palabra Clave: PEDIDOS
Ejemplos de mensaje:
- Quiero crear un pedido
- Nuevo pedido
- Ver mis pedidos
- Muéstrame mis pedido
- Ver pedidos 
- Cancelar pedido

Palabra Clave: COTIZACIONES
Ejemplos de mensaje:
- Quiero crear una cotización
- Nueva cotización
- Ver mis cotizaciones
- Muéstrame mis cotización
- Ver cotizaciones 
- Cancelar cotización

Palabra Clave: FACTURAS
Ejemplos de mensaje:
- Quiero crear una factura
- Nueva factura
- Ver mis facturas
- Muéstrame mis facturas
- Ver facturas 
- Cancelar factura
- Configurar emisor de factura
- Configurar mis datos

Palabra Clave: NO APLICABLE
Ejemplos de mensaje:
- Haz lo siguiente
- Cuenta un chiste
- Has un resumen

Mensaje: {{texto_mensaje}}

Asocia el Mensaje con una de las Palabras Clave proporcionadas y devuelve la palabra clave que mejor se relacione con el mensaje.  
Si no puedes relacionar el Mensaje con ninguna de las Palabras clave retorna: NO APLICABLE
Solo se debe retornar la palabra clave sin ningún otro texto adicional.
';
        
        $prompt = str_replace("{{texto_mensaje}}", $text_mensajes, $prompt);
        //$prompt = str_replace("{{palabras_clave}}", $palabras_clave, $prompt);

        //Armando la peticion cURL        
        $fields = array(
            "model" => static::$model_openai,
            //"model" => "text-curie-001",
            "prompt" => $prompt,
            "temperature" => 0.5,
            "max_tokens" => 2048 
        ); 
   
        $fields = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_openai.static::$path_openai."/completions");
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
                'error'=>'Error al conectar con OpenAi',
                'open_ai'=>$err
            ];
        } else {

            $open_ai_obj = json_decode($response);

            if (property_exists($open_ai_obj, 'id')) {

                $text =  $open_ai_obj->choices[0]->text;

                $text = str_replace(". \n\n", "", $text);
                $text = str_replace(".\n\n", "", $text);
                $text = str_replace("\n", "", $text);
                $text = str_replace("\r", "", $text);
                //$text = str_replace(" ", "", $text);
                $text = strtoupper($text);

                $text = str_replace("PALABRA CLAVE: ", "", $text);

                return [
                    'status'=>200,
                    //'prompt'=>$prompt,
                    'text'=>$text,
                    'open_ai'=>$open_ai_obj
                ];    

            }else{
                return [
                    'status'=>409,
                    'error'=>$open_ai_obj->error->message,
                    'open_ai'=>$open_ai_obj
                ];
            }
          
        }  

    }

    public static function _davinciRespuestaBot($bot_id, $mensajes, $palabra_clave)
    {
        set_time_limit(500);

        $sistema = Sistema::all();
        if (count($sistema)==0)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'Sistema no configurado',
                'open_ai'=>null
            ];
        }
        $token = $sistema[0]->key_1;

        $bot = Bot::find($bot_id);
        if (!$bot)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'No existe el bot con id '.$bot_id,
                'open_ai'=>null
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
                'open_ai'=>null
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
            "model" => static::$model_openai,
            "prompt" => $prompt,
            "temperature" => 0.5,
            "max_tokens" => 2048 
        ); 
   
        $fields = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_openai.static::$path_openai."/completions");
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
                'error'=>'Error al conectar con OpenAi',
                'open_ai'=>$err
            ];
        } else {

            $open_ai_obj = json_decode($response);

            if (property_exists($open_ai_obj, 'id')) {

                $text =  $open_ai_obj->choices[0]->text;

                return [
                    'status'=>200,
                    //'prompt'=>$prompt,
                    'text'=>$text,
                    'open_ai'=>$open_ai_obj
                ];    

            }else{
                return [
                    'status'=>409,
                    'error'=>$open_ai_obj->error->message,
                    'open_ai'=>$open_ai_obj
                ];
            }
          
        }  

    }

    public static function _davinciRespuestaPrompt($prompt)
    {
        set_time_limit(500);

        $sistema = Sistema::all();
        if (count($sistema)==0)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'Sistema no configurado',
                'open_ai'=>null
            ];
        }
        $token = $sistema[0]->key_1;

        //Armando la peticion cURL        
        $fields = array(
            "model" => static::$model_openai,
            "prompt" => $prompt,
            "temperature" => 0.5,
            "max_tokens" => 2048 
        ); 
   
        $fields = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_openai.static::$path_openai."/completions");
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
                'error'=>'Error al conectar con OpenAi',
                'open_ai'=>$err
            ];
        } else {

            $open_ai_obj = json_decode($response);

            if (property_exists($open_ai_obj, 'id')) {

                $text =  $open_ai_obj->choices[0]->text;

                return [
                    'status'=>200,
                    //'prompt'=>$prompt,
                    'text'=>$text,
                    'open_ai'=>$open_ai_obj
                ];    

            }else{
                return [
                    'status'=>409,
                    'error'=>$open_ai_obj->error->message,
                    'open_ai'=>$open_ai_obj
                ];
            }
          
        }  

    }

    public static function _davinciFechaNLP($fecha_nlp)
    {
        set_time_limit(500);

        $sistema = Sistema::all();
        if (count($sistema)==0)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'Sistema no configurado',
                'open_ai'=>null
            ];
        }
        $token = $sistema[0]->key_1;

        $date_hoy = Carbon::now();
        $dia = $date_hoy->dayOfWeek;
        $mes = $date_hoy->month;

        $fecha_hoy = $date_hoy->format('d/m/Y');
        $dia_actual = '';
        $mes_actual = '';

        $date_manana = Carbon::tomorrow();
        $fecha_manana = $date_manana->format('d/m/Y');

        $date_pasado_manana = Carbon::now()->addDays(2);
        $fecha_pasado_manana = $date_pasado_manana->format('d/m/Y');

        if($dia == 1){ //lunes
            $dia_actual = 'Lunes';

            $prox_fechas = 'Próximo Martes: {{prox_martes}}
Próximo Miércoles: {{prox_miercoles}}
Próximo Jueves: {{prox_jueves}}
Próximo Viernes: {{prox_viernes}}
Próximo Sabado: {{prox_sabado}}
Próximo Domingo: {{prox_domingo}}
Próximo Lunes: {{prox_lunes}}';

        }else if($dia == 2){ //martes
            $dia_actual = 'Martes';

            $prox_fechas = 'Próximo Miércoles: {{prox_miercoles}}
Próximo Jueves: {{prox_jueves}}
Próximo Viernes: {{prox_viernes}}
Próximo Sabado: {{prox_sabado}}
Próximo Domingo: {{prox_domingo}}
Próximo Lunes: {{prox_lunes}}
Próximo Martes: {{prox_martes}}';

        }else if($dia == 3){ //miercoles
            $dia_actual = 'Miércoles';

            $prox_fechas = 'Próximo Jueves: {{prox_jueves}}
Próximo Viernes: {{prox_viernes}}
Próximo Sabado: {{prox_sabado}}
Próximo Domingo: {{prox_domingo}}
Próximo Lunes: {{prox_lunes}}
Próximo Martes: {{prox_martes}}
Próximo Miércoles: {{prox_miercoles}}';

        }else if($dia == 4){ //jueves
            $dia_actual = 'Jueves';

            $prox_fechas = 'Próximo Viernes: {{prox_viernes}}
Próximo Sabado: {{prox_sabado}}
Próximo Domingo: {{prox_domingo}}
Próximo Lunes: {{prox_lunes}}
Próximo Martes: {{prox_martes}}
Próximo Miércoles: {{prox_miercoles}}
Próximo Jueves: {{prox_jueves}}';

        }else if($dia == 5){ //viernes
            $dia_actual = 'Viernes';

            $prox_fechas = 'Próximo Sabado: {{prox_sabado}}
Próximo Domingo: {{prox_domingo}}
Próximo Lunes: {{prox_lunes}}
Próximo Martes: {{prox_martes}}
Próximo Miércoles: {{prox_miercoles}}
Próximo Jueves: {{prox_jueves}}
Próximo Viernes: {{prox_viernes}}';

        }else if($dia == 6){ //sabado
            $dia_actual = 'Sábado';

            $prox_fechas = 'Próximo Domingo: {{prox_domingo}}
Próximo Lunes: {{prox_lunes}}
Próximo Martes: {{prox_martes}}
Próximo Miércoles: {{prox_miercoles}}
Próximo Jueves: {{prox_jueves}}
Próximo Viernes: {{prox_viernes}}
Próximo Sabado: {{prox_sabado}}';

        }else if($dia == 0){ //domingo
            $dia_actual = 'Domingo';

            $prox_fechas = 'Próximo Lunes: {{prox_lunes}}
Próximo Martes: {{prox_martes}}
Próximo Miércoles: {{prox_miercoles}}
Próximo Jueves: {{prox_jueves}}
Próximo Viernes: {{prox_viernes}}
Próximo Sabado: {{prox_sabado}}
Próximo Domingo: {{prox_domingo}}';
        }

        if ($mes == 1) { // Enero
            $mes_actual = 'Enero';
        } else if ($mes == 2) { // Febrero
            $mes_actual = 'Febrero';
        } else if ($mes == 3) { // Marzo
            $mes_actual = 'Marzo';
        } else if ($mes == 4) { // Abril
            $mes_actual = 'Abril';
        } else if ($mes == 5) { // Mayo
            $mes_actual = 'Mayo';
        } else if ($mes == 6) { // Junio
            $mes_actual = 'Junio';
        } else if ($mes == 7) { // Julio
            $mes_actual = 'Julio';
        } else if ($mes == 8) { // Agosto
            $mes_actual = 'Agosto';
        } else if ($mes == 9) { // Septiembre
            $mes_actual = 'Septiembre';
        } else if ($mes == 10) { // Octubre
            $mes_actual = 'Octubre';
        } else if ($mes == 11) { // Noviembre
            $mes_actual = 'Noviembre';
        } else if ($mes == 12) { // Diciembre
            $mes_actual = 'Diciembre';
        }

        $date1 = Carbon::now();
        $prox_lunes = $date1->next('Monday')->format('d/m/Y');
        $date2 = Carbon::now();
        $prox_martes = $date2->next('Tuesday')->format('d/m/Y');
        $date3 = Carbon::now();
        $prox_miercoles = $date3->next('Wednesday')->format('d/m/Y');
        $date4 = Carbon::now();
        $prox_jueves = $date4->next('Thursday')->format('d/m/Y');
        $date5 = Carbon::now();
        $prox_viernes = $date5->next('Friday')->format('d/m/Y');
        $date6 = Carbon::now();
        $prox_sabado = $date6->next('Saturday')->format('d/m/Y');
        $date7 = Carbon::now();
        $prox_domingo = $date7->next('Sunday')->format('d/m/Y');

        $prompt = 'Genera un JSON con la siguiente estructura:

{
   "date_ia":""
}

Información de fechas:
- Día de la semana: {{dia_actual}}
- Mes actual: {{mes_actual}}
- Fecha de hoy: {{fecha_hoy}}
- Fecha de mañana: {{fecha_manana}}
- Fecha pasado mañana: {{fecha_pasado_manana}}

Próximas fechas:
{{prox_fechas}}

Instrucciones:
- "date_ia" es la fecha que se indica a continuación: {{fecha_nlp}}. La fecha proporcionada puede venir en cualquier formato, incluyendo palabras como "hoy", "mañana", "en dos días", "el próximo domingo", etc.
- Convierte la fecha a formato "dd/mm/aaaa" (día/mes/año) y genera el JSON resultante.
- Retorna solo el JSON asegurándote de que el campo "date_ia" tenga el formato indicado.';

        $prompt = str_replace("{{dia_actual}}", $dia_actual, $prompt);
        $prompt = str_replace("{{mes_actual}}", $mes_actual, $prompt);
        $prompt = str_replace("{{fecha_hoy}}", $fecha_hoy, $prompt);
        $prompt = str_replace("{{fecha_manana}}", $fecha_manana, $prompt);
        $prompt = str_replace("{{fecha_pasado_manana}}", $fecha_pasado_manana, $prompt);
        $prompt = str_replace("{{prox_fechas}}", $prox_fechas, $prompt);
        $prompt = str_replace("{{prox_lunes}}", $prox_lunes, $prompt);
        $prompt = str_replace("{{prox_martes}}", $prox_martes, $prompt);
        $prompt = str_replace("{{prox_miercoles}}", $prox_miercoles, $prompt);
        $prompt = str_replace("{{prox_jueves}}", $prox_jueves, $prompt);
        $prompt = str_replace("{{prox_viernes}}", $prox_viernes, $prompt);
        $prompt = str_replace("{{prox_sabado}}", $prox_sabado, $prompt);
        $prompt = str_replace("{{prox_domingo}}", $prox_domingo, $prompt);
        $prompt = str_replace("{{fecha_nlp}}", $fecha_nlp, $prompt);
       
        //Armando la peticion cURL        
        $fields = array(
            "model" => static::$model_openai,
            "prompt" => $prompt,
            "temperature" => 0.5,
            "max_tokens" => 2048 
        ); 
   
        $fields = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_openai.static::$path_openai."/completions");
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
                'error'=>'Error al conectar con OpenAi',
                'open_ai'=>$err
            ];
        } else {

            $open_ai_obj = json_decode($response);

            if (property_exists($open_ai_obj, 'id')) {

                $text =  $open_ai_obj->choices[0]->text;

                return [
                    'status'=>200,
                    //'prompt'=>$prompt,
                    'text'=>$text,
                    'open_ai'=>$open_ai_obj
                ];    

            }else{
                return [
                    'status'=>409,
                    'error'=>$open_ai_obj->error->message,
                    'open_ai'=>$open_ai_obj
                ];
            }
          
        }  

    }

    public static function _davinciHoraNLP($hora_nlp)
    {
        set_time_limit(500);

        $sistema = Sistema::all();
        if (count($sistema)==0)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'Sistema no configurado',
                'open_ai'=>null
            ];
        }
        $token = $sistema[0]->key_1;

        $prompt = 'Genera un JSON con la siguiente estructura:

{
   "hora_ia":""
}

- hora_ia es la hora que se indica a continuación: {{hora_nlp}}
- hora_ia debe tener el formato de 12 horas (hh:mm AM) o (hh:mm PM)
- solo genera y retorna el JSON.';

        $prompt = str_replace("{{hora_nlp}}", $hora_nlp, $prompt);
       
        //Armando la peticion cURL        
        $fields = array(
            "model" => static::$model_openai,
            "prompt" => $prompt,
            "temperature" => 0.5,
            "max_tokens" => 2048 
        ); 
   
        $fields = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_openai.static::$path_openai."/completions");
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
                'error'=>'Error al conectar con OpenAi',
                'open_ai'=>$err
            ];
        } else {

            $open_ai_obj = json_decode($response);

            if (property_exists($open_ai_obj, 'id')) {

                $text =  $open_ai_obj->choices[0]->text;

                return [
                    'status'=>200,
                    //'prompt'=>$prompt,
                    'text'=>$text,
                    'open_ai'=>$open_ai_obj
                ];    

            }else{
                return [
                    'status'=>409,
                    'error'=>$open_ai_obj->error->message,
                    'open_ai'=>$open_ai_obj
                ];
            }
          
        }  

    }

    public static function _davinciTestBot($bot_id, $mensajes)
    {
        set_time_limit(500);

        $sistema = Sistema::all();
        if (count($sistema)==0)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'Sistema no configurado',
                'open_ai'=>null
            ];
        }
        $token = $sistema[0]->key_1;

        $bot = Bot::find($bot_id);
        if (!$bot)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'No existe el bot con id '.$bot_id,
                'open_ai'=>null
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

        $prompt = 'Actúa como un Chat Bot de WhatsApp y responde al siguiente mensaje de un cliente: {{mensaje}}';
        
        
        $prompt = str_replace("{{mensaje}}", $text_mensajes, $prompt);

        //Armando la peticion cURL        
        $fields = array(
            "model" => static::$model_openai,
            "prompt" => $prompt,
            "temperature" => 0.5,
            "max_tokens" => 2048 
        ); 
   
        $fields = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_openai.static::$path_openai."/completions");
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
                'error'=>'Error al conectar con OpenAi',
                'open_ai'=>$err
            ];
        } else {

            $open_ai_obj = json_decode($response);

            if (property_exists($open_ai_obj, 'id')) {

                $text =  $open_ai_obj->choices[0]->text;

                return [
                    'status'=>200,
                    //'prompt'=>$prompt,
                    'text'=>$text,
                    'open_ai'=>$open_ai_obj
                ];    

            }else{
                return [
                    'status'=>409,
                    'error'=>$open_ai_obj->error->message,
                    'open_ai'=>$open_ai_obj
                ];
            }
          
        }  

    }

}
