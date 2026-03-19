<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\User;
use App\Models\SocialBrand;
use App\Models\SocialNetwork;
use App\Models\SocialPost;

use DB;

use Exception;

use App\Http\Traits\ApiOpenAiTrait;
use App\Http\Traits\ApiGoogleAITrait;

class ApiPexelsController extends Controller
{
    use ApiOpenAiTrait;
    use ApiGoogleAITrait;

    public $base_url = "https://api.pexels.com";
    public $path = "/v1";
    public $token = "kU2LKcICTVmLwxaeVSLl8lxXrs1AxyG0jo2sUBv29kdbuB5SdXI9dWAV"; //Antonio
    //public $type_src = 'large';
    public $type_src = 'portrait';
    //public $type_src = 'landscape';

    public function getPageAleatorea($per_page,$query)
    {

        $query_sin_espacios=str_replace(" ","+",$query);

        $query = "?locale=es-ES&page=1&per_page=1&query=".$query_sin_espacios;
        

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->base_url.$this->path."/search".$query);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: '.$this->token
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //curl_setopt($ch, CURLOPT_POST, TRUE);
        //curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        curl_close($ch);

        $pexels = json_decode($response);

        // Verificar que la respuesta sea JSON válido y un objeto
        if (json_last_error() !== JSON_ERROR_NONE || !is_object($pexels)) {
            // Aquí puedes loguear $response para ver qué devolvió la API
            \Log::error("[PexelsAPI] Respuesta inválida: ".$response);
            return 1;
        }

