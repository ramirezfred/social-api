<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\User;
use App\Models\SocialBrand;
use App\Models\SocialNetwork;
use App\Models\SocialPost;
use App\Models\SocialImage;
use App\Models\Sistema;

//use Hash;
use DB;
//use Validator;
use Exception;


use Carbon\Carbon;

use App\Http\Traits\ApiOpenAiTrait;
use App\Http\Traits\ApiGoogleAITrait;

date_default_timezone_set('America/Mexico_City');

class ApiOpenAiController extends Controller
{
    use ApiOpenAiTrait;
    use ApiGoogleAITrait;

    public function davinciTextos(Request $request)
    {
        // $resp = $this->_davinciTextos($request->input('brand_id'));
        $resp = $this->_textosGoogleAI($request->input('brand_id'));
        if ($resp['status'] == 200) {
            return response()->json([
                'open_ai'=>$resp
            ], $resp['status']);
        }else{
           return response()->json([
                'error'=>$resp['error'],
                'open_ai'=>$resp['open_ai']
            ], $resp['status']); 
        }
    }

    public function davinciEscena(Request $request)
    {
        $resp = $this->_davinciEscena($request->input('brand_id'),$request->input('texto'));
        if ($resp['status'] == 200) {
            return response()->json([
                'open_ai'=>$resp
            ], $resp['status']);
        }else{
           return response()->json([
                'error'=>$resp['error'],
                'open_ai'=>$resp['open_ai']
            ], $resp['status']); 
        }
    }

    public function dalle(Request $request)
    {

        $resp = $this->_dalle($request->input('brand_id'),$request->input('prompt'));
        if ($resp['status'] == 200) {

            return response()->json([
                'open_ai'=>$resp
            ], $resp['status']);

            //$archivo_ruta = $this->storeLinkImagen($resp['open_ai']->data[0]->b64_json);
            //$archivo_ruta = $this->storeLinkImagen2($resp['open_ai']->data[0]->url);
            //$archivo_ruta = $this->storeLinkImagen3($resp['open_ai']->data[0]->url);

            // return response()->json([
            //     'archivo_ruta'=>$archivo_ruta
            // ], 200);

        }else{
           return response()->json([
                'error'=>$resp['error'],
                'open_ai'=>$resp['open_ai']
            ], $resp['status']); 
        } 

    }

    public function generarAllTextos()
    {

        //set_time_limit(500);

        $marcas = [];

        /*
        buscar las marcas activas
        de clientes activos
        con una o mas redes
        sin posts generados
        */

        $marcas1 = SocialBrand::select('id','user_id','nombre','servicios','prompt_textos')
            ->where('status', 1)
            ->where('prompt_textos','<>','')
            ->whereNotNull('prompt_textos')
            ->whereHas('user', function ($query) {
                $query->where('tipo', 2)
                    ->where('status', 1);
            })
            ->has('redes', '>=', 1)
            ->has('posts', 0)
            //->where('id', 3) //Internow
            ->get();

        for ($i=0; $i < count($marcas1); $i++) { 
            array_push($marcas, $marcas1[$i]);
        }

        /*
        buscar las marcas 
        de clientes activos
        con una o mas redes
        con 3 o menos posts por publicar
        */

        $marcas2 = SocialBrand::select('id','user_id','nombre','servicios','prompt_textos')
            ->where('status', 1)
            ->where('prompt_textos','<>','')
            ->whereNotNull('prompt_textos')
            ->whereHas('user', function ($query) {
                $query->where('tipo', 2)
                    ->where('status', 1);
            })
            ->has('redes', '>=', 1)
            ->with(['posts' => function ($query) {
                $query->where('publicada', 0)
                    ->whereNotNull('texto');
             
            }])
            ->get();

        /*juntar sin repetir*/
        for ($i=0; $i < count($marcas2); $i++) { 
            $esta = false;
            for ($j=0; $j < count($marcas); $j++) { 
                if ($marcas2[$i]->id == $marcas[$j]->id) {
                    $esta = true;
                }
            }
            if (!$esta && count($marcas2[$i]->posts) <= 3) {
                array_push($marcas, $marcas2[$i]);
            }
        }

        // return response()->json([
        //     'marcas'=>$marcas,
        //     //'marcas2'=>$marcas2
        // ], 200);

        /*generar 10 posts por cada marca*/
        for ($i=0; $i < count($marcas); $i++) { 

            set_time_limit(500);

            // $resp = $this->_davinciTextos($marcas[$i]->id);
            $resp = $this->_textosGoogleAI($marcas[$i]->id);
            if ($resp['status'] == 200) {

                $textos = $resp['array_textos_redes'];

                for ($j=0; $j < count($textos); $j++) { 

                    $texto = $textos[$j];

                    $nuevoObj=SocialPost::create([
                        'brand_id'=>$marcas[$i]->id,
                        'aprobada'=>0,
                        'publicada'=>0,
                        'texto'=>$texto,
                        'escena'=>null,
                        'tipo'=>0,
                    ]);

                }

            }else{
               return response()->json([
                    'error'=>$resp['error'],
                    'open_ai'=>$resp['open_ai']
                ], $resp['status']); 
            }

        }

        return response()->json([
            'message'=>'Textos generados'
        ], 200);
        
        // return response()->json([
        //     'marcas'=>$marcas,
        //     //'marcas2'=>$marcas2
        // ], 200);

    }

