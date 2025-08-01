<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Crypt;

use Exception;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

use App\Models\User;
use App\Models\Sistema;
use App\Models\Bot;
use App\Models\BotCliente;
use App\Models\BotChat;

use App\Models\CfdiEmpresa;
use App\Models\CfdiComprobante;

use App\Models\Cotizacion;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Color;

use App\Models\SocialBrand;


//use Hash;
use DB;
//use Validator;

use Carbon\Carbon;


date_default_timezone_set('America/Mexico_City');

class BotClienteController extends Controller
{
    public function validarToken(Request $request)
    {

        return true;
        
        try {
            $user = JWTAuth::parseToken()->authenticate();
            //return response()->json(['user' => $user], 200);
            return true;

        } catch (Exception $e) {

            //return true;

            if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenInvalidException){
                return ['error' => 'Token is Invalid'];
            } else if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenExpiredException){
                return ['error' => 'Token is Expired'];
            } else {
                return ['error' => 'Authorization Token not found'];
            }
        }

    }

    public function index()
    {

        $objs = BotCliente::
            select('id','nombre','empresa',
                'telefono','status','count_querys',
                'hab_citas','hab_redes','hab_pedidos','hab_cotizaciones','hab_facturas',
                'max_facturas','count_facturas','last_pay_date','created_at')
            ->get();

        return response()->json(['clientes'=>$objs], 200);
    }

    public function show($id)
    {
        // La cadena cifrada que se envió desde Angular
        $cadenaEncriptada = $id;

        $claveAdicional = config('app.lada_c');

        try {
            $cadenaDesencriptada = Crypt::decrypt($cadenaEncriptada, $claveAdicional);
        } catch (Exception $e) {
            return response()->json(['error'=>'Cliente no encontrado'], 404);
        }

        $obj = BotCliente::find($cadenaDesencriptada);

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Cliente no encontrado'], 404);
        }

        $obj->flag_marca_social_id = false;

        $user_social = User::with('marcas')
            ->where('bot_cliente_id', $obj->id)
            ->first();

        if($user_social && count($user_social->marcas) > 0){

            $cadena = $user_social->marcas[0]->id;

            $claveAdicional = config('app.lada_a');

            $cadenaEncriptada = Crypt::encrypt($cadena, $claveAdicional);
            
            $obj->flag_marca_social_id = $cadenaEncriptada;
        }

        return response()->json(['cliente'=>$obj], 200);
    }

    public function finPeriodoPrueba()
    {
        $date = Carbon::now();
        $test_day = $date->day;
        $test_month = $date->month;
        $test_year = $date->year;

        $objs = BotCliente::
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

        return response()->json(['clientes'=>$objs], 200);
    }

    public function updateStatus(Request $request, $cliente_id)
    {
        // Comprobamos si el usuario que nos están pasando existe o no.
        $usuario=BotCliente::find($cliente_id);

        if (!$usuario)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Usuario no encontrado.'], 404);
        }    
        
        // Listado de campos recibidos teóricamente.
        $status=$request->input('status');

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Actualización parcial de campos.
        if (($status != null && $status!='') || $status === 0)
        {
            $usuario->status = $status;
            $bandera=true;
        }
       
        if ($bandera)
        {
            // Almacenamos en la base de datos el registro.
            if ($usuario->save()) {
                return response()->json(['message'=>'Usuario actualizado.',
                 'usuario'=>$usuario], 200);
            }else{
                return response()->json(['error'=>'Error al actualizar el usuario.'], 500);
            }           
        }
        else
        {
            // Se devuelve un array error con los error encontrados y cabecera HTTP 304 Not Modified – [No Modificada] Usado cuando el cacheo de encabezados HTTP está activo
            // Este código 304 no devuelve ningún body, así que si quisiéramos que se mostrara el mensaje usaríamos un código 200 en su lugar.
            return response()->json(['error'=>'No se ha modificado ningún al usuario.'],500);
        }
    }

    public function update(Request $request, $cliente_id)
    {
        // Comprobamos si el usuario que nos están pasando existe o no.
        $usuario=BotCliente::find($cliente_id);

        if (!$usuario)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Usuario no encontrado.'], 404);
        }    
        
        // Listado de campos recibidos teóricamente.
        $empresa=$request->input('empresa');
        $flag_colores=$request->input('flag_colores');
        $flag_stock=$request->input('flag_stock');
        $costo_envio=$request->input('costo_envio');
        $logo=$request->input('logo');
        $color_a=$request->input('color_a');
        $color_b=$request->input('color_b');
        $color_c=$request->input('color_c');
        $hab_citas=$request->input('hab_citas');
        $hab_redes=$request->input('hab_redes');
        $hab_pedidos=$request->input('hab_pedidos');
        $hab_cotizaciones=$request->input('hab_cotizaciones');
        $hab_facturas=$request->input('hab_facturas');
        $max_facturas=$request->input('max_facturas');

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Actualización parcial de campos.
        if ($empresa != null && $empresa!='')
        {
            if(strlen($empresa) > 90){
                return response()->json(['error'=>'El nombre comercial debe tener máximo 90 caracteres.'], 409);
            }

            $usuario->empresa = $empresa;
            $bandera=true;
        }

        if (($flag_colores != null && $flag_colores!='') || $flag_colores === 0)
        {
            $usuario->flag_colores = $flag_colores;
            $bandera=true;
        }

        if (($flag_stock != null && $flag_stock!='') || $flag_stock === 0)
        {
            $usuario->flag_stock = $flag_stock;
            $bandera=true;
        }

        if (($costo_envio != null && $costo_envio!='') || $costo_envio === 0)
        {
            $usuario->costo_envio = $costo_envio;
            $bandera=true;
        }

        if ($logo != null && $logo!='')
        {
            if($usuario->logo != null && $usuario->logo!='' && $usuario->logo!=$logo){
                //Eliminar la imagen vieja
                $cadenas = explode('/',$usuario->logo);
                $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."clientes_bot".DIRECTORY_SEPARATOR;
                $fileName = $cadenas[count($cadenas)-1];
                $archivo_ruta = $destinationPath.$fileName;
                if (file_exists($archivo_ruta)) {
                    unlink($archivo_ruta); // Eliminar la imagen
                }
            }

            $cadenas = explode('/',$logo);
            $fileName = $cadenas[count($cadenas)-1];
            $logo_allow_origin = 'https://apisocial.internow.com.mx/api/bots_cliente/allow_origin/'.$fileName;

            $usuario->logo = $request->input('logo');
            $usuario->logo_allow_origin = $logo_allow_origin;
            $bandera=true;
        }

        if ($color_a != null && $color_a!='')
        {
            $usuario->color_a = $color_a;
            $bandera=true;
        }

        if ($color_b != null && $color_b!='')
        {
            $usuario->color_b = $color_b;
            $bandera=true;
        }

        if ($color_c != null && $color_c!='')
        {
            $usuario->color_c = $color_c;
            $bandera=true;
        }

        if (($hab_citas != null && $hab_citas!='') || $hab_citas === 0)
        {
            $usuario->hab_citas = $hab_citas;
            $bandera=true;
        }

        if (($hab_redes != null && $hab_redes!='') || $hab_redes === 0)
        {
            $usuario->hab_redes = $hab_redes;
            $bandera=true;
        }

        if (($hab_pedidos != null && $hab_pedidos!='') || $hab_pedidos === 0)
        {
            $usuario->hab_pedidos = $hab_pedidos;
            $bandera=true;
        }

        if (($hab_cotizaciones != null && $hab_cotizaciones!='') || $hab_cotizaciones === 0)
        {
            $usuario->hab_cotizaciones = $hab_cotizaciones;
            $bandera=true;
        }

        if (($hab_facturas != null && $hab_facturas!='') || $hab_facturas === 0)
        {
            $usuario->hab_facturas = $hab_facturas;
            $bandera=true;
        }

        if (($max_facturas != null && $max_facturas!='') || $max_facturas === 0)
        {
            $usuario->max_facturas = $max_facturas;
            $bandera=true;
        }
       
        if ($bandera)
        {
            // Almacenamos en la base de datos el registro.
            if ($usuario->save()) {
                return response()->json(['message'=>'Usuario actualizado.',
                 'usuario'=>$usuario], 200);
            }else{
                return response()->json(['error'=>'Error al actualizar el usuario.'], 500);
            }           
        }
        else
        {
            // Se devuelve un array error con los error encontrados y cabecera HTTP 304 Not Modified – [No Modificada] Usado cuando el cacheo de encabezados HTTP está activo
            // Este código 304 no devuelve ningún body, así que si quisiéramos que se mostrara el mensaje usaríamos un código 200 en su lugar.
            return response()->json(['error'=>'No se ha modificado ningún al usuario.'],500);
        }
    }

    public function getCliente(Request $request, $cliente_id)
    {
        $token_result = $this->validarToken($request);
        if($token_result !== true){
            return response()->json($token_result, 401);
        }

        // La cadena cifrada que se envió desde Angular
        $cadenaEncriptada = $cliente_id;

        $claveAdicional = config('app.lada_d');

        try {
            $cadenaDesencriptada = Crypt::decrypt($cadenaEncriptada, $claveAdicional);
        } catch (Exception $e) {
            return response()->json(['error'=>'Cliente no encontrado'], 404);
        }

        $obj = BotCliente::find($cadenaDesencriptada);

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Cliente no encontrado'], 404);
        }

        return response()->json(['cliente'=>$obj], 200);
    }

    public function imagenAllowOrigin($imagen)
    {
        // Formar la ruta de la imagen
        $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."clientes_bot".DIRECTORY_SEPARATOR;
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

    public function cambiarImagenes(Request $request, $cliente_id)
    {
        $token_result = $this->validarToken($request);
        if($token_result !== true){
            return response()->json($token_result, 401);
        }

        // Comprobamos si el usuario que nos están pasando existe o no.
        $usuario=BotCliente::find($cliente_id);

        if (!$usuario)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Usuario no encontrado.'], 404);
        }    
        
        // Listado de campos recibidos teóricamente.
        $flag=$request->input('flag');

        if (!$request->hasFile('archivo')) {
            return response()->json(['error'=>'Archivo no detectado.'], 422);
        }

        //Guardar la imagen y generar el url
        $array_rutas = $this->storeLinkImagen3($request);

        if(!$array_rutas){
            return response()->json(['error'=>'Error al guardar la imagen.'],409);
        }

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Actualización parcial de campos.

        if ($flag != null && $flag!='')
        {

            $url_old = null;

            //header
            if($flag == 1){

                $url_old = $usuario->header;

                $usuario->header = $array_rutas[0];
                $bandera=true;

            }

            //footer
            if($flag == 2){

                $url_old = $usuario->footer;

                $usuario->footer = $array_rutas[0];
                $bandera=true;

            }

            if($url_old != null && $url_old!=''){
                //Eliminar la imagen vieja
                $cadenas = explode('/',$url_old);
                $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."cotizaciones_frames".DIRECTORY_SEPARATOR;
                $fileName = $cadenas[count($cadenas)-1];
                $archivo_ruta = $destinationPath.$fileName;
                if (file_exists($archivo_ruta)) {
                    unlink($archivo_ruta); // Eliminar la imagen
                }
            }
            
        }
        
       
        if ($bandera)
        {
            // Almacenamos en la base de datos el registro.
            if ($usuario->save()) {
                return response()->json(['message'=>'Usuario actualizado.',
                 'usuario'=>$usuario], 200);
            }else{
                return response()->json(['error'=>'Error al actualizar el usuario.'], 500);
            }           
        }
        else
        {
            // Se devuelve un array error con los error encontrados y cabecera HTTP 304 Not Modified – [No Modificada] Usado cuando el cacheo de encabezados HTTP está activo
            // Este código 304 no devuelve ningún body, así que si quisiéramos que se mostrara el mensaje usaríamos un código 200 en su lugar.
            return response()->json(['error'=>'No se ha modificado ningún al usuario.'],500);
        }
    }

    public function storeLinkImagen3(Request $request)
    {
        try{

            set_time_limit(500);
        
            $carpeta = 'images_uploads/cotizaciones_frames/';
            //$url_base = 'http://localhost/publicacionesIA/publicacionesIAAPI/public/';
            $url_base = 'https://apisocial.internow.com.mx/';

            $hoy = date("m.d.y.H.i.s");

            $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."cotizaciones_frames".DIRECTORY_SEPARATOR;
            $fileName = $hoy.'.png';

            $request->file('archivo')->move($destinationPath,$fileName);

            $archivo_ruta = $url_base.$carpeta.$fileName;

            $array_rutas = [
                $archivo_ruta,
                'https://apisocial.internow.com.mx/api/clientes_bot/allow_origin/'.$fileName
            ];

            return $array_rutas;

        } catch ( Exception $e ){

            //return $e->getMessage();
            return null;

        }
        
    }

    public function destroy($cliente_id)
    {
        $cliente=BotCliente::find($cliente_id);

        if (!$cliente)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Usuario no encontrado.'], 404);
        } 

        //---FACTURAS

        //eliminar los mensajes
        DB::table('bot_chats')
            ->where('cliente_id', $cliente->id)
            ->delete();

        $empresa=CfdiEmpresa::
            where('bot_cliente_id', $cliente->id)
            ->first();

        if ($empresa)
        {
            //eliminar el producto por defecto de la empresa
            DB::table('cfdi_productos')
                ->where('empresa_id', $empresa->id)
                ->delete();
        }

        //eliminar los clientes de la empresa
        DB::table('cfdi_clientes')
            ->where('empresa_id', $empresa->id)
            ->delete();

        //eliminar la empresa de las facturas
        DB::table('cfdi_empresas')
            ->where('bot_cliente_id', $cliente->id)
            ->delete();


        $facturas=CfdiComprobante::select('id','cliente_id')
            ->where('cliente_id', $cliente->id)
            ->get();

        for ($i=0; $i < count($facturas); $i++) {

            DB::table('cfdi_receptor')
                ->where('comprobante_id', $facturas[$i]->id)
                ->delete();

            DB::table('cfdi_conceptos')
                ->where('comprobante_id', $facturas[$i]->id)
                ->delete();

            DB::table('cfdi_impuestos')
                ->where('comprobante_id', $facturas[$i]->id)
                ->delete();

            DB::table('cfdi_timbre_fiscal_digital')
                ->where('comprobante_id', $facturas[$i]->id)
                ->delete();

            DB::table('cfdi_archivos')
                ->where('comprobante_id', $facturas[$i]->id)
                ->delete();

        }

        //eliminar las facturas
        DB::table('cfdi_comprobante')
                ->where('cliente_id', $cliente->id)
                ->delete();


        //---CITAS 

        //eliminar las citas
        DB::table('bot_citas')
                ->where('cliente_id', $cliente->id)
                ->delete();

        //---COTIZACIONES

        $cotizaciones=Cotizacion::select('id','cliente_id')
            ->where('cliente_id', $cliente->id)
            ->get();

        for ($i=0; $i < count($cotizaciones); $i++) {

            DB::table('cotizacion_gastos')
                ->where('cotizacion_id', $cotizaciones[$i]->id)
                ->delete();

        }

        //---PEDIDOS

        $pedidos=Pedido::select('id','cliente_id')
            ->where('cliente_id', $cliente->id)
            ->get();

        for ($i=0; $i < count($pedidos); $i++) {

            DB::table('pedido_detalle')
                ->where('pedido_id', $pedidos[$i]->id)
                ->delete();

        }

        //---PRODUCTOS

        $prouctos=Producto::select('id','cliente_id')
            ->where('cliente_id', $cliente->id)
            ->get();

        for ($i=0; $i < count($prouctos); $i++) {

            DB::table('producto_imagenes')
                ->where('producto_id', $prouctos[$i]->id)
                ->delete();

            $colores=Color::select('id','producto_id')
                ->where('producto_id', $prouctos[$i]->id)
                ->get();

            for ($j=0; $j < count($colores); $j++) {
                DB::table('tipos')
                    ->where('color_id', $colores[$j]->id)
                    ->delete();
            }

            DB::table('colores')
                ->where('producto_id', $prouctos[$i]->id)
                ->delete();

        }

        DB::table('productos')
            ->where('cliente_id', $cliente->id)
            ->delete();

        //---REDES SOCIALES

        //Cargar las marcas sociales
        $user_social=User::
            with(['marcas' => function ($query) {
                $query->select('id','user_id');
            }])
            ->where('bot_cliente_id', $cliente->id)
            ->first();

        if ($user_social)
        {

            for ($i=0; $i < count($user_social->marcas); $i++) { 

                $obj = SocialBrand::
                    with(['posts' => function ($query) {
                        $query->select('id','brand_id');
                     
                    }])
                    ->find($user_social->marcas[$i]->id);

                if ($obj)
                {
                    
                    //Eliminar las redes de la marca
                    DB::table('social_networks')
                        ->where('brand_id', $user_social->marcas[$i]->id)
                        ->delete();

                    //Eliminar las imagenes
                    for ($j=0; $j < count($obj->posts); $j++) { 
                        DB::table('social_images')
                            ->where('post_id', $obj->posts[$j]->id)
                            ->delete();
                    }

                    //Eliminar los posts
                    DB::table('social_posts')
                            ->where('brand_id', $user_social->marcas[$i]->id)
                            ->delete();

                    // Eliminamos la marca
                    $obj->delete();

                }

            }

            // Eliminamos el usuario
            $user_social->delete();

        }

        // Eliminamos el cliente del bot
        $cliente->delete();

        return response()->json(['message'=>'Se ha eliminado correctamente el usuario.'], 200);
    }
}