        if (property_exists($pexels, 'total_results')) {

            if ($pexels->total_results == 0) {
                return 1;
            }else{
                $paginas = $pexels->total_results/$per_page;
                if($paginas < 0){
                    return 1;
                }else{
                    $paginas = intval($paginas);
                    return rand(1,$paginas);
                }
            }

        }else{
            return 1;
        }

    }

    public function getImagenes(Request $request)
    {
        $pagina_aleatorea = $this->getPageAleatorea($request->input('per_page'),$request->input('query'));

        $type_src = $this->type_src;

        $query_sin_espacios=str_replace(" ","+",$request->input('query'));

        //$query = "?locale=es-ES&page=2&per_page=1&query=construccion";
        $query = "?locale=es-ES&page=".$pagina_aleatorea."&per_page=".$request->input('per_page')."&query=".$query_sin_espacios;
        
        //Armando la peticion cURL
        // $fields = array(
        // );
            
        // $fields = json_encode($fields);
        // /* print("\nJSON sent:\n");
        // print($fields); */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->base_url.$this->path."/search".$query);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: '.$this->token
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //curl_setopt($ch, CURLOPT_POST, TRUE);
        //curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        curl_close($ch);

        //return $response;

        //print($response); 
        //dd($response);
        $pexels = json_decode($response);


        if (property_exists($pexels, 'page')) {

            $photos = [];
            for ($i=0; $i < count($pexels->photos); $i++) { 

                $type_src_aux = str_replace("h=1200&w=800", "h=1080&w=1080", $pexels->photos[$i]->src->$type_src);
                
                $resul = (object) [
                    // 'large' => $pexels->photos[$i]->src->large,
                    // 'medium' => $pexels->photos[$i]->src->medium,
                    
                    'src' => $type_src_aux,
                ];

                array_push($photos,$resul);

            }

            $next_page = null;
            if(property_exists($pexels, 'next_page')){
                $next_page = $pexels->next_page;
            }

            $pexels_resul = (object) [
                'page' => $pexels->page,
                'per_page' => $pexels->per_page,
                'query' => $request->input('query'),
                'type_src' => $type_src,
                'photos' => $photos,
                'total_results' => $pexels->total_results,
                'next_page' => $next_page,

            ];

            return response()->json([
                'pexels'=>$pexels_resul,
                //'pexels'=>$pexels,
            ], 200);

        }else{

            return response()->json([
                'error'=>'Error al obtener imagenes de Pexels.',
                'pexels'=>$pexels
            ], 500);
        }

    }

    public function getImagenesMarca($per_page,$brand_id)
    {
        $per_page = 5;

        $marca = SocialBrand::find($brand_id);

        if (!$marca)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe la marca con id '.$brand_id], 404);
        }

        try {
            $array_servicios = json_decode($marca->servicios);
        } catch (Exception $e) {
            $array_servicios = [];
        }

        if (count($array_servicios)==0) {
            return response()->json(['error'=>'La marca no posee servicios.'], 409);
        }

        $servicios = '';
        // for ($i=0; $i < count($array_servicios); $i++) { 
        //     if($i == 0){
        //         $servicios = $array_servicios[$i];
        //     }else if($i > 0 ){
        //         $servicios = $servicios.', '.$array_servicios[$i];
        //     }
        // }

        $numero_aleatorio = rand(0, count($array_servicios)-1);
        $servicios = $array_servicios[$numero_aleatorio];

        $pagina_aleatorea = $this->getPageAleatorea($per_page,$servicios);

        $type_src = $this->type_src;

        $query_sin_espacios=str_replace(" ","+",$servicios);

        //$query = "?locale=es-ES&page=2&per_page=1&query=construccion";
        $query = "?locale=es-ES&page=".$pagina_aleatorea."&per_page=".$per_page."&query=".$query_sin_espacios;
        
        //Armando la peticion cURL
        // $fields = array(
        // );
            
        // $fields = json_encode($fields);
        // /* print("\nJSON sent:\n");
        // print($fields); */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->base_url.$this->path."/search".$query);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: '.$this->token
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //curl_setopt($ch, CURLOPT_POST, TRUE);
        //curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        curl_close($ch);

        //return $response;

        //print($response); 
        //dd($response);
        $pexels = json_decode($response);


        if (property_exists($pexels, 'page')) {

            $photos = [];
            for ($i=0; $i < count($pexels->photos); $i++) { 

                $type_src_aux = str_replace("h=1200&w=800", "h=1080&w=1080", $pexels->photos[$i]->src->$type_src);
                
                $resul = (object) [
                    // 'large' => $pexels->photos[$i]->src->large,
                    // 'medium' => $pexels->photos[$i]->src->medium,
                    
                    'src' => $type_src_aux,
                ];

                array_push($photos,$resul);

            }

            $next_page = null;
            if(property_exists($pexels, 'next_page')){
                $next_page = $pexels->next_page;
            }

            $pexels_resul = (object) [
                'page' => $pexels->page,
                'per_page' => $pexels->per_page,
                'query' => $servicios,
                'type_src' => $type_src,
                'photos' => $photos,
                'total_results' => $pexels->total_results,
                'next_page' => $next_page,

            ];

            return response()->json([
                'pexels'=>$pexels_resul,
                //'pexels'=>$pexels,
            ], 200);

        }else{

            return response()->json([
                'error'=>'Error al obtener imagenes de Pexels.',
                'pexels'=>$pexels
            ], 500);
        }

    }

    public function getImagenesPostV1($per_page,$post_id)
    {

        $post = SocialPost::find($post_id);

        if (!$post)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe el post con id '.$post_id], 404);
        }
        

        $type_src = $this->type_src;
        $per_page = null;
        $array_query_sin_espacios = [];
        $metodo = null;

        // $resp = $this->_davinciPalabrasClavePost($post_id);
        $resp = $this->_palabrasClavePostGoogleAI($post_id);
        if ($resp['status'] == 200) {
            // return response()->json([
            //     'open_ai'=>$resp
            // ], $resp['status']);

            $metodo = 1;

            $per_page = 3;

            $array_palabras_clave = $resp['array_palabras_clave'];

            for ($i=0; $i < count($array_palabras_clave); $i++) { 
                if($i < 1){
                    $query_sin_espacios=str_replace(" ","+",$array_palabras_clave[$i]);

                    array_push($array_query_sin_espacios,$query_sin_espacios);   
                }
                
            }
        }

        // $resp2 = $this->_davinciPalabrasClaveEmpresa($post->brand_id);
        $resp2 = $this->_palabrasClaveEmpresaGoogleAI($post->brand_id);
        if ($resp2['status'] == 200) {
            // return response()->json([
            //     'open_ai'=>$resp2
            // ], $resp2['status']);

            $metodo = 1;

            $per_page = 3;

            $array_palabras_clave = $resp2['array_palabras_clave'];

            for ($i=0; $i < count($array_palabras_clave); $i++) { 
                if($i < 1){
                    $query_sin_espacios=str_replace(" ","+",$array_palabras_clave[$i]);

                    array_push($array_query_sin_espacios,$query_sin_espacios);   
                }
                
            }
        }

        //if(count($array_query_sin_espacios) == 0){
        /*if(true){

            //$metodo = 2;

            $marca = SocialBrand::find($post->brand_id);

            if (!$marca)
            {
                // Devolvemos error codigo http 404
                return response()->json(['error'=>'No existe la marca con id '.$post->brand_id], 404);
            }

            try {
                $array_servicios = json_decode($marca->servicios);
            } catch (Exception $e) {
                $array_servicios = [];
            }

            if (count($array_servicios)==0) {
                return response()->json(['error'=>'La marca no posee servicios.'], 409);
            }

            $servicios = '';

            $numero_aleatorio = rand(0, count($array_servicios)-1);
            $servicios = $array_servicios[$numero_aleatorio];

            //$per_page = 5;
            $per_page = 3;     

            $query_sin_espacios=str_replace(" ","+",$servicios);

            array_push($array_query_sin_espacios,$query_sin_espacios);

        }*/

        $pexels = null;
        $photos = [];

        for ($k=0; $k < count($array_query_sin_espacios); $k++) { 

            $pagina_aleatorea = $this->getPageAleatorea($per_page,$array_query_sin_espacios[$k]); 

            $query = "?locale=es-ES&page=".$pagina_aleatorea."&per_page=".$per_page."&query=".$array_query_sin_espacios[$k];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->base_url.$this->path."/search".$query);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Authorization: '.$this->token
            ));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_HEADER, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

            $response = curl_exec($ch);
            curl_close($ch);

            $pexels = json_decode($response);


            if (property_exists($pexels, 'page')) {

                
                for ($i=0; $i < count($pexels->photos); $i++) { 

                    $type_src_aux = str_replace("h=1200&w=800", "h=1080&w=1080", $pexels->photos[$i]->src->$type_src);
                    
                    $resul = (object) [
                        // 'large' => $pexels->photos[$i]->src->large,
                        // 'medium' => $pexels->photos[$i]->src->medium,
                        
                        'src' => $type_src_aux,
                    ];

                    array_push($photos,$resul);

                }

            }

        }

        if(count($photos) == 0){

            $metodo = 2;

            $marca = SocialBrand::find($post->brand_id);

            if (!$marca)
            {
                // Devolvemos error codigo http 404
                return response()->json(['error'=>'No existe la marca con id '.$post->brand_id], 404);
            }

            try {
                $array_servicios = json_decode($marca->servicios);
            } catch (Exception $e) {
                $array_servicios = [];
            }

            if (count($array_servicios)==0) {
                return response()->json(['error'=>'La marca no posee servicios.'], 409);
            }

            $servicios = '';

            $servicios = $array_servicios[0];

            $per_page = 6;    

            $query_sin_espacios=str_replace(" ","+",$servicios);

            $array_query_sin_espacios = [];

            array_push($array_query_sin_espacios,$query_sin_espacios);

            for ($k=0; $k < count($array_query_sin_espacios); $k++) { 

                $pagina_aleatorea = $this->getPageAleatorea($per_page,$array_query_sin_espacios[$k]); 

                $query = "?locale=es-ES&page=".$pagina_aleatorea."&per_page=".$per_page."&query=".$array_query_sin_espacios[$k];
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $this->base_url.$this->path."/search".$query);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Authorization: '.$this->token
                ));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                curl_setopt($ch, CURLOPT_HEADER, FALSE);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

                $response = curl_exec($ch);
                curl_close($ch);

                $pexels = json_decode($response);


                if (property_exists($pexels, 'page')) {

                    
                    for ($i=0; $i < count($pexels->photos); $i++) { 

                        $type_src_aux = str_replace("h=1200&w=800", "h=1080&w=1080", $pexels->photos[$i]->src->$type_src);
                        
                        $resul = (object) [
                            // 'large' => $pexels->photos[$i]->src->large,
                            // 'medium' => $pexels->photos[$i]->src->medium,
                            
                            'src' => $type_src_aux,
                        ];

                        array_push($photos,$resul);

                    }

                }

            }

        }

        if (count($photos)>0) {
            $pexels_resul = (object) [
                'metodo' => $metodo,
                'type_src' => $type_src,
                'photos' => $photos,

            ];

            return response()->json([
                'pexels'=>$pexels_resul,
                'open_ai'=>$resp,
                'open_ai2'=>$resp2,
                //'pexels'=>$pexels,
            ], 200);
        }else{
            return response()->json([
                'error'=>'Error al obtener imagenes de Pexels.',
                'metodo' => $metodo,
                'open_ai'=>$resp,
                'open_ai2'=>$resp2,
                'pexels'=>$pexels,

            ], 500);
        }

    }

    public function getImagenesQuery($per_page,$query_sin_espacios){

        $type_src = $this->type_src;

        $pexels = null;
        $photos = [];

        $pagina_aleatorea = $this->getPageAleatorea($per_page,$query_sin_espacios); 

        $query = "?locale=es-ES&page=".$pagina_aleatorea."&per_page=".$per_page."&query=".$query_sin_espacios;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->base_url.$this->path."/search".$query);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: '.$this->token
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        curl_close($ch);

        $pexels = json_decode($response);

        // Verificar que la respuesta sea JSON válido y un objeto
        if (json_last_error() !== JSON_ERROR_NONE || !is_object($pexels)) {
            // Aquí puedes loguear $response para ver qué devolvió la API
            \Log::error("[PexelsAPI] Respuesta inválida: ".$response);
            return $photos;
        }

        if (property_exists($pexels, 'page')) {

            for ($i=0; $i < count($pexels->photos); $i++) { 

                $src_obj = $pexels->photos[$i]->src;

                if (isset($src_obj->$type_src)) {
                    $type_src_aux = str_replace("h=1200&w=800", "h=1080&w=1080", $src_obj->$type_src);
                }  else {
                    // fallback si no existe la clave
                    $type_src_aux = $src_obj->original."?h=1080&w=1080"; 
                }

                // $type_src_aux = str_replace("h=1200&w=800", "h=1080&w=1080", $pexels->photos[$i]->src->$type_src);
                
                $resul = (object) [
                    // 'large' => $pexels->photos[$i]->src->large,
                    // 'medium' => $pexels->photos[$i]->src->medium,
                    
                    'src' => $type_src_aux,
                ];

                array_push($photos,$resul);

            }

        }

        return $photos;
        
    }

    public function getImagenesPost($per_page,$post_id)
    {

        $post = SocialPost::find($post_id);

        if (!$post)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe el post con id '.$post_id], 404);
        }

        $marca = SocialBrand::find($post->brand_id);

        if (!$marca)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe la marca con id '.$post->brand_id], 404);
        }
        
        $type_src = $this->type_src;
        $per_page = null;
        $array_query_sin_espacios = [];
        $metodo = null;

        $resp0 = null;
        $resp = null;
        $resp2 = null;

        $pexels = null;
        $photos = [];

        if($marca->pexels_status == 1){

            if ($marca->pexels_frase == '' || $marca->pexels_frase == null)
            {

                // Devolvemos error codigo http 404
                return response()->json(['error'=>'La marca no tiene su palabra clave configurada'], 404);
            }

            // $resp0 = $this->_davinciPalabrasClaveEmpresaSinonimos($post->brand_id);
            $resp0 = $this->_palabrasClaveEmpresaSinonimosGoogleAI($post->brand_id);
            if ($resp0['status'] == 200) {
                // return response()->json([
                //     'open_ai'=>$resp
                // ], $resp['status']);

                $metodo = 0;

                $per_page = 2;

                $array_palabras_clave = $resp0['array_palabras_clave'];

                for ($i=0; $i < count($array_palabras_clave); $i++) { 
                    //if($i < 1){
                        $query_sin_espacios=str_replace(" ","+",$array_palabras_clave[$i]);

                        array_push($array_query_sin_espacios,$query_sin_espacios);   
                    //}
                    
                }
            }

            for ($k=0; $k < count($array_query_sin_espacios); $k++) { 

                $array_photos_aux = $this->getImagenesQuery($per_page,$array_query_sin_espacios[$k]);

                for ($p=0; $p < count($array_photos_aux); $p++) { 
                    array_push($photos,$array_photos_aux[$p]);
                } 
                
            }


        }else{

            // $resp = $this->_davinciPalabrasClavePost($post_id);
            $resp = $this->_palabrasClavePostGoogleAI($post_id);
            if ($resp['status'] == 200) {
                // return response()->json([
                //     'open_ai'=>$resp
                // ], $resp['status']);

                $metodo = 1;

                $per_page = 3;

                $array_palabras_clave = $resp['array_palabras_clave'];

                for ($i=0; $i < count($array_palabras_clave); $i++) { 
                    if($i < 1){
                        $query_sin_espacios=str_replace(" ","+",$array_palabras_clave[$i]);

                        array_push($array_query_sin_espacios,$query_sin_espacios);   
                    }
                    
                }
            }

            // $resp2 = $this->_davinciPalabrasClaveEmpresa($post->brand_id);
            $resp2 = $this->_palabrasClaveEmpresaGoogleAI($post->brand_id);
            if ($resp2['status'] == 200) {
                // return response()->json([
                //     'open_ai'=>$resp2
                // ], $resp2['status']);

                $metodo = 1;

                $per_page = 3;

                $array_palabras_clave = $resp2['array_palabras_clave'];

                for ($i=0; $i < count($array_palabras_clave); $i++) { 
                    if($i < 1){
                        $query_sin_espacios=str_replace(" ","+",$array_palabras_clave[$i]);

                        array_push($array_query_sin_espacios,$query_sin_espacios);   
                    }
                    
                }
            }

            

            for ($k=0; $k < count($array_query_sin_espacios); $k++) { 

                $array_photos_aux = $this->getImagenesQuery($per_page,$array_query_sin_espacios[$k]);

                for ($p=0; $p < count($array_photos_aux); $p++) { 
                    array_push($photos,$array_photos_aux[$p]);
                } 
                
            }

            if(count($photos) == 0){

                $metodo = 2;

                try {
                    $array_servicios = json_decode($marca->servicios);
                } catch (Exception $e) {
                    $array_servicios = [];
                }

                if (count($array_servicios)==0) {
                    return response()->json(['error'=>'La marca no posee servicios.'], 409);
                }

                $servicios = '';

                $servicios = $array_servicios[0];

                $per_page = 6;    

                $query_sin_espacios=str_replace(" ","+",$servicios);

                $array_query_sin_espacios = [];

                array_push($array_query_sin_espacios,$query_sin_espacios);

                for ($k=0; $k < count($array_query_sin_espacios); $k++) { 

                    $array_photos_aux = $this->getImagenesQuery($per_page,$array_query_sin_espacios[$k]);

                    for ($p=0; $p < count($array_photos_aux); $p++) { 
                        array_push($photos,$array_photos_aux[$p]);
                    } 

                }

            }

        }

        if (count($photos)>0) {
            $pexels_resul = (object) [
                'metodo' => $metodo,
                'type_src' => $type_src,
                'photos' => $photos,

            ];

            return response()->json([
                'pexels'=>$pexels_resul,
                'open_ai0'=>$resp0,
                'open_ai'=>$resp,
                'open_ai2'=>$resp2,
                //'pexels'=>$pexels,
            ], 200);
        }else{
            return response()->json([
                'error'=>'Error al obtener imagenes de Pexels.',
                'metodo' => $metodo,
                'open_ai0'=>$resp0,
                'open_ai'=>$resp,
                'open_ai2'=>$resp2,
                'pexels'=>$pexels,

            ], 500);
        }

    }

    public function countResults($query)
    {

        $query_sin_espacios=str_replace(" ","+",$query);

        $query = "?locale=es-ES&page=1&per_page=1&query=".$query_sin_espacios;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->base_url.$this->path."/search".$query);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: '.$this->token
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //curl_setopt($ch, CURLOPT_POST, TRUE);
        //curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        curl_close($ch);

        $pexels = json_decode($response);

        if (property_exists($pexels, 'total_results')) {

            return response()->json([
                'total_results'=>$pexels->total_results,
            ], 200);

        }else{
            return response()->json([
                'total_results'=>0,
            ], 200);
        }

    }

    public function getImagenesMarco(Request $request)
    {
        $pagina_aleatorea = $this->getPageAleatorea($request->input('per_page'),$request->input('query'));

        $type_src = $this->type_src;

        $query_sin_espacios=str_replace(" ","+",$request->input('query'));

        //$query = "?locale=es-ES&page=2&per_page=1&query=construccion";
        $query = "?locale=es-ES&page=".$pagina_aleatorea."&per_page=".$request->input('per_page')."&query=".$query_sin_espacios;
        
        //Armando la peticion cURL
        // $fields = array(
        // );
            
        // $fields = json_encode($fields);
        // /* print("\nJSON sent:\n");
        // print($fields); */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->base_url.$this->path."/search".$query);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: '.$this->token
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //curl_setopt($ch, CURLOPT_POST, TRUE);
        //curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        curl_close($ch);

        //return $response;

        //print($response); 
        //dd($response);
        $pexels = json_decode($response);

        $photos = [];
        $resul1 = (object) [
            'src' => "https://images.pexels.com/photos/7567443/pexels-photo-7567443.jpeg?auto=compress&cs=tinysrgb&fit=crop&h=1080&w=1080",
        ];
        array_push($photos,$resul1);
        $resul2 = (object) [
            'src' => "https://images.pexels.com/photos/10054190/pexels-photo-10054190.jpeg?auto=compress&cs=tinysrgb&fit=crop&h=1080&w=1080",
        ];
        array_push($photos,$resul2);

        if (property_exists($pexels, 'page')) {
            
            for ($i=0; $i < count($pexels->photos); $i++) { 

                $type_src_aux = str_replace("h=1200&w=800", "h=1080&w=1080", $pexels->photos[$i]->src->$type_src);
                
                $resul = (object) [
                    // 'large' => $pexels->photos[$i]->src->large,
                    // 'medium' => $pexels->photos[$i]->src->medium,
                    
                    'src' => $type_src_aux,
                ];

                array_push($photos,$resul);

            }

            $next_page = null;
            if(property_exists($pexels, 'next_page')){
                $next_page = $pexels->next_page;
            }

            $pexels_resul = (object) [
                'page' => $pexels->page,
                'per_page' => $pexels->per_page,
                'query' => $request->input('query'),
                'type_src' => $type_src,
                'photos' => $photos,
                'total_results' => $pexels->total_results,
                'next_page' => $next_page,

            ];

            return response()->json([
                'pexels'=>$pexels_resul,
                //'pexels'=>$pexels,
            ], 200);

        }else{

            // return response()->json([
            //     'error'=>'Error al obtener imagenes de Pexels.',
            //     'pexels'=>$pexels
            // ], 500);

            $pexels_resul = (object) [
                'photos' => $photos,
            ];

            return response()->json([
                'pexels'=>$pexels_resul,
                'pexels_response'=>$pexels,
            ], 200);
        }

    }

}
