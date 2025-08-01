<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\Crypt;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

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

class PostController extends Controller
{
    use ApiMetaTrait;
    use VpsTrait;

    public function index()
    {
        $objs = SocialPost::select('id', 'texto', 'escena')
            ->with(['imagenes' => function ($query) {
                $query->select('id', 'post_id', 'url');
            }])
            ->get();

        return response()->json(['posts'=>$objs], 200);
    }

    public function indexNoAprobados()
    {
        $objs = SocialPost::select('id', 'texto', 'escena', 'brand_id')
            ->where('aprobada', 0)
            ->with(['marca' => function ($query) {
                $query->select('id', 'nombre');
            }])
            ->get();

        return response()->json(['posts'=>$objs], 200);
    }


    public function aprobarPost(Request $request, $post_id)
    {
        $post = SocialPost::find($post_id);

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

        //Eliminar la imagen previa
        //Aunque no deberia tener ninguna
        DB::table('social_images')
            ->where('id', $post_id)
            ->delete();
        
        // /*Descargar la imagen y generar el url*/
        // $archivo_ruta = $this->storeLinkImagen2($imagen->url);

        // if($archivo_ruta == ''){
        //     return response()->json(['error'=>'Tiempo de imagen expirado.'],409);
        // }

        if (!$request->hasFile('archivo')) {
            return response()->json(['error'=>'Archivo no detectado.'], 422);
        }

        //Guardar la imagen y generar el url
        $array_rutas = $this->storeLinkImagen3($request);

        if(!$array_rutas){
            return response()->json(['error'=>'Error al guardar la imagen.'],409);
        }

        //aprobar post
        $post->aprobada = 1;
        $post->save();

        //crear imagen aprobada
        $nuevaImg=SocialImage::create([
            'post_id'=>$post_id,
            'aprobada'=>1,
            'publicada'=>0,
            'url'=>$array_rutas[0],
            'url_allow_origin'=>$array_rutas[1],
            //'base64'=>$array_imagenes[$k]->b64_json,
        ]);

        //Actualizar el marco a usado
        if($request->input('marco_id') != null){
           DB::table('social_frames')
                ->where('id', $request->input('marco_id'))
                ->update(['usado' => 1]); 
        }
        

        return response()->json([
            'message'=>'Post aprobado con éxito.',
            'post'=>$post,
            'imagen'=>$nuevaImg,
        ], 200);
    }

    public function aprobarPostLink(Request $request, $post_id)
    {
        $post = SocialPost::find($post_id);

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

        //Eliminar la imagen previa
        //Aunque no deberia tener ninguna
        DB::table('social_images')
            ->where('id', $post_id)
            ->delete();

        //aprobar post
        $post->aprobada = 1;
        $post->save();

        //crear imagen aprobada
        $nuevaImg=SocialImage::create([
            'post_id'=>$post_id,
            'aprobada'=>1,
            'publicada'=>0,
            'url'=>$request->input('link_archivo'),
            'url_allow_origin'=>$request->input('link_archivo'),
            //'base64'=>$array_imagenes[$k]->b64_json,
        ]);

        //Actualizar el marco a usado
        if($request->input('marco_id') != null){
           DB::table('social_frames')
                ->where('id', $request->input('marco_id'))
                ->update(['usado' => 1]); 
        }

        //Eliminar la imagen subida por el cliente
        if($request->input('imagen_id') != null){
           $this->destroyBrandImage($request->input('imagen_id'));
        }
        

        return response()->json([
            'message'=>'Post aprobado con éxito.',
            'post'=>$post,
            'imagen'=>$nuevaImg,
        ], 200);
    }


    public function eliminarPost($post_id)
    {

        // return response()->json([
        //     'message'=>'Petición exitosa.',
        // ], 200);

        $post = SocialPost::with('imagenes')->find($post_id);

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

        //Eliminar las imagenes
        for ($i=0; $i < count($post->imagenes); $i++) { 
            DB::table('social_images')
                ->where('id', $post->imagenes[$i]->id)
                ->delete();
        }

        $post->delete();

        return response()->json([
            'message'=>'Post eliminado correctamente.',
        ], 200);
    }

