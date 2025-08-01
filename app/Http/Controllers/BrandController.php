<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Crypt;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\Auth;
use Exception;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

use App\Models\User;
use App\Models\SocialBrand;
use App\Models\SocialNetwork;
use App\Models\SocialPost;

//use Hash;
use DB;
//use Validator;

use Carbon\Carbon;

use App\Http\Traits\ApiPayPalTrait;

date_default_timezone_set('America/Mexico_City');

class BrandController extends Controller
{
    use ApiPayPalTrait;

    public function indexAll()
    {
        // $objs = SocialBrand::
        //     select('id','nombre','servicios',/*'horario',*/'status')
        //     ->with('redes')
        //     ->get();

        $objs = SocialBrand::
            with('redes')
            ->get();

        for ($i=0; $i < count($objs); $i++) { 
            $objs[$i]->servicios = json_decode($objs[$i]->servicios);
            $objs[$i]->horario = json_decode($objs[$i]->horario);
            $objs[$i]->parametros = json_decode($objs[$i]->parametros);
            $objs[$i]->parametros_imagen = json_decode($objs[$i]->parametros_imagen);
        }

        return response()->json(['marcas'=>$objs], 200);
    }

    public function indexBasic()
    {
        $objs = SocialBrand::
            select('id', 'user_id','nombre','logo_allow_origin',
                'color_a','color_b','color_c','pexels_frase','font')
            ->with(['redes' => function ($query) {
                $query->select('id','brand_id','tipo','page_name','alias')
                    ->orderBy('tipo', 'asc');
            }])
            ->with(['user' => function ($query) {
                $query->select('id','email','telefono');
            }])
            ->get();

        return response()->json(['marcas'=>$objs], 200);
    }

    public function index($user_id)
    {
        $objs = SocialBrand::with('redes')
            ->where('user_id', $user_id)->get();

        for ($i=0; $i < count($objs); $i++) { 
            $objs[$i]->servicios = json_decode($objs[$i]->servicios);
            $objs[$i]->horario = json_decode($objs[$i]->horario);
            $objs[$i]->parametros = json_decode($objs[$i]->parametros);
            $objs[$i]->parametros_imagen = json_decode($objs[$i]->parametros_imagen);

            for ($j=0; $j < count($objs[$i]->redes); $j++) { 
                $objs[$i]->redes[$j]->access_token = null;
                $objs[$i]->redes[$j]->meta_user_id = null;
                $objs[$i]->redes[$j]->page_id = null;
                $objs[$i]->redes[$j]->page_image = null;
                $objs[$i]->redes[$j]->page_name = null;
                $objs[$i]->redes[$j]->password = null;
                $objs[$i]->redes[$j]->user = null;
            }
        }

        return response()->json(['marcas'=>$objs], 200);
    }

