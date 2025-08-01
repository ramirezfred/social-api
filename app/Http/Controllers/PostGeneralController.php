<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

use Illuminate\Support\Facades\Crypt;

use App\Models\User;
use App\Models\SocialBrand;
use App\Models\SocialNetwork;
use App\Models\SocialPost;
use App\Models\SocialImage;
use App\Models\SocialFrame;

//use Hash;
use DB;
//use Validator;
use Exception;

use Carbon\Carbon;

use App\Http\Traits\ApiMetaTrait;
use App\Http\Traits\VpsTrait;

date_default_timezone_set('America/Mexico_City');

class PostGeneralController extends Controller
{
    use ApiMetaTrait;
    use VpsTrait;

    public function indexMarcasToPost()
    {

        /*
        buscar las marcas 
        de clientes activos
        con una o mas redes
        */

        $marcas = SocialBrand::select('id','user_id')
            ->where('status', 1)
            ->whereHas('user', function ($query) {
                $query->where('tipo', 2)
                    ->where('status', 1);
            })
            ->has('redes', '>=', 1)
            //->where('id',23)
            ->get();

        if (count($marcas)==0) {
            return response()->json(['error'=>'No hay marcas activas con redes asociadas.'], 404);
        }

        $array_marcas = [];

        for ($i=0; $i < count($marcas); $i++) {

            $marcos = SocialFrame::
                where('brand_id',$marcas[$i]->id)
                ->take(1)
                ->get();

            if(count($marcos)==1){

                $resul = (object) [
                    'marca_id' => $marcas[$i]->id,
                    'marco_url' => $marcos[0]->url,

                ];
                array_push($array_marcas,$resul);

            }

        }

        return response()->json(['marcas'=>$array_marcas], 200);
    }

    public function publicarPostsGenerales(Request $request)
    {
        set_time_limit(500);

        $posts = json_decode($request->input('posts'));

        $resp = null;
        $resp2 = null;
        $resp3 = null;

        for($i=0; $i < count($posts); $i++) {

            $marca = SocialBrand::select('id', 'nombre')
                ->with('redes')->find($posts[$i]->marca_id);

            if($marca){
                for ($j=0; $j < count($marca->redes); $j++) { 

                    $claveAdicional = config('app.lada_b');
                    $cadenaDesencriptada = Crypt::decrypt($marca->redes[$j]->access_token, $claveAdicional);
                    $cadenaDesencriptada = substr($cadenaDesencriptada, 0, -5);
                
                    //facebook
                    if($marca->redes[$j]->tipo == 1){

                        $resp = $this->_newPostPhoto(
                            $marca->redes[$j]->page_id,
                            $request->input('texto'),
                            $posts[$i]->post_url,
                            $cadenaDesencriptada
                        );
                        if ($resp['status'] == 200) {
                            // return response()->json([
                            //     'meta'=>$resp['meta']
                            // ], $resp['status']);

                        }

                    }

                    //instagram
                    if($marca->redes[$j]->tipo == 2){

                        $resp2 = $this->_media(
                            $marca->redes[$j]->page_id,
                            $request->input('texto'),
                            $posts[$i]->post_url,
                            $cadenaDesencriptada
                        );
                        if ($resp2['status'] == 200) {
                            $resp3 = $this->_mediaPublish(
                                $marca->redes[$j]->page_id,
                                $resp2['meta']->id,
                                $cadenaDesencriptada
                            );
                            if ($resp3['status'] == 200) {
                                // return response()->json([
                                //     'meta'=>$resp3['meta']
                                // ], $resp3['status']);

                            } 
                        } 

                    }

                    //Eliminar la imagen del vps
                    //$this->_deleteImagen($posts[$i]->post_url); 

                }
            }
            
            
        }

        return response()->json([
            'message'=>'Posts generales publicados.',
            'resp' => $resp,
            'resp2' =>$resp2,
            'resp3' =>$resp3,
        ], 200);

    }
}