    public function storeLinkImagenTestV1(Request $request)
    {
        try{

            if (!$request->hasFile('archivo')) {
                return response()->json(['error'=>'Archivo no detectado.'], 422);
            }

            set_time_limit(500);
        
            $carpeta = 'images_uploads/posts/';
            //$url_base = 'http://localhost/publicacionesIA/publicacionesIAAPI/public/';
            $url_base = 'https://apisocial.internow.com.mx/';

            $hoy = date("m.d.y.H.i.s");

            $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."posts".DIRECTORY_SEPARATOR;
            $fileName = $hoy.'.jpg';

            $request->file('archivo')->move($destinationPath,$fileName);

            $archivo_ruta = $url_base.$carpeta.$fileName;

            //return $archivo_ruta;
            return response()->json([
                'archivo_ruta'=>$archivo_ruta
            ], 200);

        } catch ( Exception $e ){

            return $e->getMessage();
            //return '';

        }
        
    }

    public function storeLinkImagenTestV2(Request $request)
    {
        try{

            // if (!$request->hasFile('archivo')) {
            //     return response()->json(['error'=>'Archivo no detectado.'], 422);
            // }

            set_time_limit(500);
        
            $carpeta = 'images_uploads/posts/';
            //$url_base = 'http://localhost/publicacionesIA/publicacionesIAAPI/public/';
            $url_base = 'https://apisocial.internow.com.mx/';

            # Decode the Base64 string, making sure that it contains only valid characters
            $bin = base64_decode($request->input('archivo'), true);
            //$bin = base64_decode($request->file('archivo'), true);
            //$imagenBase64 = $request->input('archivo');
            //$bin = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $imagenBase64));

            $hoy = date("m.d.y.H.i.s");

            $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."posts".DIRECTORY_SEPARATOR;
            $fileName = $hoy.'.jpg';

            # Write the PDF contents to a local file
            //file_put_contents('file.pdf', $bin);
            file_put_contents($destinationPath.$fileName, $bin);

            $archivo_ruta = $url_base.$carpeta.$fileName;

            //return $archivo_ruta;
            return response()->json([
                'archivo_ruta'=>$archivo_ruta
            ], 200);

        } catch ( Exception $e ){

            return $e->getMessage();
            //return '';

        }
        
    }

    public function storeLinkImagen3(Request $request)
    {
        try{

            set_time_limit(500);
        
            $carpeta = 'images_uploads/posts/';
            //$url_base = 'http://localhost/publicacionesIA/publicacionesIAAPI/public/';
            $url_base = 'https://apisocial.internow.com.mx/';

            $hoy = date("m.d.y.H.i.s");

            $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."posts".DIRECTORY_SEPARATOR;
            $fileName = $hoy.'.jpg';

            $request->file('archivo')->move($destinationPath,$fileName);

            $archivo_ruta = $url_base.$carpeta.$fileName;

            $array_rutas = [
                $archivo_ruta,
                'https://apisocial.internow.com.mx/api/posts/allow_origin/'.$fileName
            ];

            return $array_rutas;

        } catch ( Exception $e ){

            //return $e->getMessage();
            return null;

        }
        
    }

    public function storeLinkImagen($base64)
    {
        
        $carpeta = 'images_uploads/posts/';
        //$url_base = 'http://localhost/publicacionesIA/publicacionesIAAPI/public/';
        $url_base = 'https://apisocial.internow.com.mx/';

        # Decode the Base64 string, making sure that it contains only valid characters
        $bin = base64_decode($base64, true);

        $hoy = date("m.d.y.H.i.s");

        $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."posts".DIRECTORY_SEPARATOR;
        $fileName = $hoy.'.jpg';

        # Write the PDF contents to a local file
        //file_put_contents('file.pdf', $bin);
        file_put_contents($destinationPath.$fileName, $bin);

        $archivo_ruta = $url_base.$carpeta.$fileName;

        return $archivo_ruta;
        
    }