    public function store(Request $request)
    {
        // Primero comprobaremos si estamos recibiendo todos los campos.
        $validator = Validator::make($request->all(),[
            'user_id'=>'required|integer',
            'nombre'=>'required|string',
            'servicios'=>'required|string',
            'logo'=>'required|string',
        ]);
        if ($validator->fails()) { 
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 422 Unprocessable Entity – [Entidad improcesable] Utilizada para errores de validación.
            return response()->json(['error'=>'Error de validación',
                'detalle'=>$validator->errors(),
            ],422);
        }

        $aux = User::find($request->input('user_id'));
        if(!$aux){
           // Devolvemos un código 409 Conflict. 
            return response()->json(['error'=>'No existe el user con id '.$request->input('user_id')], 409);
        }

        $aux_2 = SocialBrand::where('nombre', $request->input('nombre'))
            ->where('user_id', $request->input('user_id'))
            ->get();
        if(count($aux_2)!=0){
           // Devolvemos un código 409 Conflict. 
            return response()->json(['error'=>'Ya existe una marca con ese nombre.'], 409);
        }

        $servicios = json_decode($request->input('servicios'));
        if (count($servicios)==0) {
            // Devolvemos un código 409 Conflict. 
            return response()->json(['error'=>'Debe agregar al menos un servicio.'], 409);
        }

        $horario = [
            ['nombre' => 'Lunes', 'hora' => '00', 'minutos' => '00', 'sel' => false],
            ['nombre' => 'Martes', 'hora' => '00', 'minutos' => '00', 'sel' => false],
            ['nombre' => 'Miércoles', 'hora' => '00', 'minutos' => '00', 'sel' => false],
            ['nombre' => 'Jueves', 'hora' => '00', 'minutos' => '00', 'sel' => false],
            ['nombre' => 'Viernes', 'hora' => '00', 'minutos' => '00', 'sel' => false],
            ['nombre' => 'Sábado', 'hora' => '00', 'minutos' => '00', 'sel' => false],
            ['nombre' => 'Domingo', 'hora' => '00', 'minutos' => '00', 'sel' => false]
        ];

        $horario_json = json_encode($horario);

        $user = auth()->user();
        $status = 0;
        /*
            Cuando esta creando el admin,
            la marca la creo en status activa
        */
        // if ($user->tipo == 1) {
        //     $status = 1;
        // }

        //Proxima fecha de cobro
        $date = Carbon::now()->addMonth();
        $pay_next_day = $date->day;
        $pay_next_month = $date->month;
        $pay_next_year = $date->year;

        $logo = $request->input('logo');
        $cadenas = explode('/',$logo);
        $fileName = $cadenas[count($cadenas)-1];
        $logo_allow_origin = 'https://apisocial.internow.com.mx/api/marcas/allow_origin/'.$fileName;

        if($nuevoObj=SocialBrand::create([
            'user_id'=>$request->input('user_id'),
            'nombre'=>$request->input('nombre'),
            'logo'=>$request->input('logo'),
            'logo_allow_origin'=>$logo_allow_origin,
            'servicios'=>$request->input('servicios'),
            //'servicios' => json_encode([]),
            'horario'=>$horario_json,
            'status'=>$status,
            'pay_next_day'=>$pay_next_day,
            'pay_next_month'=>$pay_next_month,
            'pay_next_year'=>$pay_next_year,
            'parametros' => json_encode([]),
            'parametros_imagen' => json_encode([]),
            'bandera_flujo'=>0,
            'color_a'=>'#d6d9da',
            'color_b'=>'#618a9f',
            'color_c'=>'#1a2d4e',
            'font'=>'Arial',

            
        ])){

            $nuevoObj->servicios = json_decode($nuevoObj->servicios);
            $nuevoObj->horario = json_decode($nuevoObj->horario);
            $nuevoObj->parametros = json_decode($nuevoObj->parametros);
            $nuevoObj->parametros_imagen = json_decode($nuevoObj->parametros_imagen);
            $nuevoObj->redes = [];

            //Enviar Email
            $details = [
                'title' => 'Registro de Marca',
                'body' => 'El usuario '.$aux->email.' creó la marca '.$nuevoObj->nombre
            ];

            // \Mail::to('tonii.jaam@gmail.com')->send(new \App\Mail\RegistroMarcaEmail($details));
            //\Mail::to('ramirez.fred016@gmail.com')->send(new \App\Mail\RegistroMarcaEmail($details));

            return response()->json(['marca'=>$nuevoObj, 'message'=>'Marca creada con éxito.'], 200);
        }else{
            return response()->json(['error'=>'Error al crear la marca.'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        // Comprobamos si lo que nos están pasando existe o no.

        $marca = SocialBrand::with('redes')->find($id);

        if (!$marca)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe la marca con id '.$id], 404);
        }

        // Listado de campos recibidos teóricamente.
        $nombre=$request->input('nombre'); 
        $logo=$request->input('logo');
        $servicios=$request->input('servicios');  
        $parametros=$request->input('parametros');
        $parametros_imagen=$request->input('parametros_imagen');

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Actualización parcial de campos.
        if ($nombre != null && $nombre!='')
        {
            $aux = SocialBrand::where('nombre', $request->input('nombre'))
                ->where('id', '<>', $marca->id)
                ->where('user_id', $marca->user_id)
                ->get();

            if(count($aux)!=0){
               // Devolvemos un código 409 Conflict. 
                return response()->json(['error'=>'Ya tienes otra marca con ese nombre.'], 409);
            }

            $marca->nombre = $nombre;
            $bandera=true;
        }

        if ($logo != null && $logo!='')
        {
            $cadenas = explode('/',$logo);
            $fileName = $cadenas[count($cadenas)-1];
            $logo_allow_origin = 'https://apisocial.internow.com.mx/api/marcas/allow_origin/'.$fileName;

            $marca->logo = $request->input('logo');
            $marca->logo_allow_origin = $logo_allow_origin;
            $bandera=true;
        }

        if ($servicios != null && $servicios!='')
        {
            $services = json_decode($request->input('servicios'));
            if (count($services)==0) {
                // Devolvemos un código 409 Conflict. 
                return response()->json(['error'=>'Debe agregar al menos un servicio.'], 409);
            }
        
            $marca->servicios = $servicios;
            $bandera=true;
        }

        if ($parametros != null && $parametros!='')
        {
            $marca->parametros = $parametros;
            $bandera=true;
        }

        if ($parametros_imagen != null && $parametros_imagen!='')
        {
            $marca->parametros_imagen = $parametros_imagen;
            $bandera=true;
        }
        
        if ($bandera)
        {
            // Almacenamos en la base de datos el registro.
            if ($marca->save()) {

                $marca->servicios = json_decode($marca->servicios);
                $marca->horario = json_decode($marca->horario);
                $marca->parametros = json_decode($marca->parametros);
                $marca->parametros_imagen = json_decode($marca->parametros_imagen);
                
                return response()->json(['message'=>'Marca editada con éxito.',
                    'marca'=>$marca], 200);
            }else{
                return response()->json(['error'=>'Error al actualizar la marca.'], 500);
            }
            
        }
        else
        {
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 304 Not Modified – [No Modificada] Usado cuando el cacheo de encabezados HTTP está activo
            // Este código 304 no devuelve ningún body, así que si quisiéramos que se mostrara el mensaje usaríamos un código 200 en su lugar.
            return response()->json(['error'=>'No se ha modificado ningún dato a la marca.'],409);
        }
    }

    public function updateAdmin(Request $request, $id)
    {
        // Comprobamos si lo que nos están pasando existe o no.

        $marca = SocialBrand::with('redes')->find($id);

        if (!$marca)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe la marca con id '.$id], 404);
        }

        // Listado de campos recibidos teóricamente.
        $nombre=$request->input('nombre'); 
        $logo=$request->input('logo');
        $servicios=$request->input('servicios');  
        $parametros=$request->input('parametros');
        $parametros_imagen=$request->input('parametros_imagen');
        $bandera_flujo=$request->input('bandera_flujo');
        $prompt_imagen=$request->input('prompt_imagen');
        $pexels_status=$request->input('pexels_status');
        $pexels_frase=$request->input('pexels_frase');
        $comment_status=$request->input('comment_status');
        $comment_auto=$request->input('comment_auto');
        $comment_prompt=$request->input('comment_prompt');
        $comment_footer=$request->input('comment_footer');
        $prompt_textos=$request->input('prompt_textos');

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Actualización parcial de campos.
        if ($nombre != null && $nombre!='')
        {
            $aux = SocialBrand::where('nombre', $request->input('nombre'))
                ->where('id', '<>', $marca->id)
                ->where('user_id', $marca->user_id)
                ->get();

            if(count($aux)!=0){
               // Devolvemos un código 409 Conflict. 
                return response()->json(['error'=>'Ya tienes otra marca con ese nombre.'], 409);
            }

            $marca->nombre = $nombre;
            $bandera=true;
        }

        if ($logo != null && $logo!='')
        {
            $cadenas = explode('/',$logo);
            $fileName = $cadenas[count($cadenas)-1];
            $logo_allow_origin = 'https://apisocial.internow.com.mx/api/marcas/allow_origin/'.$fileName;


            $marca->logo = $request->input('logo');
            $marca->logo_allow_origin = $logo_allow_origin;
            $bandera=true;
        }

        if ($servicios != null && $servicios!='')
        {
            $services = json_decode($request->input('servicios'));
            if (count($services)==0) {
                // Devolvemos un código 409 Conflict. 
                return response()->json(['error'=>'Debe agregar al menos un servicio.'], 409);
            }
        
            $marca->servicios = $servicios;
            $bandera=true;
        }

        if ($parametros != null && $parametros!='')
        {
            $marca->parametros = $parametros;
            $bandera=true;
        }

        if ($parametros_imagen != null && $parametros_imagen!='')
        {
            $marca->parametros_imagen = $parametros_imagen;
            $bandera=true;
        }

        if (($bandera_flujo != null && $bandera_flujo!='') || $bandera_flujo === 0)
        {
            if($bandera_flujo == 1){
                if ($prompt_imagen == null || $prompt_imagen == '') {
                    return response()->json(['error'=>'Para activar este flujo, también debes configurar un Prompt para las imagenes.'], 409);
                }
            }

            $marca->bandera_flujo = $bandera_flujo;
            $marca->prompt_imagen = $prompt_imagen;
            $bandera=true;
        }

        if (($pexels_status != null && $pexels_status!='') || $pexels_status === 0)
        {
            if($pexels_status == 1){
                if ($pexels_frase == null || $pexels_frase == '') {
                    return response()->json(['error'=>'Para activar esta función, también debes configurar una palabra/frase clave.'], 409);
                }
            }

            $marca->pexels_status = $pexels_status;
            $marca->pexels_frase = $pexels_frase;
            $bandera=true;
        }

        if ($pexels_frase != null && $pexels_frase!='')
        {
            $marca->pexels_frase = $pexels_frase;
            $bandera=true;
        }

        if (($comment_status != null && $comment_status!='') || $comment_status === 0)
        {
            if($comment_status == 1){
                if ($comment_prompt == null || $comment_prompt == '') {
                    return response()->json(['error'=>'Para activar esta función, también debes configurar un Prompt.'], 409);
                }

                //verificar la precencia de {{comentario}}
                $comentario = strpos($comment_prompt, '{{comentario}}');
                if ($comentario === false) {
                    return response()->json(['error'=>'El prompt debe contener la subcadena {{comentario}}'], 409);
                }

            }

            if($comment_status == 2){
                if ($comment_auto == null || $comment_auto == '') {
                    return response()->json(['error'=>'Para activar esta función, también debes configurar una respuesta predefinida.'], 409);
                }

            }

            $marca->comment_status = $comment_status;
            $marca->comment_prompt = $comment_prompt;
            $marca->comment_auto = $comment_auto;
            $bandera=true;
        }

        if ($comment_prompt != null && $comment_prompt!='')
        {
            $marca->comment_prompt = $comment_prompt;
            $bandera=true;
        }

        if ($comment_auto != null && $comment_auto!='')
        {
            $marca->comment_auto = $comment_auto;
            $bandera=true;
        }

        if ($request->has('comment_footer'))
        {
            $marca->comment_footer = $comment_footer;
            $bandera=true;
        }

        if ($prompt_textos != null && $prompt_textos!='')
        {

            //verificar la presencia de <cantidad>
            $posicionA = strpos($prompt_textos, '<');
            if ($posicionA === false) {
                return response()->json(['error'=>'La cantidad debe ser numérica y debe ir entre <>'], 409);
            }

            $posicionB = strpos($prompt_textos, '>');
            if ($posicionB === false) {
                return response()->json(['error'=>'La cantidad debe ser numérica y debe ir entre <>'], 409);
            }

            //validar cantidad entera
            $cantidad = substr($prompt_textos,$posicionA+1,$posicionB-($posicionA+1));
            if (!ctype_digit($cantidad)) {
                return response()->json(['error'=>'La cantidad debe ser entera'], 409);
            }

            $cantidad = intval($cantidad);
            if ($cantidad < 1 || $cantidad > 10) {
                return response()->json(['error'=>'La cantidad debe estar entre 1 y 10'], 409);
            }

            //verificar la precencia de {{marca}}
            $marca_aux = strpos($prompt_textos, '{{marca}}');
            if ($marca_aux === false) {
                return response()->json(['error'=>'El prompt debe contener la subcadena {{marca}}'], 409);
            }

            //verificar la precencia de {{servicios}}
            $servicios = strpos($prompt_textos, '{{servicios}}');
            if ($servicios === false) {
                return response()->json(['error'=>'El prompt debe contener la subcadena {{servicios}}'], 409);
            }


            $marca->prompt_textos = $prompt_textos;
            $bandera=true;
        }
        
        if ($bandera)
        {
            // Almacenamos en la base de datos el registro.
            if ($marca->save()) {

                $marca->servicios = json_decode($marca->servicios);
                $marca->horario = json_decode($marca->horario);
                $marca->parametros = json_decode($marca->parametros);
                $marca->parametros_imagen = json_decode($marca->parametros_imagen);
                
                return response()->json(['message'=>'Marca editada con éxito.',
                    'marca'=>$marca], 200);
            }else{
                return response()->json(['error'=>'Error al actualizar la marca.'], 500);
            }
            
        }
        else
        {
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 304 Not Modified – [No Modificada] Usado cuando el cacheo de encabezados HTTP está activo
            // Este código 304 no devuelve ningún body, así que si quisiéramos que se mostrara el mensaje usaríamos un código 200 en su lugar.
            return response()->json(['error'=>'No se ha modificado ningún dato a la marca.'],409);
        }
    }

    public function getServices($brand_id)
    {
        $brand = SocialBrand::find($brand_id);

        if (!$brand)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe la marca con id '.$brand_id], 404);
        }

        $servicios = [];

        if ($brand->servicios) {
            $servicios = json_decode($brand->servicios);
        }

        return response()->json(['servicios'=>$servicios], 200);
    }

