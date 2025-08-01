<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\User;
use App\Models\SocialBrand;
use App\Models\SocialNetwork;
use App\Models\SocialPost;
use App\Models\SocialImage;
use App\Models\SocialFrame;
use App\Models\BrandImage;

//use Hash;
use DB;
//use Validator;

use Carbon\Carbon;

class BrandImageController extends Controller
{
    public function imagenesMarca($brand_id)
    {
        $objs = BrandImage::where('brand_id',$brand_id)->get();

        return response()->json(['imagenes'=>$objs], 200);
    }

    //imagenes para publicar todas
    public function imagenesMarcaActivas($brand_id)
    {
        $objs = BrandImage::where('aprobada',0)
            ->where('brand_id',$brand_id)
            ->get();

        return response()->json(['imagenes'=>$objs], 200);
    }

    //imagenes para publicar solo 5
    public function imagenesMarcaToPosts($brand_id)
    {
        $objs = BrandImage::where('aprobada',0)
            ->where('brand_id',$brand_id)
            ->take(5)
            ->get();

        return response()->json(['imagenes'=>$objs], 200);
    }

    public function store(Request $request)
    {
        // Primero comprobaremos si estamos recibiendo todos los campos.
        $validator = Validator::make($request->all(),[
            'brand_id'=>'required|integer',
        ]);
        if ($validator->fails()) { 
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 422 Unprocessable Entity – [Entidad improcesable] Utilizada para errores de validación.
            return response()->json(['error'=>'Error de validación',
                'detalle'=>$validator->errors(),
            ],422);
        }

        $aux = SocialBrand::find($request->input('brand_id'));
        if(!$aux){
           // Devolvemos un código 409 Conflict. 
            return response()->json(['error'=>'No existe la marca con id '.$request->input('brand_id')], 409);
        }

        if (!$request->hasFile('archivo')) {
            return response()->json(['error'=>'Archivo no detectado.'], 422);
        }

        //Guardar la imagen y generar el url
        $array_rutas = $this->storeLinkImagen3($request);

        if(!$array_rutas){
            return response()->json(['error'=>'Error al guardar la imagen.'],409);
        }

        if($nuevoObj=BrandImage::create([
            'brand_id'=>$request->input('brand_id'),
            'url'=>$array_rutas[0],
            'url_allow_origin'=>$array_rutas[1],
            'aprobada'=>0,
            'publicada'=>0,
        ])){

            return response()->json(['message'=>'Imagen agregada con éxito.',
             'marco'=>$nuevoObj], 200);
        }else{
            return response()->json(['error'=>'Error al agregar la imagen.'], 500);
        }
    }

    public function storeLinkImagen3(Request $request)
    {
        try{

            set_time_limit(500);
        
            $carpeta = 'images_uploads/brand_images/';
            //$url_base = 'http://localhost/publicacionesIA/publicacionesIAAPI/public/';
            $url_base = 'https://apisocial.internow.com.mx/';

            $hoy = date("m.d.y.H.i.s");

            $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."brand_images".DIRECTORY_SEPARATOR;
            $fileName = $hoy.'.jpg';

            $request->file('archivo')->move($destinationPath,$fileName);

            $archivo_ruta = $url_base.$carpeta.$fileName;

            $array_rutas = [
                $archivo_ruta,
                'https://apisocial.internow.com.mx/api/brand_images/allow_origin/'.$fileName
            ];

            return $array_rutas;

        } catch ( Exception $e ){

            //return $e->getMessage();
            return null;

        }
        
    }

    public function imagenAllowOrigin($imagen)
    {
        // Formar la ruta de la imagen
        $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."brand_images".DIRECTORY_SEPARATOR;
        $archivo_ruta = $destinationPath.$imagen;

        // Establecer el encabezado de acceso de origen cruzado
        header("Access-Control-Allow-Origin: *");

        // Obtener el tipo MIME de la imagen
        $mime_type = mime_content_type($archivo_ruta);

        // Establecer el encabezado de tipo MIME
        header("Content-Type: $mime_type");

        // Enviar los datos de la imagen al navegador
        readfile($archivo_ruta);
    }

    public function destroy($id)
    {

        $imagen = BrandImage::find($id);

        if (!$imagen)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Imagen no encontrada'], 404);
        }

        //Eliminar la imagen
        $cadenas = explode('/',$imagen->url);
        $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."brand_images".DIRECTORY_SEPARATOR;
        $fileName = $cadenas[count($cadenas)-1];
        $archivo_ruta = $destinationPath.$fileName;
        if (file_exists($archivo_ruta)) {
            unlink($archivo_ruta); // Eliminar la imagen
        }

        $imagen->delete();

        return response()->json([
            'message'=>'Imagen eliminada correctamente.',
        ], 200);
    }

    public function aprobar($id)
    {

        $imagen = BrandImage::find($id);

        if (!$imagen)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Imagen no encontrada'], 404);
        }

        $imagen->aprobada = 1;
        $imagen->save();

        return response()->json([
            'imagen'=>$imagen,
            'message'=>'Imagen aprobada correctamente.',
        ], 200);
    }

}
