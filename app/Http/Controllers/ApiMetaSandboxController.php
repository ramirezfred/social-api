<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Crypt;

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

use App\Http\Traits\ApiMetaSandboxTrait;
use App\Http\Traits\VpsTrait;

date_default_timezone_set('America/Mexico_City');

class ApiMetaSandboxController extends Controller
{
    use ApiMetaSandboxTrait;
    use VpsTrait;

    //Facebook
    public function userAccesTokenLongLive(Request $request)
    {
        $resp = $this->_userAccesTokenLongLive($request->input('access_token'));
        if ($resp['status'] == 200) {
            return response()->json([
                'meta'=>$resp['meta']
            ], $resp['status']);
        }else{
           return response()->json([
                'error'=>$resp['error'],
                'meta'=>$resp['meta']
            ], $resp['status']); 
        }
    }

    //Facebook
    public function accounts(Request $request)
    {
        $resp = $this->_accounts($request->input('user_id'),$request->input('access_token'));
        if ($resp['status'] == 200) {
            return response()->json([
                'meta'=>$resp['meta']
            ], $resp['status']);
        }else{
           return response()->json([
                'error'=>$resp['error'],
                'meta'=>$resp['meta']
            ], $resp['status']); 
        }
    }

    //Facebook
    public function photos(Request $request)
    {
        $resp = $this->_photos($request->input('page_id'),$request->input('access_token'));
        if ($resp['status'] == 200) {
            return response()->json([
                'meta'=>$resp['meta']
            ], $resp['status']);
        }else{
           return response()->json([
                'error'=>$resp['error'],
                'meta'=>$resp['meta']
            ], $resp['status']); 
        }
    }

    //Facebook
    public function picture(Request $request)
    {
        $resp = $this->_picture($request->input('photo_id'),$request->input('access_token'));
        return $resp;
        // if ($resp['status'] == 200) {
        //     return response()->json([
        //         'meta'=>$resp['meta']
        //     ], $resp['status']);
        // }else{
        //    return response()->json([
        //         'error'=>$resp['error'],
        //         'meta'=>$resp['meta']
        //     ], $resp['status']); 
        // }
    }

    //Facebook
    public function getSubscribedApps(Request $request)
    {
        $obj=SocialNetwork::find($request->input('red_id'));

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe la red con id '.$request->input('red_id')], 404);
        }

        $claveAdicional = config('app.lada_b');
        $cadenaDesencriptada = Crypt::decrypt($obj->access_token, $claveAdicional);
        $cadenaDesencriptada = substr($cadenaDesencriptada, 0, -5);

        $resp = $this->_getSubscribedApps($obj->page_id,$cadenaDesencriptada);

