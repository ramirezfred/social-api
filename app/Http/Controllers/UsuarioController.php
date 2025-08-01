<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\Crypt;

use App\Models\User;
use App\Models\SocialBrand;
use App\Models\SocialNetwork;
use App\Models\SocialPost;
use App\Models\Sistema;

use App\Models\BotCliente;

//use Hash;
use DB;
//use Validator;

use Carbon\Carbon;

date_default_timezone_set('America/Mexico_City');

class UsuarioController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {

        //$coleccion = User::all();
        $coleccion = User::/*with('marcas.redes')*/
            with(['marcas' => function ($query) {
                $query->select('id', 'user_id','nombre',
                    'logo','servicios','horario',
                    'status','parametros','parametros_imagen',
                    'bandera_flujo','prompt_imagen',
                    'pexels_status','pexels_frase',
                    'comment_status','comment_prompt',
                    'comment_auto','comment_footer',
                    'prompt_textos'
                )
                ->with(['redes' => function ($query) {
                    $query->select('id', 'brand_id','tipo',
                        'page_id','page_name')
                        ->orderBy('tipo', 'asc');
                }]);
            }])
            ->where('tipo', 2)
            ->orderBy('id', 'desc')
            ->get();

        for ($i=0; $i < count($coleccion); $i++) {
            for ($j=0; $j < count($coleccion[$i]->marcas); $j++) { 
                $coleccion[$i]->marcas[$j]->servicios = json_decode($coleccion[$i]->marcas[$j]->servicios);
                $coleccion[$i]->marcas[$j]->horario = json_decode($coleccion[$i]->marcas[$j]->horario);
                $coleccion[$i]->marcas[$j]->parametros = json_decode($coleccion[$i]->marcas[$j]->parametros);
                $coleccion[$i]->marcas[$j]->parametros_imagen = json_decode($coleccion[$i]->marcas[$j]->parametros_imagen);
            }
        }

        return response()->json(['clientes'=>$coleccion], 200);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Primero comprobaremos si estamos recibiendo todos los campos.
        $validator = Validator::make($request->all(),[
            //'email'=>'required|string|email|unique:users,email',
            'email'=>'required|string',
            'password'=>'required|string',
            //'servicios'=>'required|string',
            //'telefono'=>'required|string',
            'telefono'=>'required|numeric|digits:10',
        ]);
        if ($validator->fails()) { 
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 422 Unprocessable Entity – [Entidad improcesable] Utilizada para errores de validación.
            return response()->json(['error'=>'Error de validación',
                'detalle'=>$validator->errors(),
            ],422);
        }

        $aux2 = User::where('email', $request->input('email'))->get();
        if(count($aux2)!=0){
            return response()->json(['error'=>'Ya existe un usuario con esas credenciales.'], 409);    
        }

        /*Primero creo una instancia en la tabla usuarios*/
        $usuario = new User;
        $usuario->tipo = 2; //cliente
        $usuario->status = 1;
        $usuario->email = $request->input('email');
        $usuario->password = Hash::make($request->input('password'));
        $usuario->telefono = $request->input('telefono');
        
        if($usuario->save()){

           return response()->json(['message'=>'Usuario creado con éxito.',
             'usuario'=>$usuario], 200);
        }else{
            return response()->json(['error'=>'Error al crear el usuario.'], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        // Comprobamos si lo que nos están pasando existe o no.

        $usuario = User::find($id);

        if (!$usuario)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe el usuario con id '.$id], 404);
        }

        if($usuario->tipo != 2){
            return response()->json(['error'=>'Permisos inválidos.'], 401);
        }

        // Listado de campos recibidos teóricamente.
        $email=$request->input('email'); 
        $password=$request->input('password'); 
        //$status=$request->input('status');
        $telefono=$request->input('telefono'); 

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Actualización parcial de campos de usuario.
        if ($email != null && $email!='')
        {
            $aux = User::where('email', $request->input('email'))
            ->where('id', '<>', $usuario->id)->get();

            if(count($aux)!=0){
               // Devolvemos un código 409 Conflict. 
                return response()->json(['error'=>'Ya existe otro usuario con ese email.'], 409);
            }

            $usuario->email = $email;
            $bandera=true;
        }

        if ($password != null && $password!='')
        {
            $usuario->password = Hash::make($request->input('password'));
            $bandera=true;
        }

        // if (($status != null && $status!='') || $status===0)
        // {
        //     $usuario->status = $request->input('status');
        //     $bandera=true;
        // }

        if ($telefono != null && $telefono!='')
        {
            $usuario->telefono = $request->input('telefono');
            $bandera=true;
        }

        if ($bandera)
        {

            // Almacenamos en la base de datos el registro.
            if ($usuario->save()) {
                return response()->json(['message'=>'Usuario editado con éxito.',
                    'usuario'=>$usuario], 200);
            }else{
                return response()->json(['error'=>'Error al actualizar el usuario.'], 500);
            }
            
        }
        else
        {
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 304 Not Modified – [No Modificada] Usado cuando el cacheo de encabezados HTTP está activo
            // Este código 304 no devuelve ningún body, así que si quisiéramos que se mostrara el mensaje usaríamos un código 200 en su lugar.
            return response()->json(['error'=>'No se ha modificado ningún dato al usuario.'],409);
        }
    }

