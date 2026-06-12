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

use Illuminate\Support\Facades\Cache;

date_default_timezone_set('America/Mexico_City');

trait ApiGoogleAITrait
{
    public static $base_url_googleAI = "https://generativelanguage.googleapis.com";
    public static $path_googleAI = "/v1beta";
    // public static $model_googleAI = "gemini-1.5-flash";
    // public static $model_googleAI = "gemini-2.0-flash";
    // public static $model_googleAI = "gemini-2.5-flash";
    public static $model_googleAI = "gemini-3.1-flash-lite";

    public static function getApiKey()
    {
        return env('GOOGLE_AI_API_KEY');
    }

    public static $cache_ttl_googleAI = "3600s"; //1 hora
    public static $cache_model_googleAI  = "gemini-1.5-flash-001";

    private static function checkGoogleSlidingWindowWithWait($limit = 15)
    {
        $key = 'google_ai_sliding_window';
        $windowSeconds = 60;

        // Micro delay para reducir colisiones
        usleep(100000); //100ms

        $timestamps = Cache::get($key, []);
        $now = time();

        // Filtrar solo los últimos 60 segundos
        $timestamps = array_values(array_filter($timestamps, function ($timestamp) use ($now, $windowSeconds) {
            return ($now - $timestamp) < $windowSeconds;
        }));

        if (count($timestamps) >= $limit) {
            // Calcular el tiempo que falta para liberar la primera petición
            $earliest = $timestamps[0];
            $waitSeconds = $windowSeconds - ($now - $earliest);
            return [
                'allowed' => false,
                'wait_seconds' => $waitSeconds > 0 ? $waitSeconds : 1 // mínimo 1 segundo
            ];
        }

        // Agregar timestamp actual
        $timestamps[] = $now;

        // Guardar con TTL de 60 segundos
        Cache::put($key, $timestamps, $windowSeconds);

        return [
            'allowed' => true,
            'wait_seconds' => 0
        ];
    }

    
    public static function _textosGoogleAI($brand_id)
    {

        $result = self::checkGoogleSlidingWindowWithWait(15);

        if (!$result['allowed']) {
            return [
                'status' => 429,
                'error' => 'Límite de peticiones alcanzado. Intente en '.$result['wait_seconds'].' segundos.',
                'open_ai'=>null
            ];
        }

        set_time_limit(500);  

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
        $fields = [
            'contents' => [
                [
                    'parts' => [
                        [ 'text' => $prompt ]/*,
                        [ 'file_data' => [
                            'mime_type' => 'application/pdf',
                            'file_uri' => $fileUri
                        ]]*/
                    ],
                    // 'role' => 'user'
                ]
            ],
            // 'systemInstruction' => [

            //     'parts' => [
            //         [ 

            //             'text' =>  'Eres una Inteligencia artificial especializada en contabilidad.'
 
            //         ]
            //     ]
            // ]
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseSchema' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'text' => [
                                'type' => 'STRING'
                            ]
                        ]
                    ]
                ]
            ]

        ];
   
        $fields_json = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_googleAI.static::$path_googleAI."/models/".static::$model_googleAI.":generateContent?key=".self::getApiKey());
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
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
                'error'=>'Error al conectar con GoogleIA',
                // 'google_ai'=>$err
                'open_ai'=>$err
            ];
        } else {

            $google_ai_obj = json_decode($response);

            if (property_exists($google_ai_obj, 'candidates')) {

                $response_ai =  $google_ai_obj->candidates[0]->content->parts[0]->text;

                $response_ai = json_decode($response_ai);

                $array_textos_redes = [];

                for ($i=0; $i < count($response_ai); $i++) { 
                    array_push($array_textos_redes, $response_ai[$i]->text);
                }
                
                return [
                    'status'=>200,
                    'prompt'=>$prompt,
                    'response_ai'=>$response_ai,
                    'array_textos_redes'=>$array_textos_redes,
                    // 'google_ai'=>$google_ai_obj
                    'open_ai'=>$google_ai_obj
                ];    

            }else{
                return [
                    'status'=>409,
                    'error'=>$google_ai_obj->error->message,
                    // 'google_ai'=>$google_ai_obj
                    'open_ai'=>$google_ai_obj
                ];
            }
          
        }  

    }

    public static function _palabrasClaveEmpresaGoogleAI($brand_id)
    {
        $result = self::checkGoogleSlidingWindowWithWait(15);

        if (!$result['allowed']) {
            return [
                'status' => 429,
                'error' => 'Límite de peticiones alcanzado. Intente en '.$result['wait_seconds'].' segundos.',
                'open_ai'=>null
            ];
        }

        set_time_limit(500);  

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
        $fields = [
            'contents' => [
                [
                    'parts' => [
                        [ 'text' => $prompt ]/*,
                        [ 'file_data' => [
                            'mime_type' => 'application/pdf',
                            'file_uri' => $fileUri
                        ]]*/
                    ],
                    // 'role' => 'user'
                ]
            ],
            // 'systemInstruction' => [

            //     'parts' => [
            //         [ 

            //             'text' =>  'Eres una Inteligencia artificial especializada en contabilidad.'
 
            //         ]
            //     ]
            // ]
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseSchema' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'text' => [
                                'type' => 'STRING'
                            ]
                        ]
                    ]
                ]
            ]

        ];
   
        $fields_json = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_googleAI.static::$path_googleAI."/models/".static::$model_googleAI.":generateContent?key=".self::getApiKey());
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
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
                'error'=>'Error al conectar con GoogleIA',
                // 'google_ai'=>$err
                'open_ai'=>$err
            ];
        } else {

            $google_ai_obj = json_decode($response);

            if (property_exists($google_ai_obj, 'candidates')) {

                $response_ai =  $google_ai_obj->candidates[0]->content->parts[0]->text;

                $response_ai = json_decode($response_ai);

                $array_textos = [];

                for ($i=0; $i < count($response_ai); $i++) { 
                    array_push($array_textos, $response_ai[$i]->text);
                }

                $array_palabras_clave = [];

                for ($i=0; $i < count($array_textos); $i++) { 
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
                    'response_ai'=>$response_ai,
                    'array_palabras_clave'=>$array_palabras_clave,
                    // 'google_ai'=>$google_ai_obj
                    'open_ai'=>$google_ai_obj
                ];    

            }else{
                return [
                    'status'=>409,
                    'error'=>$google_ai_obj->error->message,
                    // 'google_ai'=>$google_ai_obj
                    'open_ai'=>$google_ai_obj
                ];
            }
          
        }  

    }

    public static function _palabrasClavePostGoogleAI($post_id)
    {
        $result = self::checkGoogleSlidingWindowWithWait(15);

        if (!$result['allowed']) {
            return [
                'status' => 429,
                'error' => 'Límite de peticiones alcanzado. Intente en '.$result['wait_seconds'].' segundos.',
                'open_ai'=>null
            ];
        }

        set_time_limit(500);  

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
        $fields = [
            'contents' => [
                [
                    'parts' => [
                        [ 'text' => $prompt ]/*,
                        [ 'file_data' => [
                            'mime_type' => 'application/pdf',
                            'file_uri' => $fileUri
                        ]]*/
                    ],
                    // 'role' => 'user'
                ]
            ],
            // 'systemInstruction' => [

            //     'parts' => [
            //         [ 

            //             'text' =>  'Eres una Inteligencia artificial especializada en contabilidad.'
 
            //         ]
            //     ]
            // ]
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseSchema' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'text' => [
                                'type' => 'STRING'
                            ]
                        ]
                    ]
                ]
            ]

        ];
   
        $fields_json = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_googleAI.static::$path_googleAI."/models/".static::$model_googleAI.":generateContent?key=".self::getApiKey());
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
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
                'error'=>'Error al conectar con GoogleIA',
                // 'google_ai'=>$err
                'open_ai'=>$err
            ];
        } else {

            $google_ai_obj = json_decode($response);

            if (property_exists($google_ai_obj, 'candidates')) {

                $response_ai =  $google_ai_obj->candidates[0]->content->parts[0]->text;

                $response_ai = json_decode($response_ai);

                $array_textos = [];

                for ($i=0; $i < count($response_ai); $i++) { 
                    array_push($array_textos, $response_ai[$i]->text);
                }

                $array_palabras_clave = [];

                for ($i=0; $i < count($array_textos); $i++) { 
                    if ($array_textos[$i] != "") {
                        $palabra_clave = str_replace("-", "", $array_textos[$i]);
                        $string_sin_espacios = trim($palabra_clave);
                        array_unshift($array_palabras_clave, $string_sin_espacios);
                    }
                }
                
                return [
                    'status'=>200,
                    'prompt'=>$prompt,
                    'response_ai'=>$response_ai,
                    'array_palabras_clave'=>$array_palabras_clave,
                    // 'google_ai'=>$google_ai_obj
                    'open_ai'=>$google_ai_obj
                ];    

            }else{
                return [
                    'status'=>409,
                    'error'=>$google_ai_obj->error->message,
                    // 'google_ai'=>$google_ai_obj
                    'open_ai'=>$google_ai_obj
                ];
            }
          
        }  

    }

    public static function _palabrasClaveEmpresaSinonimosGoogleAI($brand_id)
    {
        $result = self::checkGoogleSlidingWindowWithWait(15);

        if (!$result['allowed']) {
            return [
                'status' => 429,
                'error' => 'Límite de peticiones alcanzado. Intente en '.$result['wait_seconds'].' segundos.',
                'open_ai'=>null
            ];
        }

        set_time_limit(500);  

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

        $prompt = 'Genera una lista de '.$cantidad.' sinónimos de: '.$palabra_clave.'. La lista solo debe contener los sinónimos. No incluyas caracteres especiales.';

        // return [
        //     'status'=>200,
        //     'prompt'=>$prompt
        // ];

        //Armando la peticion cURL        
        $fields = [
            'contents' => [
                [
                    'parts' => [
                        [ 'text' => $prompt ]/*,
                        [ 'file_data' => [
                            'mime_type' => 'application/pdf',
                            'file_uri' => $fileUri
                        ]]*/
                    ],
                    // 'role' => 'user'
                ]
            ],
            // 'systemInstruction' => [

            //     'parts' => [
            //         [ 

            //             'text' =>  'Eres una Inteligencia artificial especializada en contabilidad.'
 
            //         ]
            //     ]
            // ]
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseSchema' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'text' => [
                                'type' => 'STRING'
                            ]
                        ]
                    ]
                ]
            ]

        ];
   
        $fields_json = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_googleAI.static::$path_googleAI."/models/".static::$model_googleAI.":generateContent?key=".self::getApiKey());
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
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
                'error'=>'Error al conectar con GoogleIA',
                // 'google_ai'=>$err
                'open_ai'=>$err
            ];
        } else {

            $google_ai_obj = json_decode($response);

            if (property_exists($google_ai_obj, 'candidates')) {

                $response_ai =  $google_ai_obj->candidates[0]->content->parts[0]->text;

                $response_ai = json_decode($response_ai);

                $array_textos = [];

                for ($i=0; $i < count($response_ai); $i++) { 
                    array_push($array_textos, $response_ai[$i]->text);
                }

                $array_palabras_clave = [];

                for ($i=0; $i < count($array_textos); $i++) { 
                    if ($array_textos[$i] != "") {
                        $palabra_clave = str_replace("-", "", $array_textos[$i]);
                        $string_sin_espacios = trim($palabra_clave);
                        array_unshift($array_palabras_clave, $string_sin_espacios);
                    }
                }
                
                return [
                    'status'=>200,
                    'prompt'=>$prompt,
                    'response_ai'=>$response_ai,
                    'array_palabras_clave'=>$array_palabras_clave,
                    // 'google_ai'=>$google_ai_obj
                    'open_ai'=>$google_ai_obj
                ];    

            }else{
                return [
                    'status'=>409,
                    'error'=>$google_ai_obj->error->message,
                    // 'google_ai'=>$google_ai_obj
                    'open_ai'=>$google_ai_obj
                ];
            }
          
        }  

    }

    
}
