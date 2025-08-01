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
use App\Models\Pedido;
use App\Models\PedidoDetalle;

use Carbon\Carbon;

use DB;

date_default_timezone_set('America/Mexico_City');

class PedidoController extends Controller
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

    public function indexTest()
    {
        $pedidos = Pedido::
            where('cliente_id',1)
            ->with('detalles', 'detalles.producto', 'detalles.color', 'detalles.tipo')
            ->get();

        for ($i=0; $i < count($pedidos); $i++) { 
            $detalles_aux = [];
            for ($j=0; $j < count($pedidos[$i]->detalles); $j++) { 
                $resul = (object) [
                    'producto' => $pedidos[$i]->detalles[$j]->producto->nombre,
                    'color' => $pedidos[$i]->detalles[$j]->color->nombre,
                    'tipo' => $pedidos[$i]->detalles[$j]->tipo->nombre,
                    'cantidad' => $pedidos[$i]->detalles[$j]->cantidad,
                    'stock' => $pedidos[$i]->detalles[$j]->tipo->stock,
                    'precio_unitario' => $pedidos[$i]->detalles[$j]->precio_unitario,
                ];
                array_push($detalles_aux,$resul);
                $pedidos[$i]->detalles[$j] = null;
            }
            $pedidos[$i]->detalles = [];
            $pedidos[$i]->detalles_bot = $detalles_aux;
        }

        return response()->json([
            'pedidos'=>$pedidos
        ], 200);
    }

    public function storeTest()
    {
        if($nuevoPedido=Pedido::create([
            'bot_id' => 1,
            'cliente_id' => 1,
            'status' => 0,
            'subtotal' => 149.9,
            'envio' => 0,
            'total' =>149.9,

        ])){

            $nuevoDetalle=PedidoDetalle::create([
                'pedido_id' => $nuevoPedido->id,
                'producto_id' => 1,
                'color_id' => 1,
                'tipo_id' => 1,
                'cantidad' => 1,
                'precio_initario' => 149.9,
            ]);

           return response()->json(['message'=>'Pedido creado con éxito.',
             'pedido'=>$nuevoPedido], 200);
        }else{
            return response()->json(['error'=>'Error al crear el pedido.'], 500);
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

        //pedidos en curso
        $pedidos = Pedido::
            //where('cliente_id',$cliente_id)
            where('cliente_id',$obj->id)
            ->where('status',1)
            ->with('detalles', 'detalles.producto', 'detalles.color', 'detalles.tipo')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'cliente'=>$obj,
            'pedidos'=>$pedidos
        ], 200);
        
    }

    public function indexFinalizadosFilter(Request $request, $cliente_id)
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
                'telefono','empresa','flag_colores','flag_stock')
            ->find($cadenaDesencriptada);

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Cliente no encontrado'], 404);
        }

        //pedidos en curso
        $pedidos = Pedido::
            //where('cliente_id',$cliente_id)
            where('cliente_id',$obj->id)
            ->where(DB::raw('DAY(created_at)'),$request->input('dia'))
            ->where(DB::raw('MONTH(created_at)'),$request->input('mes'))
            ->where(DB::raw('YEAR(created_at)'),$request->input('anio'))
            ->where(function ($query) {
                $query
                    ->where('status',2) //finalizado
                    ->orWhere('status',3); //cancelado
            })
            ->with('detalles', 'detalles.producto', 'detalles.color', 'detalles.tipo')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'cliente'=>$obj,
            'pedidos'=>$pedidos
        ], 200);
        
    }

    public function updateFinalizar(Request $request, $id)
    {
        $token_result = $this->validarToken($request);
        if($token_result !== true){
            return response()->json($token_result, 401);
        }

        // Comprobamos si lo que nos están pasando existe o no.
        $pedido = Pedido::find($id);

        if (!$pedido)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe el pedido con id '.$id], 404);
        }

        //finalizar
        $pedido->status = 2;

        // Almacenamos en la base de datos el registro.
        if ($pedido->save()) {
            return response()->json(['message'=>'Pedido finalizado con éxito.',
                'pedido'=>$pedido], 200);
        }else{
            return response()->json(['error'=>'Error al actualizar el pedido.'], 500);
        }

    }

    public function updateCancelar(Request $request, $id)
    {
        $token_result = $this->validarToken($request);
        if($token_result !== true){
            return response()->json($token_result, 401);
        }

        // Comprobamos si lo que nos están pasando existe o no.
        $pedido = Pedido::
            with('detalles', 'detalles.producto', 'detalles.color', 'detalles.tipo')
            ->find($id);

        if (!$pedido)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe el pedido con id '.$id], 404);
        }

        if ($pedido->status == 2)
        {
            return response()->json(['error'=>'El pedido está marcado como finalizado.'], 409);
        }

        if ($pedido->status == 3)
        {
            return response()->json(['error'=>'El pedido ya está marcado como cancelado.'], 409);
        }

        $cliente = BotCliente::find($pedido->cliente_id);

        //reponer inventario
        //si usa colores ó no usa colores pero tiene stock activado
        if($cliente->flag_colores == 1 || ($cliente->flag_colores == 0 && $cliente->flag_stock == 1)){
            for ($i=0; $i < count($pedido->detalles); $i++) { 
                $pedido->detalles[$i]->tipo->stock = $pedido->detalles[$i]->tipo->stock + $pedido->detalles[$i]->cantidad;
                $pedido->detalles[$i]->tipo->save();
            }
        }

        //cancelar
        $pedido->status = 3;

        // Almacenamos en la base de datos el registro.
        if ($pedido->save()) {
            return response()->json(['message'=>'Pedido cancelado con éxito.',
                'pedido'=>$pedido], 200);
        }else{
            return response()->json(['error'=>'Error al actualizar el pedido.'], 500);
        }

    }


  
}