    public function updateStatus(Request $request, $usuario_id)
    {
        // Comprobamos si el usuario que nos están pasando existe o no.
        $usuario=User::find($usuario_id);

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

    public function crear(Request $request)
    {
        // Primero comprobaremos si estamos recibiendo todos los campos.
        $validator = Validator::make($request->all(),[
            //'email'=>'required|string|email|unique:users,email',
            'email'=>'required|string',
            'password'=>'required|string',
            'marca'=>'required|string',
            'servicios'=>'required|string',
            'logo'=>'required|string',
        ]);
        if ($validator->fails()) { 
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 422 Unprocessable Entity – [Entidad improcesable] Utilizada para errores de validación.
            return response()->json(['error'=>'Error de validación',
                'detalle'=>$validator->errors(),
            ],422);
        }

        $aux2 = User::where('email', $request->input('email'))->get();
        if(count($aux2)!=0){
            return response()->json(['error'=>'Ya existe un usuario con esas credenciales.'], 409);    
        }

        $servicios = json_decode($request->input('servicios'));
        if (count($servicios)==0) {
            // Devolvemos un código 409 Conflict. 
            return response()->json(['error'=>'Debe agregar al menos un servicio.'], 409);
        }

        /*Primero creo una instancia en la tabla usuarios*/
        $usuario = new User;
        $usuario->tipo = 2; //cliente
        $usuario->status = 1;
        $usuario->email = $request->input('email');
        $usuario->password = Hash::make($request->input('password'));
        $usuario->telefono = $request->input('telefono');
        
        if($usuario->save()){

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

            $logo = $request->input('logo');
            $cadenas = explode('/',$logo);
            $fileName = $cadenas[count($cadenas)-1];
            $logo_allow_origin = 'https://apisocial.internow.com.mx/api/marcas/allow_origin/'.$fileName;

            $nuevoObj=SocialBrand::create([
                'user_id'=>$usuario->id,
                'nombre'=>$request->input('marca'),
                'logo'=>$request->input('logo'),
                'logo_allow_origin'=>$logo_allow_origin,
                'servicios'=>$request->input('servicios'),
                //'servicios' => json_encode([]),
                'horario'=>$horario_json,
                'status'=>0,
                'parametros' => json_encode([]),
                'parametros_imagen' => json_encode([]),
                'bandera_flujo'=>0,
                'color_a'=>'#d6d9da',
                'color_b'=>'#618a9f',
                'color_c'=>'#1a2d4e',
                'font'=>'Arial',

            ]);

            $nuevoObj->servicios = json_decode($nuevoObj->servicios);
            $nuevoObj->horario = json_decode($nuevoObj->horario);
            $nuevoObj->parametros = json_decode($nuevoObj->parametros);
            $nuevoObj->parametros_imagen = json_decode($nuevoObj->parametros_imagen);

           return response()->json(['message'=>'Usuario creado con éxito.',
             'usuario'=>$usuario], 200);
        }else{
            return response()->json(['error'=>'Error al crear el usuario.'], 500);
        }
    }

    public function destroy($id)
    {
        $usuario=User::
            with(['marcas' => function ($query) {
                $query->select('id','user_id');
             
            }])
            ->find($id);

        if (!$usuario)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Usuario no encontrado.'], 404);
        } 

        for ($i=0; $i < count($usuario->marcas); $i++) { 

            $obj = SocialBrand::
                with(['posts' => function ($query) {
                    $query->select('id','brand_id');
                 
                }])
                ->find($usuario->marcas[$i]->id);

            if (!$obj)
            {
                // Devolvemos error codigo http 404
                return response()->json(['error'=>'No existe la marca con id '.$usuario->marcas[$i]->id], 404);
            }
           
            //Eliminar las redes de la marca
            DB::table('social_networks')
                ->where('brand_id', $usuario->marcas[$i]->id)
                ->delete();

            //Eliminar las imagenes
            for ($j=0; $j < count($obj->posts); $j++) { 
                DB::table('social_images')
                    ->where('post_id', $obj->posts[$j]->id)
                    ->delete();
            }

            //Eliminar los posts
            DB::table('social_posts')
                    ->where('brand_id', $usuario->marcas[$i]->id)
                    ->delete();

            // Eliminamos la marca
            $obj->delete();

        }

        // Eliminamos el usuario
        $usuario->delete();

        return response()->json(['message'=>'Se ha eliminado correctamente el usuario.'], 200);
    }

