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
use App\Models\SocialFrameBase;

//use Hash;
use DB;
//use Validator;

use Carbon\Carbon;

class FrameController extends Controller
{
    public function index()
    {
        $objs = SocialFrame::all();

        return response()->json(['marcos'=>$objs], 200);
    }

    public function marcosMarca($brand_id)
    {
        $objs = SocialFrame::where('brand_id',$brand_id)->get();

        return response()->json(['marcos'=>$objs], 200);
    }

    public function marcosMarcaContador($brand_id)
    {
        $count = SocialFrame::where('brand_id',$brand_id)->count();

        return response()->json(['contador'=>$count], 200);
    }

    public function marcosMarcaToPosts($brand_id)
    {
        $objs = SocialFrame::
            select('id','brand_id','orden','usado','url','url_allow_origin')
            ->where('brand_id',$brand_id)
            ->orderBy('orden', 'asc')
            ->get();

        //return response()->json(['objs'=>$objs], 200);

        //separar los que no tienen orden
        $array_sinOrden = [];
        for ($i=0; $i < count($objs); $i++) { 
            if($objs[$i]->orden == null){
                array_push($array_sinOrden,$objs[$i]);
            }
        }

        //separar los que tienen orden
        $array_conOrden = [];
        for ($i=0; $i < count($objs); $i++) { 
            if($objs[$i]->orden != null){
                array_push($array_conOrden,$objs[$i]);
            }
        }

        $marcos = [];
        //meter los que tienen orden
        for ($i=0; $i < count($array_conOrden); $i++) { 
            if($i != 0){
                array_push($marcos,$objs[$i]);
            }
        }

        //meter los que no tienen orden
        for ($i=0; $i < count($array_sinOrden); $i++) { 
            array_push($marcos,$objs[$i]);
        }

        //poner de ultimo la primera posicion de los que tienen orden
        if (count($array_conOrden)>=1) {
            array_push($marcos,$objs[0]);
        }

        //setear de nuevo el orden
        for ($i=0; $i < count($marcos); $i++) { 
            $marcos[$i]->orden = $i + 1;
            $marcos[$i]->save();
        }

        return response()->json(['marcos'=>$marcos], 200);
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

        if($nuevoObj=SocialFrame::create([
            'brand_id'=>$request->input('brand_id'),
            'url'=>$array_rutas[0],
            'url_allow_origin'=>$array_rutas[1],
        ])){

            return response()->json(['message'=>'Marco agregado con éxito.',
             'marco'=>$nuevoObj], 200);
        }else{
            return response()->json(['error'=>'Error al agregar el marco.'], 500);
        }
    }

    public function storeConFrameBase(Request $request)
    {
        // Primero comprobaremos si estamos recibiendo todos los campos.
        $validator = Validator::make($request->all(),[
            'brand_id'=>'required|integer',
            'frame_base_id'=>'required|integer',
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

        $aux2 = SocialFrameBase::find($request->input('frame_base_id'));
        if(!$aux2){
           // Devolvemos un código 409 Conflict. 
            return response()->json(['error'=>'No existe el marco base con id '.$request->input('frame_base_id')], 409);
        }

        if (!$request->hasFile('archivo')) {
            return response()->json(['error'=>'Archivo no detectado.'], 422);
        }

        //Guardar la imagen y generar el url
        $array_rutas = $this->storeLinkImagen3($request);

        if(!$array_rutas){
            return response()->json(['error'=>'Error al guardar la imagen.'],409);
        }

        if($nuevoObj=SocialFrame::create([
            'brand_id'=>$request->input('brand_id'),
            'url'=>$array_rutas[0],
            'url_allow_origin'=>$array_rutas[1],
        ])){

            $aux->frames_base()->attach($aux2->id, [
                'frame_id' => $nuevoObj->id,
            ]);

            return response()->json(['message'=>'Marco agregado con éxito.',
             'marco'=>$nuevoObj], 200);
        }else{
            return response()->json(['error'=>'Error al agregar el marco.'], 500);
        }
    }

    public function storeLinkImagen3(Request $request)
    {
        try{

            set_time_limit(500);
        
            $carpeta = 'images_uploads/frames/';
            //$url_base = 'http://localhost/publicacionesIA/publicacionesIAAPI/public/';
            $url_base = 'https://apisocial.internow.com.mx/';

            $hoy = date("m.d.y.H.i.s");

            $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."frames".DIRECTORY_SEPARATOR;
            $fileName = $hoy.'.png';

            $request->file('archivo')->move($destinationPath,$fileName);

            $archivo_ruta = $url_base.$carpeta.$fileName;

            $array_rutas = [
                $archivo_ruta,
                'https://apisocial.internow.com.mx/api/marcos/allow_origin/'.$fileName
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
        $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."frames".DIRECTORY_SEPARATOR;
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

        $marco = SocialFrame::find($id);

        if (!$marco)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Marco no encontrado'], 404);
        }

        //Eliminar la imagen
        $cadenas = explode('/',$marco->url);
        $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."frames".DIRECTORY_SEPARATOR;
        $fileName = $cadenas[count($cadenas)-1];
        $archivo_ruta = $destinationPath.$fileName;
        if (file_exists($archivo_ruta)) {
            unlink($archivo_ruta); // Eliminar la imagen
        }

        //borrar los usos del marco base
        DB::table('social_brand_frames_base')
            ->where('frame_id', $marco->id)
            ->delete();

        $marco->delete();

        return response()->json([
            'message'=>'Marco eliminado correctamente.',
        ], 200);
    }


}