    public function storeLinkImagen2($url)
    {
        try{

            set_time_limit(500);
            
            $carpeta = 'images_uploads/dalle/';
            //$url_base = 'http://localhost/publicacionesIA/publicacionesIAAPI/public/';
            $url_base = 'https://apisocial.internow.com.mx/';

            $hoy = date("m.d.y.H.i.s");

            $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."dalle".DIRECTORY_SEPARATOR;
            $fileName = $hoy.'.jpg';

            $context = stream_context_create(array(
                "ssl" => array(
                    "verify_peer" => false,
                    "verify_peer_name" => false,
                ),
            ));

            // Obtiene el contenido de la imagen a partir del enlace
            $imagen = file_get_contents($url, false, $context);

            // Guarda la imagen en el disco duro
            file_put_contents($destinationPath.$fileName, $imagen);

            $archivo_ruta = $url_base.$carpeta.$fileName;

            return $archivo_ruta;

        } catch ( Exception $e ){

            //return $e->getMessage();
            return '';

        }
        
    }

    public function dercargarUrl()
    {

        set_time_limit(500);

        //$url = "https://oaidalleapiprodscus.blob.core.windows.net/private/org-OnucWdu3UWZRd3IYjbZBni2R/user-kI9vrmILuJqK3OYowc6XxfAb/img-a1Gr9yJX75DAZi0keX4nv3SM.png?st=2023-03-09T14%3A52%3A15Z&se=2023-03-09T16%3A52%3A15Z&sp=r&sv=2021-08-06&sr=b&rscd=inline&rsct=image/png&skoid=6aaadede-4fb3-4698-a8f6-684d7786b067&sktid=a48cca56-e6da-484e-a814-9c849652bcb3&skt=2023-03-08T21%3A47%3A26Z&ske=2023-03-09T21%3A47%3A26Z&sks=b&skv=2021-08-06&sig=yDO8uziYJ2dpcsUcMkyflqH1EGHXW9mfSpqqcTIQSeQ%3D";

        $url = "https://oaidalleapiprodscus.blob.core.windows.net/private/org-OnucWdu3UWZRd3IYjbZBni2R/user-kI9vrmILuJqK3OYowc6XxfAb/img-DuVPXNZdHo1SvX2CA8Fkx51P.png?st=2023-03-13T00%3A37%3A18Z&se=2023-03-13T02%3A37%3A18Z&sp=r&sv=2021-08-06&sr=b&rscd=inline&rsct=image/png&skoid=6aaadede-4fb3-4698-a8f6-684d7786b067&sktid=a48cca56-e6da-484e-a814-9c849652bcb3&skt=2023-03-12T07%3A51%3A24Z&ske=2023-03-13T07%3A51%3A24Z&sks=b&skv=2021-08-06&sig=U%2B4kU7ua/0dN55l7hmTOAvCszo%2BrCdNvHNKT2PN4Q8Q%3D";

        //$archivo_ruta = $this->storeLinkImagen($resp['open_ai']->data[0]->b64_json);
        $archivo_ruta = $this->storeLinkImagen2($url);

        return response()->json([
            'archivo_ruta'=>$archivo_ruta
        ], 200);

    }

