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

//facturas
use App\Models\CfdiEmpresa;
use App\Models\CfdiProducto;
use App\Models\CfdiCliente;
use App\Models\CfdiComprobante;
use App\Models\CfdiReceptor;
use App\Models\CfdiConcepto;
use App\Models\CfdiArchivo;

use App\Models\Cfdi40CodigoPostal;
use App\Models\Cfdi40RegimenFiscal;
use App\Models\Cfdi40ProductoServicio;
use App\Models\Cfdi40ClaveUnidad;


//use Hash;
use DB;
//use Validator;

use Carbon\Carbon;

class FacturaAuxController extends Controller
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

    public function generarProductos()
    {

        $empresas = CfdiEmpresa::all();

        for ($i=0; $i < count($empresas); $i++) {

            //Crear el producto asociado a la empresa
            $nuevoProducto=CfdiProducto::create([
                'empresa_id'=>$empresas[$i]->id,
                'ClaveProdServ'=>null,
                'NoIdentificacion'=>null,
                'Cantidad'=>null,
                'ClaveUnidad'=>null,
                'Unidad'=>null,
                'Descripcion'=>null,
                'ValorUnitario'=>null,
                'Importe'=>null,
                'Descuento'=>null,
                'ObjetoImp'=>null,
                'ObjetoImpRet'=>null,
                
            ]);

        }

        // Regresar una respuesta exitosa
        return response('OK', 200);
        
    }

    public function getCatalogoProductos(Request $request)
    {
        $termino = $request->input('termino');

        $objs = Cfdi40ProductoServicio::
            where("id", "like", '%'.$termino.'%')
            ->orWhere("texto", "like", '%'.$termino.'%')
            ->orWhere("similares", "like", '%'.$termino.'%')
            ->get();

        return response()->json(['catalogoClaveProdServ'=>$objs], 200);
    }

    public function getCatalogoUnidades(Request $request)
    {
        $termino = $request->input('termino');

        $objs = Cfdi40ClaveUnidad::
            where("id", "like", '%'.$termino.'%')
            ->orWhere("texto", "like", '%'.$termino.'%')
            ->get();

        return response()->json(['catalogoClaveUnidad'=>$objs], 200);
    }
}