    public function crearUserMarca(Request $request)
    {
        // Primero comprobaremos si estamos recibiendo todos los campos.
        $validator = Validator::make($request->all(),[
            //'email'=>'required|string|email|unique:users,email',
            'email'=>'required|string',
            'password'=>'required|string',
            //'telefono'=>'required|string',
            'telefono'=>'required|numeric|digits:10',
            'marca'=>'required|string',
            'servicios'=>'required|string',
            'logo'=>'required|string',
            'horario'=>'required|string',
            'color_a'=>'required|string',
            'color_b'=>'required|string',
            'color_c'=>'required|string',

        ]);
        if ($validator->fails()) { 
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 422 Unprocessable Entity – [Entidad improcesable] Utilizada para errores de validación.
            return response()->json(['error'=>'Error de validación',
                'detalle'=>$validator->errors(),
            ],422);
        }

        $aux2 = User::where('email', $request->input('email'))->get();
        if(count($aux2)!=0){
            return response()->json(['error'=>'Ya existe un usuario con esas credenciales.'], 409);    
        }

        $servicios = json_decode($request->input('servicios'));
        if (count($servicios)==0) {
            // Devolvemos un código 409 Conflict. 
            return response()->json(['error'=>'Debe agregar al menos un servicio.'], 409);
        }

        $sistema = Sistema::all();
        if (count($sistema)==0)
        {
            return response()->json(['error'=>'Sistema no configurado.'], 409);
        }
        $prompt_textos = $sistema[0]->prompt_textos;

        // Comprobamos si el usuario cliente del bot que nos están pasando existe o no.
        $usuario_bot=BotCliente::find($request->input('bot_cliente_id'));
        if (!$usuario_bot)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Usuario bot no encontrado.'], 404);
        }  

        /*Primero creo una instancia en la tabla usuarios*/
        $usuario = new User;
        $usuario->tipo = 2; //cliente
        $usuario->status = 1;
        $usuario->email = $request->input('email');
        $usuario->password = Hash::make($request->input('password'));
        $usuario->telefono = $request->input('telefono');
        $usuario->bot_cliente_id = $request->input('bot_cliente_id');
        
        if($usuario->save()){

            //Limite de prueba
            $date = Carbon::now()->addDays(3);
            $test_day = $date->day;
            $test_month = $date->month;
            $test_year = $date->year;

            $logo = $request->input('logo');
            $cadenas = explode('/',$logo);
            $fileName = $cadenas[count($cadenas)-1];
            $logo_allow_origin = 'https://apisocial.internow.com.mx/api/marcas/allow_origin/'.$fileName;

            $nuevoObj=SocialBrand::create([
                'user_id'=>$usuario->id,
                'nombre'=>$request->input('marca'),
                'logo'=>$request->input('logo'),
                'logo_allow_origin'=>$logo_allow_origin,
                'servicios'=>$request->input('servicios'),
                'horario'=>$request->input('horario'),
                'status'=>1,
                'parametros' => json_encode([]),
                'parametros_imagen' => json_encode([]),
                'bandera_flujo'=>0,
                'color_a'=>$request->input('color_a'),
                'color_b'=>$request->input('color_b'),
                'color_c'=>$request->input('color_c'),
                'test_day'=>$test_day,
                'test_month'=>$test_month,
                'test_year'=>$test_year,
                'prompt_textos'=>$prompt_textos,
                'pexels_status'=>1,
                'pexels_frase'=>$servicios[0],
                'font'=>$request->input('font'),

            ]);

            $nuevoObj->servicios = json_decode($nuevoObj->servicios);
            $nuevoObj->horario = json_decode($nuevoObj->horario);
            $nuevoObj->parametros = json_decode($nuevoObj->parametros);
            $nuevoObj->parametros_imagen = json_decode($nuevoObj->parametros_imagen);

            $claveAdicional = config('app.lada_a');
            $cadenaEncriptada = Crypt::encrypt($nuevoObj->id, $claveAdicional);

            return response()->json([
                'message'=>'Usuario creado con éxito.',
                'usuario'=>$usuario,
                'marca_id'=>$cadenaEncriptada
            ], 200);

        }else{
            return response()->json(['error'=>'Error al crear el usuario.'], 500);
        }
    }
  
}
