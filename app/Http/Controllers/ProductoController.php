<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Crypt;

use Exception;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

use Illuminate\Support\Facades\Validator;

use App\Models\User;
use App\Models\BotCliente;
use App\Models\Producto;
use App\Models\Color;
use App\Models\Tipo;
use App\Models\ProductoImagen;

use Carbon\Carbon;

date_default_timezone_set('America/Mexico_City');

class ProductoController extends Controller
{
    public function validarToken(Request $request)
    {
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

    public function index(Request $request, $cliente_id)
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

        $obj = BotCliente::
            select('id','bot_id','nombre',
                'telefono','empresa','flag_colores','flag_stock',
                'color_a','color_b','color_c','logo')
            ->find($cadenaDesencriptada);

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Cliente no encontrado'], 404);
        }

        $productos = Producto::
            whereNull('eliminado')
            //->where('cliente_id',$cliente_id)
            ->where('cliente_id',$obj->id)
            ->get();

        return response()->json([
            'cliente'=>$obj,
            'productos'=>$productos
        ], 200);
        
    }

    public function store(Request $request)
    {

        // Primero comprobaremos si estamos recibiendo todos los campos.
        $validator = Validator::make($request->all(),[
            'cliente_id'=>'required|string',
            'nombre'=>'required|string',
            'url'=>'required|string',
            'colores'=>'required|string',
        ]);
        if ($validator->fails()) { 
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 422 Unprocessable Entity – [Entidad improcesable] Utilizada para errores de validación.
            return response()->json(['error'=>'Error de validación',
                'detalle'=>$validator->errors(),
            ],422);
        }

        $token_result = $this->validarToken($request);
        if($token_result !== true){
            return response()->json($token_result, 401);
        }
        
        // La cadena cifrada que se envió desde Angular
        $cadenaEncriptada = $request->input('cliente_id');

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

        $aux = Producto::where('nombre', $request->input('nombre'))
            //->where('cliente_id', $request->input('cliente_id'))
            ->where('cliente_id', $obj->id)
            ->get();
        if(count($aux)!=0){
           // Devolvemos un código 409 Conflict. 
            return response()->json(['error'=>'Ya existe un producto con ese nombre.'], 409);
        }

        //Validar al menos un color
        $colores = json_decode($request->input('colores'));
        if ( count($colores) == 0) { 
            return response()->json(['error'=>'El producto debe tener al menos un color'], 409);
        }

        //Validar al menos un tipo en cada color
        for ($i=0; $i < count($colores); $i++) { 

            if ( count($colores[$i]->tipos) == 0) { 
                return response()->json(['error'=>'El color '.$colores[$i]->nombre.' debe tener al menos un tipo'], 409);
            }

            for ($j=0; $j < count($colores[$i]->tipos); $j++) { 
                if ( $colores[$i]->tipos[$j]->stock < 0) { 
                    return response()->json(['error'=>'El stock del tipo '.$colores[$i]->tipos[$j]->nombre.' color '.$colores[$i]->nombre.', debe ser mayor o igual a cero'], 409);
                }   

                if ( $colores[$i]->tipos[$j]->precio < 0) { 
                    return response()->json(['error'=>'El precio del tipo '.$colores[$i]->tipos[$j]->nombre.' color '.$colores[$i]->nombre.', debe ser mayor o igual a cero'], 409);
                } 
            }
        }

        if($nuevoProducto=Producto::create([
            //'cliente_id' => $request->input('cliente_id'),
            'cliente_id' => $obj->id,
            'nombre' => $request->input('nombre'),
            'status' => 1,
            'url' => $request->input('url'),
            'precio' => $request->input('precio'),
            'stock' => $request->input('stock'),
            'descripcion' => $request->input('descripcion'),

        ])){

            //Crear los colores y tipos
            for ($i=0; $i < count($colores); $i++) { 

                $nuevoColor=Color::create([
                    'nombre' => $colores[$i]->nombre,
                    'status' => 1,
                    'producto_id' => $nuevoProducto->id,
                    'eliminado' => null,
                ]);

                for ($j=0; $j < count($colores[$i]->tipos); $j++) { 
                    $nuevoTipo=Tipo::create([
                        'nombre' => $colores[$i]->tipos[$j]->nombre,
                        'status' => 1,
                        'precio' => $colores[$i]->tipos[$j]->precio,
                        'stock' => $colores[$i]->tipos[$j]->stock,
                        'color_id' => $nuevoColor->id,
                        'eliminado' => null,
                    ]);
                }
            }

            //Crear las imagenes
            $imagenes = json_decode($request->input('imagenes'));
            for ($i=0; $i < count($imagenes) ; $i++) { 

                $imagen=ProductoImagen::create([
                    'url'=>$imagenes[$i]->url,
                    'producto_id'=>$nuevoProducto->id,
                ]);
                   
            }

           return response()->json(['message'=>'Producto creado con éxito.',
             'producto'=>$nuevoProducto], 200);
        }else{
            return response()->json(['error'=>'Error al crear el producto.'], 500);
        }

    }

    public function update(Request $request, $id)
    {
        $token_result = $this->validarToken($request);
        if($token_result !== true){
            return response()->json($token_result, 401);
        }

        // Comprobamos si lo que nos están pasando existe o no.
        $producto = Producto::find($id);

        if (!$producto)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe el producto con id '.$id], 404);
        }

        // Listado de campos recibidos teóricamente.
        $nombre=$request->input('nombre');
        $url=$request->input('url');
        $descripcion=$request->input('descripcion');
        $status=$request->input('status');
        $precio=$request->input('precio');
        $stock=$request->input('stock');

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Actualización parcial de campos.
        if ($nombre != null && $nombre!='')
        {
            $aux = Producto::whereNull('eliminado')
                ->where('nombre', $request->input('nombre'))
                ->where('id', '<>', $producto->id)
                ->where('cliente_id', '<>', $producto->cliente_id)
                ->get();

            if(count($aux)!=0){
               // Devolvemos un código 409 Conflict. 
                return response()->json(['error'=>'Ya existe otro producto con el nombre '.$nombre], 409);
            }

            $producto->nombre = $nombre;
            $bandera=true;
        }

        if ($url != null && $url!='')
        {
            $producto->url = $url;
            $bandera=true;
        }

        if ($descripcion != null && $descripcion!='')
        {
            $producto->descripcion = $descripcion;
            $bandera=true;
        }

        if (($status != null && $status != '') || $status === 0)
        {
            $producto->status = $status;
            $bandera=true;
        }

        if (($precio != null && $precio != '') || $precio === 0)
        {
            $producto->precio = $precio;
            $bandera=true;

            $cliente = BotCliente::find($producto->cliente_id);
            if($cliente){
                //si el cliente usa productos sin colores
                if($cliente->flag_colores == 0){
                    $colores = Color::
                        where('producto_id',$producto->id)
                        ->with('tipos')
                        ->get();

                    if(count($colores)>0 && count($colores[0]->tipos)>0){
                        $colores[0]->tipos[0]->precio = $precio;
                        $colores[0]->tipos[0]->save();
                    }
                }
            }
        }

        if (($stock != null && $stock != '') || $stock === 0)
        {
            $producto->stock = $stock;
            $bandera=true;

            $cliente = BotCliente::find($producto->cliente_id);
            if($cliente){
                //si el cliente usa productos sin colores y stock
                if($cliente->flag_colores == 0 && $cliente->flag_stock == 1){
                    $colores = Color::
                        where('producto_id',$producto->id)
                        ->with('tipos')
                        ->get();

                    if(count($colores)>0 && count($colores[0]->tipos)>0){
                        $colores[0]->tipos[0]->stock = $stock;
                        $colores[0]->tipos[0]->save();
                    }
                }
            }
        }

        if ($bandera)
        {
            // Almacenamos en la base de datos el registro.
            if ($producto->save()) {
                return response()->json(['message'=>'Producto editado con éxito.',
                    'producto'=>$producto], 200);
            }else{
                return response()->json(['error'=>'Error al actualizar el producto.'], 500);
            }
            
        }
        else
        {
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 304 Not Modified – [No Modificada] Usado cuando el cacheo de encabezados HTTP está activo
            // Este código 304 no devuelve ningún body, así que si quisiéramos que se mostrara el mensaje usaríamos un código 200 en su lugar.
            return response()->json(['error'=>'No se ha modificado ningún dato al producto.'],409);
        }

    }

    public function destroy(Request $request, $id)
    {
        $token_result = $this->validarToken($request);
        if($token_result !== true){
            return response()->json($token_result, 401);
        }

        // Comprobamos si el producto que nos están pasando existe o no.
        $producto = Producto::find($id);

        if(!$producto){
            return response()->json(['error'=>'No existe el producto con id '.$id], 404);          
        } 

        $producto->eliminado = 1;
        $producto->save();

        return response()->json(['message'=>'Se ha eliminado correctamente el producto.'], 200);
    }

    public function setImagen(Request $request, $producto_id)
    {
        $token_result = $this->validarToken($request);
        if($token_result !== true){
            return response()->json($token_result, 401);
        }

        // Primero comprobaremos si estamos recibiendo todos los campos.
        $validator = Validator::make($request->all(),[
            'url'=>'required|string',
        ]);
        if ($validator->fails()) { 
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 422 Unprocessable Entity – [Entidad improcesable] Utilizada para errores de validación.
            return response()->json(['error'=>'Error de validación',
                'detalle'=>$validator->errors(),
            ],422);
        }
        
        $producto = Producto::where('id',$producto_id)->get();
        if(count($producto)==0){
           // Devolvemos un código 409 Conflict. 
            return response()->json(['error'=>'No existe el producto con id '.$producto_id], 409);
        }

        if($newObj=ProductoImagen::create([
            'url'=>$request->input('url'),
            'producto_id'=>$producto_id,
        ])){
           return response()->json(['message'=>'Imagen creada con éxito.',
             'imagen'=>$newObj], 200);
        }else{
            return response()->json(['error'=>'Error al crear la imagen.'], 500);
        }
    }

    public function getImagenes(Request $request, $producto_id)
    {
        $token_result = $this->validarToken($request);
        if($token_result !== true){
            return response()->json($token_result, 401);
        }

        $imagenes = ProductoImagen::
            where('producto_id',$producto_id)
            ->get();

        return response()->json(['imagenes'=>$imagenes], 200);
        
    }

    public function destroyImagen(Request $request, $imagen_id)
    {
        $token_result = $this->validarToken($request);
        if($token_result !== true){
            return response()->json($token_result, 401);
        }

        // Comprobamos si la imagen existe o no.
        $imagen=ProductoImagen::find($imagen_id);

        if (!$imagen)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe la imagen con id '.$imagen_id], 404);
        }
    
        // Eliminamos la imagen.
        $imagen->delete();

        return response()->json(['message'=>'Se ha eliminado correctamente la imagen.'], 200);
    }

    public function setColor(Request $request, $producto_id)
    {
        $token_result = $this->validarToken($request);
        if($token_result !== true){
            return response()->json($token_result, 401);
        }

        $producto = Producto::find($producto_id);

        if(!$producto){
            return response()->json(['error'=>'No existe el producto con id '.$producto_id], 404);          
        }

        $aux = Color::whereNull('eliminado')
            ->where('nombre', $request->input('nombre'))
            ->where('producto_id', $producto_id)
            ->get();

        if(count($aux)!=0){
           // Devolvemos un código 409 Conflict. 
            return response()->json(['error'=>'Ya existe un color con ese nombre asociado al producto.'], 409);
        } 

        $tipos = json_decode($request->input('tipos'));
        if (count($tipos) == 0) { 
            return response()->json(['error'=>'El color '.$request->input('nombre').' debe tener al menos un tipo.'], 409);
        }

        for ($j=0; $j < count($tipos); $j++) { 
            if ($tipos[$j]->stock < 0) { 
                return response()->json(['error'=>'El stock del tipo '.$tipos[$j]->nombre.' color '.$request->input('nombre').', debe ser mayor o igual a cero'], 409);
            } 

            if ($tipos[$j]->precio < 0) { 
                return response()->json(['error'=>'El precio del tipo '.$tipos[$j]->nombre.' color '.$request->input('nombre').', debe ser mayor o igual a cero'], 409);
            } 
        }

        $nuevoColor = Color::create([
            'nombre' => $request->input('nombre'),
            'status' => 1,
            'producto_id' => $producto_id,
            'eliminado' => null,
        ]);

        for ($j=0; $j < count($tipos); $j++) { 
            $nuevoTipo = Tipo::create([
                'nombre' => $tipos[$j]->nombre,
                'status' => 1,
                'precio' => $tipos[$j]->precio,
                'stock' => $tipos[$j]->stock,
                'color_id' => $nuevoColor->id,
                'eliminado' => null,
            ]);
        }

        return response()->json(['message'=>'Color creado con éxito.'], 200);
    }

    public function updateColor(Request $request, $color_id)
    {
        $token_result = $this->validarToken($request);
        if($token_result !== true){
            return response()->json($token_result, 401);
        }

        $color = Color::find($color_id);

        if(!$color){
            return response()->json(['error'=>'No existe el color con id '.$color_id], 404);          
        }

        // Listado de campos recibidos teóricamente.
        $nombre=$request->input('nombre');
        $status=$request->input('status'); 

        if ($nombre != null && $nombre != '')
        {
            $aux = Color::whereNull('eliminado')
                ->where('nombre', $nombre)
                ->where('id', '<>', $color_id)
                ->where('producto_id',$color->producto_id)
                ->get();
            if(count($aux)!=0){
               // Devolvemos un código 409 Conflict. 
                return response()->json(['error'=>'Ya existe otro color con ese nombre asociado al producto.'], 409);
            } 

            $color->nombre = $nombre;
            $bandera=true;
        }

        if (($status != null && $status != '') || $status === 0)
        {

            if ($status == 0) {
                $aux2 = Color::whereNull('eliminado')
                    ->where('id', '<>', $color_id)
                    ->where('producto_id',$color->producto_id)
                    ->get();

                $count_off = 0;
                for ($i=0; $i < count($aux2); $i++) { 
                    if ($aux2[$i]->status == 0) {
                        $count_off = $count_off + 1;
                    }
                }
                if($count_off == count($aux2)){
                   // Devolvemos un código 409 Conflict. 
                    return response()->json(['error'=>'El producto debe tener al menos un color activo.'], 409);
                } 
            }

            $color->status = $status;
            $bandera=true;
        }

        if ($bandera)
        {
            // Almacenamos en la base de datos el registro.
            if ($color->save()) {
                return response()->json(['message'=>'Color editado con éxito.',
                    'color'=>$color], 200);
            }else{
                return response()->json(['error'=>'Error al actualizar el color.'], 500);
            }
            
        }
        else
        {
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 304 Not Modified – [No Modificada] Usado cuando el cacheo de encabezados HTTP está activo
            // Este código 304 no devuelve ningún body, así que si quisiéramos que se mostrara el mensaje usaríamos un código 200 en su lugar.
            return response()->json(['error'=>'No se ha modificado ningún dato al color.'],409);
        }
    
    }

    public function getColores(Request $request, $producto_id)
    {
        $token_result = $this->validarToken($request);
        if($token_result !== true){
            return response()->json($token_result, 401);
        }

        $colores = Color::
            where('producto_id',$producto_id)
            ->with('tipos')
            ->get();

        return response()->json([
            'colores'=>$colores,
        ], 200);
        
    }

    public function getColoresActivos(Request $request, $producto_id)
    {
        $token_result = $this->validarToken($request);
        if($token_result !== true){
            return response()->json($token_result, 401);
        }

        $colores = Color::where('status',1)
            ->where('producto_id',$producto_id)
            ->with('tipos')
            ->get();

        return response()->json([
            'colores'=>$colores
        ], 200);
        
    }

    public function setTipo(Request $request, $color_id)
    {
        $token_result = $this->validarToken($request);
        if($token_result !== true){
            return response()->json($token_result, 401);
        }

        $color = Color::find($color_id);

        if(!$color){
            return response()->json(['error'=>'No existe el color con id '.$color_id], 404);          
        }

        $aux = Tipo::whereNull('eliminado')
                ->where('nombre', $request->input('nombre'))
                ->where('color_id', $color_id)
                ->get();
        if(count($aux)!=0){
           // Devolvemos un código 409 Conflict. 
            return response()->json(['error'=>'Ya existe un tipo con ese nombre asociado al color.'], 409);
        } 

        if ($request->input('stock') < 0) { 
            return response()->json(['error'=>'El stock del tipo debe ser mayor o igual a cero'], 409);
        } 

        if ($request->input('precio') < 0) { 
            return response()->json(['error'=>'El precio del tipo debe ser mayor o igual a cero'], 409);
        } 

        $nuevoTipo = Tipo::create([
            'nombre' => $request->input('nombre'),
            'status' => 1,
            'precio' => $request->input('precio'),
            'stock' => $request->input('stock'),
            'color_id' => $color_id,
            'eliminado' => null,
        ]);

        return response()->json(['message'=>'Tipo creado con éxito.'], 200);
    }

    public function updateTipo(Request $request, $tipo_id)
    {
        $token_result = $this->validarToken($request);
        if($token_result !== true){
            return response()->json($token_result, 401);
        }

        $tipo = Tipo::with('color')->find($tipo_id);

        if(!$tipo){
            return response()->json(['error'=>'No existe el tipo con id '.$tipo_id], 404);          
        }

        // Listado de campos recibidos teóricamente.
        $nombre=$request->input('nombre');
        $status=$request->input('status'); 
        $precio=$request->input('precio');
        $stock=$request->input('stock');

        if ($nombre != null && $nombre != '')
        {
            $aux = Tipo::whereNull('eliminado')
                ->where('nombre', $nombre)
                ->where('id', '<>', $tipo_id)
                ->where('color_id',$tipo->color_id)
                ->get();
            if(count($aux)!=0){
               // Devolvemos un código 409 Conflict. 
                return response()->json([
                    'error'=>'Ya existe otro tipo con ese nombre asociado al color.',
                    'tipo'=>$aux
                ], 409);
            } 

            $tipo->nombre = $nombre;
            $bandera=true;
        }

        if (($status != null && $status != '') || $status === 0)
        {

            if ($status == 0) {
                $aux2 = Tipo::whereNull('eliminado')
                    ->where('id', '<>', $tipo_id)
                    ->where('color_id',$tipo->color_id)
                    ->get();

                $count_off = 0;
                for ($i=0; $i < count($aux2); $i++) { 
                    if ($aux2[$i]->status == 0) {
                        $count_off = $count_off + 1;
                    }
                }
                if($count_off == count($aux2)){
                   // Devolvemos un código 409 Conflict. 
                    return response()->json(['error'=>'El color debe tener al menos un tipo activo.'], 409);
                } 
            }

            $tipo->status = $status;
            $bandera=true;
        }

        if (($precio != null && $precio!='') || $precio === 0)
        {
            if ($precio < 0) { 
                return response()->json(['error'=>'El precio debe ser mayor o igual a cero'], 409);
            } 

            $tipo->precio = $precio;
            $bandera=true;
        }

        if (($stock != null && $stock!='') || $stock === 0)
        {
            if ($stock < 0) { 
                return response()->json(['error'=>'El stock debe ser mayor o igual a cero'], 409);
            }

            $tipo->stock = $stock;
            $bandera=true;
        }

        if ($bandera)
        {
            // Almacenamos en la base de datos el registro.
            if ($tipo->save()) {
                return response()->json(['message'=>'Tipo editado con éxito.',
                    'tipo'=>$tipo], 200);
            }else{
                return response()->json(['error'=>'Error al actualizar el tipo.'], 500);
            }
            
        }
        else
        {
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 304 Not Modified – [No Modificada] Usado cuando el cacheo de encabezados HTTP está activo
            // Este código 304 no devuelve ningún body, así que si quisiéramos que se mostrara el mensaje usaríamos un código 200 en su lugar.
            return response()->json(['error'=>'No se ha modificado ningún dato al tipo.'],409);
        }
    
    }

    public function getTipos(Request $request, $color_id)
    {
        $token_result = $this->validarToken($request);
        if($token_result !== true){
            return response()->json($token_result, 401);
        }

        $tipos = Tipo::
            where('color_id',$color_id)
            ->get();

        return response()->json([
            'tipos'=>$tipos,
            //'tipos_apartados'=>$tipos_apartados
        ], 200);
        
    }

    public function uploadArchivo(Request $request)
    {

        if (!$request->hasFile('archivo')) {
            return response()->json(['error'=>'Archivo no detectado.'], 422);
        }

        //Guardar la imagen y generar el url
        $array_rutas = $this->storeLinkImagen3($request);

        if(!$array_rutas){
            return response()->json(['error'=>'Error al guardar la imagen.'],409);
        }

        $resul = (object) [
            'url' => $array_rutas[0],
            'url_allow_origin' => $array_rutas[1],
        ];

        return response()->json(['message'=>'Imagen agregada con éxito.',
             'imagen'=>$resul], 200);
    }

    public function storeLinkImagen3(Request $request)
    {
        try{

            set_time_limit(500);
        
            $carpeta = 'images_uploads/productos_images/';
            //$url_base = 'http://localhost/publicacionesIA/publicacionesIAAPI/public/';
            $url_base = 'https://apisocial.internow.com.mx/';

            $hoy = date("m.d.y.H.i.s");

            $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."productos_images".DIRECTORY_SEPARATOR;
            $fileName = $hoy.'.jpg';

            $request->file('archivo')->move($destinationPath,$fileName);

            $archivo_ruta = $url_base.$carpeta.$fileName;

            $array_rutas = [
                $archivo_ruta,
                'https://apisocial.internow.com.mx/api/productos_images/allow_origin/'.$fileName
            ];

            return $array_rutas;

        } catch ( Exception $e ){

            //return $e->getMessage();
            return null;

        }
        
    }

}