    public function imagenAllowOrigin($imagen)
    {
        // Formar la ruta de la imagen
        $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."posts".DIRECTORY_SEPARATOR;
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

    public function storePostGeneralV1(Request $request)
    {

        set_time_limit(500);

        /*
        buscar las marcas 
        de clientes activos
        con una o mas redes
        */

        $marcas = SocialBrand::select('id','user_id','nombre','servicios')
            ->where('status', 1)
            ->whereHas('user', function ($query) {
                $query->where('tipo', 2)
                    ->where('status', 1);
            })
            ->has('redes', '>=', 1)
            ->get();

        if (count($marcas)==0) {
            return response()->json(['error'=>'No hay marcas activas con redes asociadas.'], 404);
        }

        if (!$request->hasFile('archivo')) {
            return response()->json(['error'=>'Archivo no detectado.'], 422);
        }

        //Guardar la imagen base y obtener el nombre
        $array_rutas = $this->storeLinkImagen4($request);

        if(!$array_rutas){
            return response()->json(['error'=>'Error al guardar la imagen.'],409);
        }
        
        /*generar el post para cada marca*/
        for ($i=0; $i < count($marcas); $i++) { 

            //tratar la imagen de fondo

            // definir el nuevo ancho y alto
            $nuevo_ancho = 512;
            $nuevo_alto = 512;

            $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."post_generales".DIRECTORY_SEPARATOR;
            $fileName = $array_rutas[2];

            $archivo_ruta = $destinationPath.$fileName;

            // Cargar las imágenes que deseas superponer
            //$imagen1 = imagecreatefromjpeg($array_rutas[1]);
            $marcas[$i]->imagen1 = imagecreatefromjpeg($archivo_ruta);

            // redimensionar la imagen
            $marcas[$i]->imagen1_redimensionada = imagescale($marcas[$i]->imagen1, $nuevo_ancho, $nuevo_alto);
            
            $marcos = SocialFrame::
                where('brand_id',$marcas[$i]->id)
                ->take(1)
                ->get();

            if(count($marcos)==1){

                $cadenas = explode('/',$marcos[0]->url);

                $destinationPath2 = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."frames".DIRECTORY_SEPARATOR;
                $fileName2 = $cadenas[count($cadenas)-1];

                $archivo_ruta2 = $destinationPath2.$fileName2;

                // Cargar las imágenes que deseas superponer
                //$imagen2 = imagecreatefrompng($marcos[0]->url_allow_origin);
                $marcos[0]->imagen2 = imagecreatefrompng($archivo_ruta2);

                // redimensionar la imagen
                $marcos[0]->imagen2_redimensionada = imagescale($marcos[0]->imagen2, $nuevo_ancho, $nuevo_alto);

                // Superponer la imagen2 en la imagen1
                imagecopy($marcas[$i]->imagen1_redimensionada, $marcos[0]->imagen2_redimensionada, 0, 0, 0, 0, $nuevo_ancho, $nuevo_alto);

                // Guardar la imagen resultante
                $destinationPathPost = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."posts".DIRECTORY_SEPARATOR;
                $hoy = date("m.d.y.H.i.s");
                $nombrePost = $hoy.'.jpg';
                imagejpeg($marcas[$i]->imagen1_redimensionada, $destinationPathPost.$nombrePost);

                // Liberar la memoria utilizada por las imágenes
                imagedestroy($marcos[0]->imagen2);
                imagedestroy($marcos[0]->imagen2_redimensionada);

                //crear el post aprobado
                $nuevoPost=SocialPost::create([
                    'brand_id'=>$marcas[$i]->id,
                    'aprobada'=>1,
                    'publicada'=>0,
                    'texto'=>$request->input('texto'),
                    'escena'=>null,
                ]);

                //crear imagen aprobada
                $nuevaImg=SocialImage::create([
                    'post_id'=>$nuevoPost->id,
                    'aprobada'=>1,
                    'publicada'=>0,
                    'url'=>'https://apisocial.internow.com.mx/images_uploads/posts/'.$nombrePost,
                    'url_allow_origin'=>'https://apisocial.internow.com.mx/api/posts/allow_origin/'.$nombrePost,
                    //'base64'=>$array_imagenes[$k]->b64_json,
                ]);

            }

            // Liberar la memoria utilizada por las imágenes
            imagedestroy($marcas[$i]->imagen1);
            imagedestroy($marcas[$i]->imagen1_redimensionada);
        }

        

        return response()->json([
            'message'=>count($marcas).' Posts generados.'
        ], 200);
    }

    /*para las bases, fondos de los posts generales*/
    public function storeLinkImagen4(Request $request)
    {
        try{

            set_time_limit(500);
        
            $carpeta = 'images_uploads/post_generales/';
            //$url_base = 'http://localhost/publicacionesIA/publicacionesIAAPI/public/';
            $url_base = 'https://apisocial.internow.com.mx/';

            $hoy = date("m.d.y.H.i.s");

            $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."post_generales".DIRECTORY_SEPARATOR;
            $fileName = md5($hoy).'.jpg';

            $request->file('archivo')->move($destinationPath,$fileName);

            $archivo_ruta = $url_base.$carpeta.$fileName;

            $array_rutas = [
                $archivo_ruta,
                'https://apisocial.internow.com.mx/api/post_generales/allow_origin/'.$fileName,
                $fileName
            ];

            return $array_rutas;

        } catch ( Exception $e ){

            //return $e->getMessage();
            return null;

        }
        
    }

    public function imagenAllowOriginGeneral($imagen)
    {
        // Formar la ruta de la imagen
        $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."post_generales".DIRECTORY_SEPARATOR;
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

    public function storePostGeneral(Request $request)
    {

        set_time_limit(500);

        /*
        buscar las marcas 
        de clientes activos
        con una o mas redes
        */

        $marcas = SocialBrand::select('id','user_id','nombre','servicios')
            ->where('status', 1)
            ->whereHas('user', function ($query) {
                $query->where('tipo', 2)
                    ->where('status', 1);
            })
            ->has('redes', '>=', 1)
            ->get();

        if (count($marcas)==0) {
            return response()->json(['error'=>'No hay marcas activas con redes asociadas.'], 404);
        }

        if (!$request->hasFile('archivo')) {
            return response()->json(['error'=>'Archivo no detectado.'], 422);
        }

        //Guardar la imagen base y obtener el nombre
        $array_rutas = $this->storeLinkImagen4($request);

        if(!$array_rutas){
            return response()->json(['error'=>'Error al guardar la imagen.'],409);
        }

        $contador = 0;
        
        /*generar el post para cada marca*/
        for ($i=0; $i < count($marcas); $i++) {

            try {
                
                //tratar la imagen de fondo
                // definir el nuevo ancho y alto
                $nuevo_ancho = 512;
                $nuevo_alto = 512;

                $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."post_generales".DIRECTORY_SEPARATOR;
                $fileName = $array_rutas[2];

                $archivo_ruta = $destinationPath.$fileName;

                // Cargar la imagen base
                $baseImage = Image::make($archivo_ruta);

                // Cambiar el tamaño de la imagen que se superpondrá para que coincida con el tamaño de la imagen base
                $baseImage->resize($nuevo_ancho, $nuevo_alto); 

            } catch (Exception $e) {
                return response()->json(['error'=>'Su archivo base debe tener extension .jpg'],409);
            }
            
            $marcos = SocialFrame::
                where('brand_id',$marcas[$i]->id)
                ->take(1)
                ->get();

            if(count($marcos)==1){

                $cadenas = explode('/',$marcos[0]->url);

                $destinationPath2 = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."frames".DIRECTORY_SEPARATOR;
                $fileName2 = $cadenas[count($cadenas)-1];

                $archivo_ruta2 = $destinationPath2.$fileName2;

                // Cargar la imagen que queremos superponer
                $overlayImage = Image::make($archivo_ruta2);

                // Obtener el ancho y alto de las imágenes
                // $baseWidth = $baseImage->width();
                // $baseHeight = $baseImage->height();
                // $overlayWidth = $overlayImage->width();
                // $overlayHeight = $overlayImage->height();

                // Cambiar el tamaño de la imagen que se superpondrá para que coincida con el tamaño de la imagen base
                $overlayImage->resize($nuevo_ancho, $nuevo_alto);

                // Superponer la imagen en el centro de la imagen base
                // $posX = ($baseWidth - $overlayWidth) / 2;
                // $posY = ($baseHeight - $overlayHeight) / 2;
                $baseImage->insert($overlayImage, 'center');

                // Guardar la imagen resultante
                $destinationPathPost = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."posts".DIRECTORY_SEPARATOR;
                $hoy = date("m.d.y.H.i.s");
                $nombrePost = $hoy.'.i'.$i.'.jpg';

                // Guardar la imagen resultante
                //Storage::put($destinationPathPost.$nombrePost, $baseImage->stream());
                file_put_contents($destinationPathPost.$nombrePost, $baseImage->stream());
                
                // Liberar memoria
                $baseImage->destroy();
                $overlayImage->destroy();

                //crear el post aprobado
                $nuevoPost=SocialPost::create([
                    'brand_id'=>$marcas[$i]->id,
                    'aprobada'=>1,
                    'publicada'=>0,
                    'texto'=>$request->input('texto'),
                    'escena'=>null,
                    'tipo'=>1,
                ]);

                //crear imagen aprobada
                $nuevaImg=SocialImage::create([
                    'post_id'=>$nuevoPost->id,
                    'aprobada'=>1,
                    'publicada'=>0,
                    'url'=>'https://apisocial.internow.com.mx/images_uploads/posts/'.$nombrePost,
                    'url_allow_origin'=>'https://apisocial.internow.com.mx/api/posts/allow_origin/'.$nombrePost,
                    //'base64'=>$array_imagenes[$k]->b64_json,
                ]);

                $contador = $contador + 1;

            }

        }

        //Eliminar la imagen
        $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."post_generales".DIRECTORY_SEPARATOR;
        $fileName = $array_rutas[2];
        $archivo_ruta = $destinationPath.$fileName;
        //$file_path = public_path('img/imagen.jpg'); // Ruta completa de la imagen
        if (file_exists($archivo_ruta)) {
            unlink($archivo_ruta); // Eliminar la imagen
        }

        return response()->json([
            'message'=>'Posts generados.'
        ], 200);
    }

    public function storePostPersonal(Request $request)
    {

        set_time_limit(500);

        $usuario=User::
            with('marcas.redes')
            ->find($request->input('user_id'));

        if (!$usuario)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe el usuario con id '.$request->input('user_id')], 404);
        }

        if (count($usuario->marcas)==0) {
            return response()->json(['error'=>'No tienes marcas registradas.'], 404);
        }

        $red_face = null;
        $red_ig = null;
        for ($i=0; $i < count($usuario->marcas); $i++) {
            if($red_face == null){
                for ($j=0; $j < count($usuario->marcas[$i]->redes); $j++) { 
                    if($usuario->marcas[$i]->redes[$j]->tipo == 1 || $usuario->marcas[$i]->redes[$j]->tipo == '1'){
                        $red_face = $usuario->marcas[$i]->redes[$j];
                    }
                    if($usuario->marcas[$i]->redes[$j]->tipo == 2 || $usuario->marcas[$i]->redes[$j]->tipo == '2'){
                        $red_ig = $usuario->marcas[$i]->redes[$j];
                    }
                }    
            }
        }

        if($red_face == null && $red_ig == null){
            return response()->json(['error'=>'No tienes ninguna red social asociada.'], 404);
        }

        if (!$request->hasFile('archivo')) {
            return response()->json(['error'=>'Archivo no detectado.'], 422);
        }

        //Guardar la imagen base y obtener el nombre
        $array_rutas = $this->storeLinkImagen4($request);

        if(!$array_rutas){
            return response()->json(['error'=>'Error al guardar la imagen.'],409);
        }

        // return response()->json([
        //     'error'=>'Prueba',
        //     'array_rutas'=>$array_rutas,
        //     'red_face'=>$red_face,

        // ],409);

        $resp = null;
        if($red_face){

            $claveAdicional = config('app.lada_b');
            $cadenaDesencriptada = Crypt::decrypt($red_face->access_token, $claveAdicional);
            $cadenaDesencriptada = substr($cadenaDesencriptada, 0, -5);

            //Publicar en facebook
            $resp = $this->_newPostPhoto(
                $red_face->page_id,
                $request->input('texto'),
                $array_rutas[0],
                $cadenaDesencriptada
            );
            if ($resp['status'] == 200) {
                // return response()->json([
                //     'meta'=>$resp['meta']
                // ], $resp['status']);
            }    
        }

        $resp2 = null;
        $resp3 = null;
        if($red_ig){

            $claveAdicional = config('app.lada_b');
            $cadenaDesencriptada = Crypt::decrypt($red_ig->access_token, $claveAdicional);
            $cadenaDesencriptada = substr($cadenaDesencriptada, 0, -5);
            
            //Publicar en instagram
            $resp2 = $this->_media(
                $red_ig->page_id,
                $request->input('texto'),
                $array_rutas[0],
                //'https://apisocial.internow.com.mx/images_uploads/posts/04.20.23.11.26.37.jpg',
                $cadenaDesencriptada
            );
            if ($resp2['status'] == 200) {
                $resp3 = $this->_mediaPublish(
                    $red_ig->page_id,
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

        // //Eliminar la imagen
        // $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."post_generales".DIRECTORY_SEPARATOR;
        // $fileName = $array_rutas[2];
        // $archivo_ruta = $destinationPath.$fileName;
        // //$file_path = public_path('img/imagen.jpg'); // Ruta completa de la imagen
        // if (file_exists($archivo_ruta)) {
        //     unlink($archivo_ruta); // Eliminar la imagen
        // }

        return response()->json([
            'message'=>'Post publicado.',
            'meta_fb'=>$resp,
            'meta_a_ig'=>$resp2,
            'meta_b_ig'=>$resp3
        ], 200);
    }

    public function storePostPersonalVps(Request $request)
    {

        set_time_limit(500);

        $usuario=User::
            with('marcas.redes')
            ->find($request->input('user_id'));

        if (!$usuario)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe el usuario con id '.$request->input('user_id')], 404);
        }

        if (count($usuario->marcas)==0) {
            return response()->json(['error'=>'No tienes marcas registradas.'], 404);
        }

        $red_face = null;
        $red_ig = null;
        for ($i=0; $i < count($usuario->marcas); $i++) {
            if($red_face == null){
                for ($j=0; $j < count($usuario->marcas[$i]->redes); $j++) { 
                    if($usuario->marcas[$i]->redes[$j]->tipo == 1 || $usuario->marcas[$i]->redes[$j]->tipo == '1'){
                        $red_face = $usuario->marcas[$i]->redes[$j];
                    }
                    if($usuario->marcas[$i]->redes[$j]->tipo == 2 || $usuario->marcas[$i]->redes[$j]->tipo == '2'){
                        $red_ig = $usuario->marcas[$i]->redes[$j];
                    }
                }    
            }
        }

        if($red_face == null && $red_ig == null){
            return response()->json(['error'=>'No tienes ninguna red social asociada.'], 404);
        }

        $resp = null;
        if($red_face){

            $claveAdicional = config('app.lada_b');
            $cadenaDesencriptada = Crypt::decrypt($red_face->access_token, $claveAdicional);
            $cadenaDesencriptada = substr($cadenaDesencriptada, 0, -5);

            //Publicar en facebook
            $resp = $this->_newPostPhoto(
                $red_face->page_id,
                $request->input('texto'),
                $request->input('link_archivo'),
                $cadenaDesencriptada
            );
            if ($resp['status'] == 200) {
                // return response()->json([
                //     'meta'=>$resp['meta']
                // ], $resp['status']);
            }    
        }

        $resp2 = null;
        $resp3 = null;
        if($red_ig){

            $claveAdicional = config('app.lada_b');
            $cadenaDesencriptada = Crypt::decrypt($red_ig->access_token, $claveAdicional);
            $cadenaDesencriptada = substr($cadenaDesencriptada, 0, -5);

            //Publicar en instagram
            $resp2 = $this->_media(
                $red_ig->page_id,
                $request->input('texto'),
                $request->input('link_archivo'),
                $cadenaDesencriptada
            );
            if ($resp2['status'] == 200) {
                $resp3 = $this->_mediaPublish(
                    $red_ig->page_id,
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
        // $this->_deleteImagen($request->input('link_archivo'));

        $cadenas = explode('/',$request->input('link_archivo'));

        if(count($cadenas) > 2){

            $fileName = $cadenas[count($cadenas)-1];
            $carpeta = $cadenas[count($cadenas)-2];

            $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR.$carpeta.DIRECTORY_SEPARATOR.$fileName;

            if (file_exists($destinationPath)) {
                unlink($destinationPath); // Eliminar la imagen
            }

        }

        return response()->json([
            'message'=>'Post publicado.',
            'meta_fb'=>$resp,
            'meta_a_ig'=>$resp2,
            'meta_b_ig'=>$resp3
        ], 200);
    }

    public function destroyBrandImage($id)
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

    public function storeNewLinkImagen(Request $request)
    {

        try{

            set_time_limit(500);

            if (!$request->hasFile('archivo')) {
                return response()->json(['error'=>'Archivo no detectado.'], 422);
            }else if(!$request->input('carpeta')){
                return response()->json(['error'=>'Especifique un directorio de destino.'], 422);
            }
        
            $carpeta = 'images_uploads'.DIRECTORY_SEPARATOR.$request->input('carpeta').DIRECTORY_SEPARATOR;
            $url_base = 'https://apisocial.internow.com.mx/';

            $hoy = date("m.d.y.H.i.s");

            $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR.$request->input('carpeta').DIRECTORY_SEPARATOR;
            //$fileName = md5($hoy).'.jpg';
            $fileName = $hoy.'.jpg';

            $request->file('archivo')->move($destinationPath,$fileName);

            $archivo_ruta = $url_base.$carpeta.$fileName;  

            return response()->json([
                'message'=>'Archivo cargado con éxito.',
                'url'=>$archivo_ruta,
            ], 200);

        } catch ( Exception $e ){

            //return $e->getMessage();
            return response()->json(['error'=>'Error al cargar el archivo.'], 422);

        }
        
    }

   
}
