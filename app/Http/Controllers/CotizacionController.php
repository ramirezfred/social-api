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
use App\Models\Cotizacion;
use App\Models\CotizacionGasto;

use Carbon\Carbon;

use DB;

date_default_timezone_set('America/Mexico_City');

class CotizacionController extends Controller
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

        //cotizaciones en curso
        $cotizaciones = Cotizacion::
            //where('cliente_id',$cliente_id)
            where('cliente_id',$obj->id)
            ->where('status',1)
            ->with('gastos')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'cliente'=>$obj,
            'cotizaciones'=>$cotizaciones
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

        //cotizaciones en curso
        $cotizaciones = Cotizacion::
            //where('cliente_id',$cliente_id)
            where('cliente_id',$obj->id)
            //->where(DB::raw('DAY(created_at)'),$request->input('dia'))
            ->where(DB::raw('MONTH(created_at)'),$request->input('mes'))
            ->where(DB::raw('YEAR(created_at)'),$request->input('anio'))
            ->where(function ($query) {
                $query
                    ->where('status',2) //finalizado
                    ->orWhere('status',3); //cancelado
            })
            ->with('gastos')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'cliente'=>$obj,
            'cotizaciones'=>$cotizaciones
        ], 200);
        
    }

    public function updateFinalizar(Request $request, $id)
    {
        $token_result = $this->validarToken($request);
        if($token_result !== true){
            return response()->json($token_result, 401);
        }

        // Comprobamos si lo que nos están pasando existe o no.
        $cotizacion = Cotizacion::find($id);

        if (!$cotizacion)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe la cotización con id '.$id], 404);
        }

        //finalizar
        $cotizacion->status = 2;

        // Almacenamos en la base de datos el registro.
        if ($cotizacion->save()) {
            return response()->json(['message'=>'Cotización finalizado con éxito.',
                'cotizacion'=>$cotizacion], 200);
        }else{
            return response()->json(['error'=>'Error al actualizar el cotización.'], 500);
        }

    }

    public function updateCancelar(Request $request, $id)
    {
        $token_result = $this->validarToken($request);
        if($token_result !== true){
            return response()->json($token_result, 401);
        }

        // Comprobamos si lo que nos están pasando existe o no.
        $cotizacion = Cotizacion::find($id);

        if (!$cotizacion)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe la cotización con id '.$id], 404);
        }

        if ($cotizacion->status == 2)
        {
            return response()->json(['error'=>'La cotización está marcada como finalizada.'], 409);
        }

        if ($cotizacion->status == 3)
        {
            return response()->json(['error'=>'La cotización ya está marcada como cancelada.'], 409);
        }

        //cancelar
        $cotizacion->status = 3;

        // Almacenamos en la base de datos el registro.
        if ($cotizacion->save()) {
            return response()->json(['message'=>'Cotización cancelada con éxito.',
                'cotizacion'=>$cotizacion], 200);
        }else{
            return response()->json(['error'=>'Error al actualizar la cotización.'], 500);
        }

    }

}
