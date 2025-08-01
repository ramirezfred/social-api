<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\User;
use App\Models\SocialBrand;
use App\Models\SocialNetwork;
use App\Models\SocialPost;
use App\Models\SocialImage;
use App\Models\SocialFrameBase;

//use Hash;
use DB;
//use Validator;

use Carbon\Carbon;

class FrameBaseController extends Controller
{
    public function index()
    {
        // $objs = SocialFrameBase::
        //     orderBy('id', 'desc')
        //     ->take(4)
        //     ->get();

        $objs = SocialFrameBase::all();

        return response()->json(['marcos'=>$objs], 200);
    }

    public function indexHabilitados($brand_id)
    {

        $marca = SocialBrand::
            select('id','user_id','nombre')
            ->with(['frames_base' => function ($query) {
                $query->select('social_frames_base.id');
            }])
            //->with('frames_base')
            ->find($brand_id);

        if (!$marca)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe la marca con id '.$brand_id], 404);
        }

        $ids = [];
        for ($i=0; $i < count($marca->frames_base); $i++) { 
            array_push($ids,$marca->frames_base[$i]->id);
        }

        $objs = SocialFrameBase::
            whereNotIn('id', $ids)
            //->orderBy('id', 'desc')
            //->take(4)
            ->get();

        return response()->json([
            //'marca'=>$marca,
            'marcos'=>$objs
        ], 200);
    }

    public function store(Request $request)
    {

        if (!$request->hasFile('archivo')) {
            return response()->json(['error'=>'Archivo no detectado.'], 422);
        }

        //Guardar la imagen y generar el url
        $array_rutas = $this->storeLinkImagen3($request);

        if(!$array_rutas){
            return response()->json(['error'=>'Error al guardar la imagen.'],409);
        }

        if($nuevoObj=SocialFrameBase::create([
            'nombre'=>null,
            'url'=>$array_rutas[0],
            'url_allow_origin'=>$array_rutas[1],
            'base64'=>null,
            'img1_tipo'=>$request->input('img1_tipo'),
            'img1_x'=>$request->input('img1_x'),
            'img1_y'=>$request->input('img1_y'),
            'img1_radius'=>$request->input('img1_radius'),
            'img1_width'=>$request->input('img1_width'),
            'img1_height'=>$request->input('img1_height'),
            'img2_tipo'=>$request->input('img2_tipo'),
            'img2_x'=>$request->input('img2_x'),
            'img2_y'=>$request->input('img2_y'),
            'img2_radius'=>$request->input('img2_radius'),
            'img2_width'=>$request->input('img2_width'),
            'img2_height'=>$request->input('img2_height'),
            'text1_px'=>$request->input('text1_px'),
            'text1_font'=>$request->input('text1_font'),
            'text1_x'=>$request->input('text1_x'),
            'text1_y'=>$request->input('text1_y'),
            'text1_aling'=>$request->input('text1_aling'),
            'text1_color'=>$request->input('text1_color'),
            'text2_px'=>$request->input('text2_px'),
            'text2_font'=>$request->input('text2_font'),
            'text2_x'=>$request->input('text2_x'),
            'text2_y'=>$request->input('text2_y'),
            'text2_aling'=>$request->input('text2_aling'),
            'text2_color'=>$request->input('text2_color'),
            'text3_px'=>$request->input('text3_px'),
            'text3_font'=>$request->input('text3_font'),
            'text3_x'=>$request->input('text3_x'),
            'text3_y'=>$request->input('text3_y'),
            'text3_aling'=>$request->input('text3_aling'),
            'text3_color'=>$request->input('text3_color'),
        ])){

            return response()->json(['message'=>'Marco agregado con éxito.',
             'marco'=>$nuevoObj], 200);
        }else{
            return response()->json(['error'=>'Error al agregar el marco.'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $obj=SocialFrameBase::find($id);

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe el marco base con id '.$id], 404);
        }  

        $obj->fill($request->all());

        // Almacenamos en la base de datos el registro.
        if ($obj->save()) {
            return response()->json(['message'=>'Marco base configurado con éxito.',
                'marco'=>$obj], 200);
        }else{
            return response()->json(['error'=>'Error al configurar el marco base.'], 500);
        }
    }

    public function storeLinkImagen3(Request $request)
    {
        try{

            set_time_limit(500);
        
            $carpeta = 'images_uploads/frames_base/';
            //$url_base = 'http://localhost/publicacionesIA/publicacionesIAAPI/public/';
            $url_base = 'https://apisocial.internow.com.mx/';

            $hoy = date("m.d.y.H.i.s");

            $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."frames_base".DIRECTORY_SEPARATOR;
            $fileName = $hoy.'.png';

            $request->file('archivo')->move($destinationPath,$fileName);

            $archivo_ruta = $url_base.$carpeta.$fileName;

            $array_rutas = [
                $archivo_ruta,
                'https://apisocial.internow.com.mx/api/marcos_base/allow_origin/'.$fileName
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
        $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."frames_base".DIRECTORY_SEPARATOR;
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

        $marco = SocialFrameBase::find($id);

        if (!$marco)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Marco no encontrado'], 404);
        }

        //Eliminar la imagen
        $cadenas = explode('/',$marco->url);
        $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."frames_base".DIRECTORY_SEPARATOR;
        $fileName = $cadenas[count($cadenas)-1];
        $archivo_ruta = $destinationPath.$fileName;
        if (file_exists($archivo_ruta)) {
            unlink($archivo_ruta); // Eliminar la imagen
        }

        //borrar los usos del marco base
        DB::table('social_brand_frames_base')
            ->where('frame_base_id', $marco->id)
            ->delete();

        $marco->delete();

        return response()->json([
            'message'=>'Marco eliminado correctamente.',
        ], 200);
    }


}