        //$resp = $this->_getSubscribedApps($request->input('page_id'),$request->input('access_token'));
        //return $resp;
        if ($resp['status'] == 200) {
            return response()->json([
                'meta'=>$resp['meta']
            ], $resp['status']);
        }else{
           return response()->json([
                'error'=>$resp['error'],
                'meta'=>$resp['meta']
            ], $resp['status']); 
        }
    }

    //Facebook
    public function postSubscribedApps(Request $request)
    {

        $obj=SocialNetwork::find($request->input('red_id'));

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe la red con id '.$request->input('red_id')], 404);
        }

        $claveAdicional = config('app.lada_b');
        $cadenaDesencriptada = Crypt::decrypt($obj->access_token, $claveAdicional);
        $cadenaDesencriptada = substr($cadenaDesencriptada, 0, -5);

        $resp = $this->_postSubscribedApps($obj->page_id,$cadenaDesencriptada);

        //$resp = $this->_postSubscribedApps($request->input('page_id'),$request->input('access_token'));
        //return $resp;
        if ($resp['status'] == 200) {
            return response()->json([
                'meta'=>$resp['meta']
            ], $resp['status']);
        }else{
           return response()->json([
                'error'=>$resp['error'],
                'meta'=>$resp['meta']
            ], $resp['status']); 
        }
    }

    //Facebook
    public function feed(Request $request)
    {
        $obj=SocialNetwork::find($request->input('red_id'));

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe la red con id '.$request->input('red_id')], 404);
        }

        $claveAdicional = config('app.lada_b');
        $cadenaDesencriptada = Crypt::decrypt($obj->access_token, $claveAdicional);
        $cadenaDesencriptada = substr($cadenaDesencriptada, 0, -5);

        $resp = $this->_feed($obj->page_id,$cadenaDesencriptada);

        if ($resp['status'] == 200) {
            return response()->json([
                'meta'=>$resp['meta']
            ], $resp['status']);
        }else{
           return response()->json([
                'error'=>$resp['error'],
                'meta'=>$resp['meta']
            ], $resp['status']); 
        }
    }

    //Facebook
    public function getComments(Request $request)
    {
        $obj=SocialNetwork::find($request->input('red_id'));

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe la red con id '.$request->input('red_id')], 404);
        }

        $claveAdicional = config('app.lada_b');
        $cadenaDesencriptada = Crypt::decrypt($obj->access_token, $claveAdicional);
        $cadenaDesencriptada = substr($cadenaDesencriptada, 0, -5);

        $resp = $this->_getComments($request->input('post_id'),$cadenaDesencriptada);

        if ($resp['status'] == 200) {
            return response()->json([
                'meta'=>$resp['meta']
            ], $resp['status']);
        }else{
           return response()->json([
                'error'=>$resp['error'],
                'meta'=>$resp['meta']
            ], $resp['status']); 
        }
    }

    //Facebook
    public function postComments(Request $request)
    {
        $obj=SocialNetwork::find($request->input('red_id'));

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe la red con id '.$request->input('red_id')], 404);
        }

        $claveAdicional = config('app.lada_b');
        $cadenaDesencriptada = Crypt::decrypt($obj->access_token, $claveAdicional);
        $cadenaDesencriptada = substr($cadenaDesencriptada, 0, -5);

        $resp = $this->_postComments($request->input('comment_id'),$cadenaDesencriptada,$request->input('message'));

        //return $resp;

        if ($resp['status'] == 200) {
            return response()->json([
                'meta'=>$resp['meta']
            ], $resp['status']);
        }else{
           return response()->json([
                'error'=>$resp['error'],
                'meta'=>$resp['meta']
            ], $resp['status']); 
        }
    }

    //Instagram
    public function meAccounts(Request $request)
    {
        $resp = $this->_meAccounts($request->input('access_token'));
        if ($resp['status'] == 200) {
            return response()->json([
                'meta'=>$resp['meta']
            ], $resp['status']);
        }else{
           return response()->json([
                'error'=>$resp['error'],
                'meta'=>$resp['meta']
            ], $resp['status']); 
        }
    }

    //Instagram
    public function media(Request $request)
    {
        $resp = $this->_media(
            $request->input('page_id'),
            $request->input('message'),
            $request->input('url'),
            $request->input('access_token'));
        if ($resp['status'] == 200) {
            return response()->json([
                'meta'=>$resp['meta']
            ], $resp['status']);
        }else{
           return response()->json([
                'error'=>$resp['error'],
                'meta'=>$resp['meta']
            ], $resp['status']); 
        }
    }

    //Instagram
    public function mediaPublish(Request $request)
    {
        $resp = $this->_mediaPublish(
            $request->input('page_id'),
            $request->input('creation_id'),
            $request->input('access_token'));
        if ($resp['status'] == 200) {
            return response()->json([
                'meta'=>$resp['meta']
            ], $resp['status']);
        }else{
           return response()->json([
                'error'=>$resp['error'],
                'meta'=>$resp['meta']
            ], $resp['status']); 
        }
    }

    //Instagram
    public function postReplies(Request $request)
    {
        $obj=SocialNetwork::find($request->input('red_id'));

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe la red con id '.$request->input('red_id')], 404);
        }

        $claveAdicional = config('app.lada_b');
        $cadenaDesencriptada = Crypt::decrypt($obj->access_token, $claveAdicional);
        $cadenaDesencriptada = substr($cadenaDesencriptada, 0, -5);

        $resp = $this->_postReplies($request->input('comment_id'),$cadenaDesencriptada,$request->input('message'));

        //return $resp;

        if ($resp['status'] == 200) {
            return response()->json([
                'meta'=>$resp['meta']
            ], $resp['status']);
        }else{
           return response()->json([
                'error'=>$resp['error'],
                'meta'=>$resp['meta']
            ], $resp['status']); 
        }
    }

    public function publicarPostsGenerales()
    {
        $posts = SocialPost::select('id', 'texto', 'brand_id')
            ->where('aprobada', 1)
            ->where('publicada', 0)
            ->where('tipo', 1)
            ->with(['marca' => function ($query) {
                $query->select('id', 'nombre')
                    ->with('redes');
            }])
            ->has('imagenes', '>=', 1)
            ->with('imagenes')
            ->get();

        //return response()->json(['posts'=>$posts], 200);

        for($i=0; $i < count($posts); $i++) { 
            
            for ($j=0; $j < count($posts[$i]->marca->redes); $j++) { 

                $claveAdicional = config('app.lada_b');
                $cadenaDesencriptada = Crypt::decrypt($posts[$i]->marca->redes[$j]->access_token, $claveAdicional);
                $cadenaDesencriptada = substr($cadenaDesencriptada, 0, -5);
                
                //facebook
                if($posts[$i]->marca->redes[$j]->tipo == 1){

                    $resp = $this->_newPostPhoto(
                        $posts[$i]->marca->redes[$j]->page_id,
                        $posts[$i]->texto,
                        $posts[$i]->imagenes[0]->url,
                        $cadenaDesencriptada
                    );
                    if ($resp['status'] == 200) {
                        // return response()->json([
                        //     'meta'=>$resp['meta']
                        // ], $resp['status']);

                        $posts[$i]->publicada = 1;
                        $posts[$i]->save();

                        $posts[$i]->imagenes[0]->publicada = 1;
                        $posts[$i]->imagenes[0]->save();

                        
                    }

                }

                //instagram
                if($posts[$i]->marca->redes[$j]->tipo == 2){

                    $resp2 = $this->_media(
                        $posts[$i]->marca->redes[$j]->page_id,
                        $posts[$i]->texto,
                        $posts[$i]->imagenes[0]->url,
                        $cadenaDesencriptada
                    );
                    if ($resp2['status'] == 200) {
                        $resp3 = $this->_mediaPublish(
                            $posts[$i]->marca->redes[$j]->page_id,
                            $resp2['meta']->id,
                            $cadenaDesencriptada
                        );
                        if ($resp3['status'] == 200) {
                            // return response()->json([
                            //     'meta'=>$resp3['meta']
                            // ], $resp3['status']);

                            $posts[$i]->publicada = 1;
                            $posts[$i]->save();

                            $posts[$i]->imagenes[0]->publicada = 1;
                            $posts[$i]->imagenes[0]->save();

                        } 
                    } 

                }

            }

            if($posts[$i]->publicada == 1){
                //Eliminar la imagen
                $cadenas = explode('/',$posts[$i]->imagenes[0]->url);
                $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."posts".DIRECTORY_SEPARATOR;
                $fileName = $cadenas[count($cadenas)-1];
                $archivo_ruta = $destinationPath.$fileName;
                if (file_exists($archivo_ruta)) {
                    unlink($archivo_ruta); // Eliminar la imagen
                }    
            }
        }

        return response()->json(['message'=>'Posts generales publicados.'], 200);

    }

    /*Posts que les corresponde ser 
    publicados segun el horario de la marca*/
    public function publicarPostsNormales()
    {

        set_time_limit(500);

        $date = Carbon::now();
        $dia = $date->dayOfWeek;
        $hora = $date->hour;
        $minutos = $date->minute;

        if($hora == 0){
            $hora = 23;
        }/*else{
            $hora = $hora - 1;
        }*/

        // Crea dos objetos Carbon que representan las horas que deseas comparar
        $hora1 = Carbon::createFromTimeString($hora.':'.$minutos);

        $marcas = SocialBrand::select('id','user_id','nombre','horario')
            ->where('status', 1)
            ->whereHas('user', function ($query) {
                $query->where('tipo', 2)
                    ->where('status', 1);
            })
            ->has('redes', '>=', 1)
            ->get();

        $array_marcas = [];

        $minutos_compare = 4;

        for ($i=0; $i < count($marcas); $i++) { 
            $horario = json_decode($marcas[$i]->horario);

            /*Verificar si esta en horario para publicar*/

            if($dia == 1 && $horario[0]->sel == true ){ //lunes
                $hora2 = Carbon::parse($horario[0]->hora.':'.$horario[0]->minutos);
                if ($hora1->greaterThanOrEqualTo($hora2)) {
                    $diferencia_en_minutos = $hora1->diffInMinutes($hora2);
                    if($diferencia_en_minutos <= $minutos_compare){
                        array_push($array_marcas,$marcas[$i]);
                    }
                }
            }else if($dia == 2 && $horario[1]->sel == true ){ //martes
                $hora2 = Carbon::parse($horario[1]->hora.':'.$horario[1]->minutos);
                if ($hora1->greaterThanOrEqualTo($hora2)) {
                    $diferencia_en_minutos = $hora1->diffInMinutes($hora2);
                    if($diferencia_en_minutos <= $minutos_compare){
                        array_push($array_marcas,$marcas[$i]);
                    }
                }
            }else if($dia == 3 && $horario[2]->sel == true ){ //miercoles
                $hora2 = Carbon::parse($horario[2]->hora.':'.$horario[2]->minutos);
                if ($hora1->greaterThanOrEqualTo($hora2)) {
                    $diferencia_en_minutos = $hora1->diffInMinutes($hora2);
                    if($diferencia_en_minutos <= $minutos_compare){
                        array_push($array_marcas,$marcas[$i]);
                    }
                }
            }else if($dia == 4 && $horario[3]->sel == true ){ //jueves
                $hora2 = Carbon::parse($horario[3]->hora.':'.$horario[3]->minutos);
                if ($hora1->greaterThanOrEqualTo($hora2)) {
                    $diferencia_en_minutos = $hora1->diffInMinutes($hora2);
                    if($diferencia_en_minutos <= $minutos_compare){
                        array_push($array_marcas,$marcas[$i]);
                    }
                }
            }else if($dia == 5 && $horario[4]->sel == true ){ //viernes
                $hora2 = Carbon::parse($horario[4]->hora.':'.$horario[4]->minutos);
                if ($hora1->greaterThanOrEqualTo($hora2)) {
                    $diferencia_en_minutos = $hora1->diffInMinutes($hora2);
                    if($diferencia_en_minutos <= $minutos_compare){
                        array_push($array_marcas,$marcas[$i]);
                    }
                }
            }else if($dia == 6 && $horario[5]->sel == true ){ //sabado
                $hora2 = Carbon::parse($horario[5]->hora.':'.$horario[5]->minutos);
                if ($hora1->greaterThanOrEqualTo($hora2)) {
                    $diferencia_en_minutos = $hora1->diffInMinutes($hora2);
                    if($diferencia_en_minutos <= $minutos_compare){
                        array_push($array_marcas,$marcas[$i]);
                    }
                }
            }else if($dia == 0 && $horario[6]->sel == true ){ //domingo
                $hora2 = Carbon::parse($horario[6]->hora.':'.$horario[6]->minutos);
                if ($hora1->greaterThanOrEqualTo($hora2)) {
                    $diferencia_en_minutos = $hora1->diffInMinutes($hora2);
                    if($diferencia_en_minutos <= $minutos_compare){
                        array_push($array_marcas,$marcas[$i]);
                    }
                }
            }
        }

        for ($m=0; $m < count($array_marcas); $m++) { 
            
            $posts = SocialPost::select('id', 'texto', 'brand_id')
                ->where('brand_id', $array_marcas[$m]->id)
                ->where('aprobada', 1)
                ->where('publicada', 0)
                ->where('tipo', 0)
                ->with(['marca' => function ($query) {
                    $query->select('id')
                        ->with('redes');
                }])
                ->has('imagenes', '>=', 1)
                ->with('imagenes')
                ->take(1)
                ->get();

            
            for($i=0; $i < count($posts); $i++) { 

                $fb = false;
                $ig = false; 

                $bandera_fb = false;
                $bandera_ig = false;

                $message_fb = '';
                $message_ig = '';
            
                for ($j=0; $j < count($posts[$i]->marca->redes); $j++) {

                    $claveAdicional = config('app.lada_b');
                    $cadenaDesencriptada = Crypt::decrypt($posts[$i]->marca->redes[$j]->access_token, $claveAdicional);
                    $cadenaDesencriptada = substr($cadenaDesencriptada, 0, -5);
                    
                    //facebook
                    if($posts[$i]->marca->redes[$j]->tipo == 1){

                        $fb = true;

                        $resp = $this->_newPostPhoto(
                            $posts[$i]->marca->redes[$j]->page_id,
                            $posts[$i]->texto,
                            $posts[$i]->imagenes[0]->url,
                            $cadenaDesencriptada
                        );
                        if ($resp['status'] == 200) {
                            // return response()->json([
                            //     'meta'=>$resp['meta']
                            // ], $resp['status']);

                            $posts[$i]->publicada = 1;
                            $posts[$i]->save();

                            $posts[$i]->imagenes[0]->publicada = 1;
                            $posts[$i]->imagenes[0]->save();

                            $bandera_fb = true;

                        }else{
                            $bandera_fb = false;

                            if(property_exists($resp['meta'], 'error') &&
                                property_exists($resp['meta']->error, 'message')){
                                $message_fb = $resp['meta']->error->message;
                            }
                        }

                    }

                    //instagram
                    if($posts[$i]->marca->redes[$j]->tipo == 2){

                        $ig = true;

                        $resp2 = $this->_media(
                            $posts[$i]->marca->redes[$j]->page_id,
                            $posts[$i]->texto,
                            $posts[$i]->imagenes[0]->url,
                            $cadenaDesencriptada
                        );
                        if ($resp2['status'] == 200) {
                            $resp3 = $this->_mediaPublish(
                                $posts[$i]->marca->redes[$j]->page_id,
                                $resp2['meta']->id,
                                $cadenaDesencriptada
                            );
                            if ($resp3['status'] == 200) {
                                // return response()->json([
                                //     'meta'=>$resp3['meta']
                                // ], $resp3['status']);

                                $posts[$i]->publicada = 1;
                                $posts[$i]->save();

                                $posts[$i]->imagenes[0]->publicada = 1;
                                $posts[$i]->imagenes[0]->save();

                                $bandera_ig = true;

                            }else{
                                $bandera_ig = false;

                                if(property_exists($resp3['meta'], 'error') &&
                                    property_exists($resp3['meta']->error, 'message')){
                                    $message_ig = $resp3['meta']->error->message;
                                }
                            }
                        }else{
                            $bandera_ig = false;

                            if(property_exists($resp2['meta'], 'error') &&
                                property_exists($resp2['meta']->error, 'message')){
                                $message_ig = $resp2['meta']->error->message;
                            }
                        } 
 

                    }

                }

                //si no se publico en alguna de las redes
                if(!$bandera_fb || !$bandera_ig){

                    //control para el emial
                    $email_fb = null;
                    if($fb){
                        if(!$bandera_fb){
                            $email_fb = 'No Publicado en Facebook (error: '.$message_fb.')';
                        }else{
                            $email_fb = 'Publicado en Facebook';
                        }   
                    }else{
                        $email_fb = 'No tiene vinculado Facebook';
                    }
                    

                    $email_ig = null;
                    if($ig){
                        if(!$bandera_ig){
                            $email_ig = 'No Publicado en Instagram (error: '.$message_ig.')';
                        }else{
                            $email_ig = 'Publicado en Instagram';
                        }   
                    }else{
                        $email_ig = 'No tiene vinculado Instagram';
                    }

                    //Enviar Email
                    $details = [
                        'title' => 'Detalle de Post',
                        'body' => 'Post '.$posts[$i]->id.' '.$email_fb.', '.$email_ig.', Marca '.$array_marcas[$m]->nombre
                    ];

                    // \Mail::to('tonii.jaam@gmail.com')->send(new \App\Mail\PostEmail($details));
                    //\Mail::to('ramirez.fred016@gmail.com')->send(new \App\Mail\PostEmail($details));
                }

                if($posts[$i]->publicada == 1){
                    // //Eliminar la imagen
                    // $cadenas = explode('/',$posts[$i]->imagenes[0]->url);
                    // $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."posts".DIRECTORY_SEPARATOR;
                    // $fileName = $cadenas[count($cadenas)-1];
                    // $archivo_ruta = $destinationPath.$fileName;
                    // if (file_exists($archivo_ruta)) {
                    //     unlink($archivo_ruta); // Eliminar la imagen
                    // }   

                    //Eliminar la imagen del vps
                    //$this->_deleteImagen($posts[$i]->imagenes[0]->url); 
                }

                
            }

        }

        return response()->json([
            'dia'=>$dia,
            'hora'=>$hora,
            'minutos'=>$minutos,
            //'marcas'=>$marcas,
            //'array_marcas'=>$array_marcas,
        ], 200);
        
    }

  
}