    /*Genera las imagenes a un posts*/
    public function generarImagenes($post_id)
    {

        $sistema = Sistema::all();
        if (count($sistema)==0)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Post no encontrado'], 404);
        }
        if($sistema[0]->dalle == 0){
            return response()->json([
                'message'=>'Dall-E inactiva.',
                'imagenes'=>[]
            ], 200);
        }

        set_time_limit(500);

        $post = SocialPost::with('marca')->with('imagenes')->find($post_id);

        if (!$post)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Post no encontrado'], 404);
        }

        if ($post->aprobada == 1) {
            return response()->json(['error'=>'El post ya está marcado como aprobado.'],409);
        }

        if ($post->publicada == 1) {
            return response()->json(['error'=>'El post ya está marcado como publicado.'],409);
        }

        if ($post->texto == null || $post->texto == '') {
            return response()->json(['error'=>'Texto de Post inválido.'],409);
        }

        //Eliminar las imagenes previas
        for ($i=0; $i < count($post->imagenes); $i++) { 
            DB::table('social_images')
                ->where('id', $post->imagenes[$i]->id)
                ->delete();
        }

        //flujo con escena y parametros
        if($post->marca->bandera_flujo == 0){

            $resp = $this->_davinciEscena($post->brand_id,$post->texto);
            if ($resp['status'] == 200) {

                // $post->escena = $resp['escena'];
                // $post->save();

                /*generar las imagenes para la escena*/
                $resp2 = $this->_dalle($post->brand_id,$resp['escena']);
                if ($resp2['status'] == 200) {

                    return response()->json([
                        'message'=>'Imagenes generadas',
                        'imagenes'=>$resp2['open_ai']->data
                    ], 200);                

                }else{
                   return response()->json([
                        'error'=>$resp2['error'],
                        'open_ai'=>$resp2['open_ai']
                    ], $resp2['status']); 
                } 

                
            }else{
               return response()->json([
                    'error'=>$resp['error'],
                    'open_ai'=>$resp['open_ai']
                ], $resp['status']); 
            }

        }

        //flujo con prompt de imagen por defecto
        if($post->marca->bandera_flujo == 1){

            if($post->marca->prompt_imagen == null || $post->marca->prompt_imagen == ''){
                return response()->json(['error'=>'El prompt para la imagen no está configurado.'],409);
            }

            /*generar las imagenes para el prompt por defecto*/
            $resp2 = $this->_dalle($post->brand_id,$post->marca->prompt_imagen);
            if ($resp2['status'] == 200) {

                return response()->json([
                    'message'=>'Imagenes generadas',
                    'imagenes'=>$resp2['open_ai']->data
                ], 200);                

            }else{
               return response()->json([
                    'error'=>$resp2['error'],
                    'open_ai'=>$resp2['open_ai']
                ], $resp2['status']); 
            } 
        }
       

    }

    public function generarTextosUsuario($usuario_id)
    {

        $marcas = [];

        $marcas1 = SocialBrand::select('id','user_id','nombre','servicios','prompt_textos')
            ->where('user_id',$usuario_id)
            //->where('prompt_textos','<>','')
            //->whereNotNull('prompt_textos')
            ->get();

        if(count($marcas1) == 0){
            return response()->json(['error'=>'El usuario no tiene marcas asociadas.'],409);
        }

        for ($i=0; $i < count($marcas1); $i++) { 
            if($marcas1[$i]->prompt_textos != null && $marcas1[$i]->prompt_textos != ''){
                array_push($marcas, $marcas1[$i]);
            }
        }

        if(count($marcas) == 0){
            return response()->json(['error'=>'Configure el prompt para los textos de la marca.'],409);
        }

        // return response()->json([
        //     'marcas'=>$marcas,
        // ], 200);

        /*generar 10 posts por cada marca*/
        $resp = null;
        $textos = null;
        for ($i=0; $i < count($marcas); $i++) { 

            set_time_limit(500);

            // $resp = $this->_davinciTextos($marcas[$i]->id);
            $resp = $this->_textosGoogleAI($marcas[$i]->id);
            if ($resp['status'] == 200) {

                $textos = $resp['array_textos_redes'];

                for ($j=0; $j < count($textos); $j++) { 

                    $texto = $textos[$j];

                    $nuevoObj=SocialPost::create([
                        'brand_id'=>$marcas[$i]->id,
                        'aprobada'=>0,
                        'publicada'=>0,
                        'texto'=>$texto,
                        'escena'=>null,
                        'tipo'=>0,
                    ]);

                }

            }else{
               return response()->json([
                    'error'=>$resp['error'],
                    'open_ai'=>$resp['open_ai']
                ], $resp['status']); 
            }

        }

        return response()->json([
            'message'=>'Textos generados',
            // 'resp'=>$resp,
            // 'textos'=>$textos,
        ], 200);
        
        // return response()->json([
        //     'marcas'=>$marcas,
        // ], 200);

    }

    public function davinciPalabrasClave(Request $request)
    {
        // $resp = $this->_davinciPalabrasClave($request->input('brand_id'));
        $resp = $this->_palabrasClaveEmpresaGoogleAI($request->input('brand_id'));
        if ($resp['status'] == 200) {
            return response()->json([
                'open_ai'=>$resp
            ], $resp['status']);
        }else{
           return response()->json([
                'error'=>$resp['error'],
                'open_ai'=>$resp['open_ai']
            ], $resp['status']); 
        }
    }


    public function davinciPalabrasClavePost(Request $request)
    {
        // $resp = $this->_davinciPalabrasClavePost($request->input('post_id'));
        $resp = $this->_palabrasClavePostGoogleAI($request->input('post_id'));
        if ($resp['status'] == 200) {
            return response()->json([
                'open_ai'=>$resp
            ], $resp['status']);
        }else{
           return response()->json([
                'error'=>$resp['error'],
                'open_ai'=>$resp['open_ai']
            ], $resp['status']); 
        }
    }

    public function davinciRespuesta(Request $request)
    {
        $resp = $this->_davinciRespuesta($request->input('brand_id'),$request->input('texto'));
        if ($resp['status'] == 200) {
            return response()->json([
                'open_ai'=>$resp
            ], $resp['status']);
        }else{
           return response()->json([
                'error'=>$resp['error'],
                'open_ai'=>$resp['open_ai']
            ], $resp['status']); 
        }
    }

}
