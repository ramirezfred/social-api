<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Exception;

class UploadImagenController extends Controller
{

    public function store(Request $request)
    {

        try {

            /* return response()->json(['message'=>'PRUEBA',
                 'carpeta'=>$request->input('carpeta'),
                 'url_imagen'=>$request->input('url_imagen'),
                 //'imagen'=>$request->file('imagen'),
                
                ], 200);  */

            if (!$request->hasFile('imagen')) {
                return response()->json(['error'=>'Img no detectada.'], 422);
            }else if(!$request->input('carpeta')){
                return response()->json(['error'=>'Especifique un directorio de destino.'], 422);
            }else if(!$request->input('url_imagen')){
                return response()->json(['error'=>'Especifique una URL base para la imagen.'], 422);
            } 
        
            $hoy = date("m.d.y.H.i.s");
    
            $destinationPath = public_path().'/images_uploads/'.$request->input('carpeta').'/';
            //$destinationPath = public_path().'/../../images_uploads/'.$request->input('carpeta').'/';
            $fileName = $hoy.'.png';
            $request->file('imagen')->move($destinationPath,$fileName);
    
            $imagen = $request->input('url_imagen').$request->input('carpeta').'/'.$fileName;
    
            return response()->json(['message'=>'Imagen cargada con éxito.',
                 'imagen'=>$imagen], 200);

        } catch (Exception $e) {
            //This was the problem
            //http_response_code(400);
            //die($e->getMessage());

            return response()->json(['error'=>'error en el catch.',
                    //'e'=>$e->getMessage()
                ], 400);
        }

        
    }


}