    public function updateServices(Request $request, $brand_id)
    {
        // Comprobamos si lo que nos están pasando existe o no.

        $brand = SocialBrand::find($brand_id);

        if (!$brand)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe la marca con id '.$brand_id], 404);
        }

        // Listado de campos recibidos teóricamente.
        $servicios=$request->input('servicios');  

        $services = json_decode($request->input('servicios'));
        if (count($services)==0) {
            // Devolvemos un código 409 Conflict. 
            return response()->json(['error'=>'Debe agregar al menos un servicio.'], 409);
        }

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Actualización parcial de campos de usuario.
        if ($servicios != null && $servicios!='')
        {
            $brand->servicios = $servicios;
            $bandera=true;
        }

        if ($bandera)
        {       
            // Almacenamos en la base de datos el registro.
            if ($brand->save()) {

                return response()->json(['message'=>'Servicios editados con éxito.',
                    'servicios'=>$services], 200);
            }else{
                return response()->json(['error'=>'Error al actualizar los servicios.'], 500);
            }
            
        }
        else
        {
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 304 Not Modified – [No Modificada] Usado cuando el cacheo de encabezados HTTP está activo
            // Este código 304 no devuelve ningún body, así que si quisiéramos que se mostrara el mensaje usaríamos un código 200 en su lugar.
            return response()->json(['error'=>'No se ha modificado ningún dato a los servicios.'],409);
        }
    }

    public function getHorario($brand_id)
    {
        $obj = SocialBrand::find($brand_id);

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Red no encontrada'], 404);
        }

        $horario = json_decode($obj->horario);

        return response()->json(['horario'=>$horario], 200);
    }

    public function updateHorario(Request $request, $brand_id)
    {
        // Comprobamos si lo que nos están pasando existe o no.

        $obj = SocialBrand::find($brand_id);

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe la marca con id '.$brand_id], 404);
        }

        // Listado de campos recibidos teóricamente.
        $horario=$request->input('horario');

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Actualización parcial de campos de usuario.
        if ($horario != null && $horario != '')
        {
            $obj->horario = $horario;
            $bandera=true;
        }

        if ($bandera)
        {
            // Almacenamos en la base de datos el registro.
            if ($obj->save()) {

                $horario_aux = json_decode($horario);

                return response()->json(['message'=>'Horario editado con éxito.',
                    'horario'=>$horario_aux], 200);
            }else{
                return response()->json(['error'=>'Error al actualizar el horario.'], 500);
            }
            
        }
        else
        {
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 304 Not Modified – [No Modificada] Usado cuando el cacheo de encabezados HTTP está activo
            // Este código 304 no devuelve ningún body, así que si quisiéramos que se mostrara el mensaje usaríamos un código 200 en su lugar.
            return response()->json(['error'=>'No se ha modificado ningún dato al horario.'],409);
        }
    }

    public function destroy($id)
    {
        $obj = SocialBrand::
            with(['posts' => function ($query) {
                $query->select('id','brand_id');
             
            }])
            ->find($id);

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe la marca con id '.$id], 404);
        }

        //Eliminar la imagen
        $cadenas = explode('/',$obj->logo);
        $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."marcas".DIRECTORY_SEPARATOR;
        $fileName = $cadenas[count($cadenas)-1];
        $archivo_ruta = $destinationPath.$fileName;
        if (file_exists($archivo_ruta)) {
            unlink($archivo_ruta); // Eliminar la imagen
        }
       
        //Eliminar las redes de la marca
        DB::table('social_networks')
            ->where('brand_id', $id)
            ->delete();

        //Eliminar las imagenes
        for ($i=0; $i < count($obj->posts); $i++) { 
            DB::table('social_images')
                ->where('post_id', $obj->posts[$i]->id)
                ->delete();
        }

        //Eliminar los posts
        DB::table('social_posts')
                ->where('brand_id', $id)
                ->delete();

        //Si ya tenia subs la cancelo
        if($obj->paypal_subscription_id){
            $obj_cancel = $this->_cancelarSubscription($obj->paypal_subscription_id);
        }

        //borrar los usos del marco base
        DB::table('social_brand_frames_base')
            ->where('brand_id', $id)
            ->delete();

        // Eliminamos la marca
        $obj->delete();

        return response()->json(['message'=>'Se ha eliminado correctamente la marca.'], 200);
    }

    public function updateStatus(Request $request, $brand_id)
    {
        // Comprobamos si el usuario que nos están pasando existe o no.
        $marca=SocialBrand::find($brand_id);

        if (!$marca)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Marca no encontrada.'], 404);
        }    
        
        // Listado de campos recibidos teóricamente.
        $status=$request->input('status');

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Actualización parcial de campos.
        if (($status != null && $status!='') || $status === 0)
        {
            $marca->status = $status;
            $bandera=true;
        }
       
        if ($bandera)
        {
            // Almacenamos en la base de datos el registro.
            if ($marca->save()) {
                return response()->json(['message'=>'Marca actualizada.',
                 'marca'=>$marca], 200);
            }else{
                return response()->json(['error'=>'Error al actualizar la marca.'], 500);
            }           
        }
        else
        {
            // Se devuelve un array error con los error encontrados y cabecera HTTP 304 Not Modified – [No Modificada] Usado cuando el cacheo de encabezados HTTP está activo
            // Este código 304 no devuelve ningún body, así que si quisiéramos que se mostrara el mensaje usaríamos un código 200 en su lugar.
            return response()->json(['error'=>'No se ha modificado ningún a la marca.'],500);
        }
    }

    public function show($id)
    {
        // La cadena cifrada que se envió desde Angular
        $cadenaEncriptada = $id;

        $claveAdicional = config('app.lada_a');

        try {
            $cadenaDesencriptada = Crypt::decrypt($cadenaEncriptada, $claveAdicional);
        } catch (Exception $e) {
            return response()->json(['error'=>'Marca no encontrada'], 404);
        }

        $obj = SocialBrand::with('user')
            ->with('redes')
            ->find($cadenaDesencriptada);

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Marca no encontrada'], 404);
        }

        $obj->servicios = json_decode($obj->servicios);
        $obj->horario = json_decode($obj->horario);
        $obj->parametros = json_decode($obj->parametros);
        $obj->parametros_imagen = json_decode($obj->parametros_imagen);

        for ($i=0; $i < count($obj->redes); $i++) { 
            $obj->redes[$i]->access_token = null;
            $obj->redes[$i]->meta_user_id = null;
            $obj->redes[$i]->page_id = null;
            $obj->redes[$i]->page_image = null;
            $obj->redes[$i]->page_name = null;
            $obj->redes[$i]->password = null;
            $obj->redes[$i]->user = null;
        }

        return response()->json(['marca'=>$obj], 200);
    }

    public function show2($id)
    {

        // La cadena cifrada que se envió desde Angular
        $cadenaEncriptada = $id;

        $claveAdicional = config('app.lada_a');

        try {
            $cadenaDesencriptada = Crypt::decrypt($cadenaEncriptada, $claveAdicional);
        } catch (Exception $e) {
            return response()->json(['error'=>'Marca no encontrada'], 404);
        }

        $obj = SocialBrand::with('user')
            ->with('redes')
            ->find($cadenaDesencriptada);

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Marca no encontrada'], 404);
        }

        $obj->servicios = json_decode($obj->servicios);
        $obj->horario = json_decode($obj->horario);
        $obj->parametros = json_decode($obj->parametros);
        $obj->parametros_imagen = json_decode($obj->parametros_imagen);

        for ($i=0; $i < count($obj->redes); $i++) { 
            $obj->redes[$i]->access_token = null;
            $obj->redes[$i]->meta_user_id = null;
            $obj->redes[$i]->page_id = null;
            $obj->redes[$i]->page_image = null;
            $obj->redes[$i]->page_name = null;
            $obj->redes[$i]->password = null;
            $obj->redes[$i]->user = null;
        }

        return response()->json(['marca'=>$obj], 200);
    }

    public function finPeriodoPrueba()
    {
        return 1;
        
        $date = Carbon::now();
        $test_day = $date->day;
        $test_month = $date->month;
        $test_year = $date->year;

        $objs = SocialBrand::
            select('id','nombre','status',
                'test_day','test_month','test_year')
            ->where('test_day', $test_day)
            ->where('test_month', $test_month)
            ->where('test_year', $test_year)
            ->get();

        for ($i=0; $i < count($objs); $i++) { 
            $objs[$i]->status = 0;
            $objs[$i]->save();
        }

        return response()->json(['marcas'=>$objs], 200);
    }

    public function imagenAllowOrigin($imagen)
    {

        // Formar la ruta de la imagen
        $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."marcas".DIRECTORY_SEPARATOR;
        $archivo_ruta = $destinationPath.$imagen;

        if (!file_exists($archivo_ruta)) {
            $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."clientes_bot".DIRECTORY_SEPARATOR;
            $archivo_ruta = $destinationPath.$imagen;
        }

        // Establecer el encabezado de acceso de origen cruzado
        header("Access-Control-Allow-Origin: *");

        // Obtener el tipo MIME de la imagen
        $mime_type = mime_content_type($archivo_ruta);

        // Establecer el encabezado de tipo MIME
        header("Content-Type: $mime_type");

        // Enviar los datos de la imagen al navegador
        readfile($archivo_ruta);
    }


}
