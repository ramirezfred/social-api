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
use App\Models\Cfdi40FormaPago;
use App\Models\Cfdi40MetodoPago;
use App\Models\Cfdi40UsoCfdi;


//use Hash;
use DB;
//use Validator;

use Carbon\Carbon;


//ejemplo factura cfdi 4.0
// Se desactivan los mensajes de debug
//error_reporting(~(E_WARNING|E_NOTICE));
error_reporting(E_ERROR);

// Se especifica la zona horaria
date_default_timezone_set('America/Mexico_City');

// Se incluye el SDK
//require_once 'sdk2/sdk2.php';
require_once public_path('sdk2/sdk2.php');

class FacturaController extends Controller
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

    public function getClienteEmpresa(Request $request, $cliente_id)
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

        $obj = BotCliente::select('id','empresa','color_a','color_b','color_c','logo')
            //->with('cfdi_empresa.producto')
            ->with(['cfdi_empresa.producto' => function ($query){
                $query->with('mi_clave_prod_serv')
                    ->with('mi_clave_unidad');
            }])
            ->find($cadenaDesencriptada);

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Cliente no encontrado'], 404);
        }

        if($obj->cfdi_empresa){
            $obj->cfdi_empresa->pass = null;    
        }

        return response()->json(['cliente'=>$obj], 200);
    }

    public function getCodigoPostal($cp)
    {
        $obj = Cfdi40CodigoPostal::find($cp);

        if(!$obj){
            return response()->json(['error'=>'Código Postal no disponible en el catálogo.'],404);
        }

        return response()->json(['cp'=>$obj], 200);
    }

    public function getCatalogoRegimen()
    {
        $objs = Cfdi40RegimenFiscal::all();

        return response()->json([
            'catalogoRegimenFiscal'=>$objs
        ], 200);
    }

    public function getCatalogoFormaPago()
    {
        $objs = Cfdi40FormaPago::all();

        return response()->json([
            'catalogoFormaPago'=>$objs
        ], 200);
    }

    public function getCatalogoMetodoPago()
    {
        $objs = Cfdi40MetodoPago::all();

        return response()->json([
            'catalogoMetodoPago'=>$objs
        ], 200);
    }

    public function update(Request $request, $empresa_id)
    {
        $token_result = $this->validarToken($request);
        if($token_result !== true){
            return response()->json($token_result, 401);
        }

        // Comprobamos si la empresa que nos están pasando existe o no.
        $empresa=CfdiEmpresa::find($empresa_id);

        if (!$empresa)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Empresa no encontrada.'], 404);
        }    
        
        // Listado de campos recibidos teóricamente.
        $flag_descuento=$request->input('flag_descuento');
        $flag_retencion=$request->input('flag_retencion');
        $flag_producto=$request->input('flag_producto');
        $Rfc=$request->input('Rfc');
        $RazonSocial=$request->input('RazonSocial');
        $RegimenFiscal=$request->input('RegimenFiscal');
        $CP=$request->input('CP');
        $cer=$request->input('cer');
        $key=$request->input('key');
        $pass=$request->input('pass');

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Actualización parcial de campos.


        if (($flag_descuento != null && $flag_descuento !=  '') || $flag_descuento === 0)
        {
            $empresa->flag_descuento = $flag_descuento;
            $bandera=true;
        }

        if (($flag_retencion != null && $flag_retencion !=  '') || $flag_retencion === 0)
        {
            $empresa->flag_retencion = $flag_retencion;
            $bandera=true;
        }

        if (($flag_producto != null && $flag_producto !=    '') || $flag_producto === 0)
        {
            $empresa->flag_producto = $flag_producto;
            $bandera=true;
        }

        if ($Rfc != null && $Rfc != '')
        {
            // Eliminar espacios en blanco y guiones si los hay
            $Rfc = str_replace([' ', '-'], '', $Rfc);
            $Rfc = strtoupper($Rfc);

            $rfcValido = "/^[A-Z0-9]{12,13}$/";

            if (preg_match($rfcValido, $Rfc)) {
                $empresa->Rfc = $Rfc;
                $bandera=true;
            } else {
                // El Rfc es inválido
                $message = 'Por favor, verifica el Rfc. En el caso de que sea una persona física, este campo debe contener una longitud de 13 posiciones, si se trata de personas morales debe contener una longitud de 12 posiciones.';
                return response()->json(['error'=>$message],409);
            }
            
        }

        if ($RazonSocial != null && $RazonSocial != '')
        {
            $empresa->RazonSocial = strtoupper($RazonSocial);
            $bandera=true;
        }

        if ($RegimenFiscal != null && $RegimenFiscal != '')
        {

            //checar si existe en el catalogo
            $RegimenFiscalBD = Cfdi40RegimenFiscal::find($RegimenFiscal);

            if($RegimenFiscalBD){
                $empresa->RegimenFiscal = $RegimenFiscal;
                $bandera=true; 
            }else{
                // El RegimenFiscal no existe en el catalogo
                $message = 'El Régimen fiscal que ingresaste no está disponible en nuestro catálogo. Por favor, intenta ingresar un Régimen fiscal diferente.';

                return response()->json(['error'=>$message],409);
            }
            
        }

        if ($CP != null && $CP != '')
        {
            
            // Eliminar espacios en blanco y guiones si los hay
            $CP = str_replace([' ', '-'], '', $CP);

            $cpValido = "/^[0-9]{5}$/";

            if (preg_match($cpValido, $CP)) {

                //checar si existe en el catalogo
                $CpBD = Cfdi40CodigoPostal::find($CP);;

                if($CpBD){
                    $empresa->CP = $CP;
                    $bandera=true;
                }else{
                    // El CP no existe en el catalogo
                    $message = 'El código postal que ingresaste no está disponible en nuestro catálogo. Por favor, intenta ingresar un código postal diferente.';
                    return response()->json(['error'=>$message],409);
                }
            } else {
                // El CP es inválido
                $message = 'Por favor, verifica el Código Postal. Este campo es el código postal del domicilio fiscal del contribuyente y debe contener una longitud de 5 posiciones.';
                return response()->json(['error'=>$message],409);
            }
        }

        if ($cer != null && $cer != '')
        {
            $url_old = $empresa->cer;

            $empresa->cer = $cer;
            $bandera=true;

            if($url_old != $cer){
                if($url_old != null && $url_old != ''){
                    //Eliminar el archivo viejo
                    $cadenas = explode('/',$url_old);
                    $destinationPath = public_path().DIRECTORY_SEPARATOR."sdk2".DIRECTORY_SEPARATOR."certificados".DIRECTORY_SEPARATOR;
                    $fileName = $cadenas[count($cadenas)-1];
                    $archivo_ruta = $destinationPath.$fileName;
                    if (file_exists($archivo_ruta)) {
                        unlink($archivo_ruta); // Eliminar el archivo
                    }

                    $archivo_ruta = $destinationPath.$fileName.'.pem';
                    if (file_exists($archivo_ruta)) {
                        unlink($archivo_ruta); // Eliminar el archivo
                    }

                    $archivo_ruta = $destinationPath.$fileName.'.pem.txt';
                    if (file_exists($archivo_ruta)) {
                        unlink($archivo_ruta); // Eliminar el archivo
                    }
                }
            }
        }

        if ($key != null && $key != '')
        {
            $url_old = $empresa->key;

            $empresa->key = $key;
            $bandera=true;

            if($url_old != $key){
                if($url_old != null && $url_old != ''){
                    //Eliminar el archivo viejo
                    $cadenas = explode('/',$url_old);
                    $destinationPath = public_path().DIRECTORY_SEPARATOR."sdk2".DIRECTORY_SEPARATOR."certificados".DIRECTORY_SEPARATOR;
                    $fileName = $cadenas[count($cadenas)-1];
                    $archivo_ruta = $destinationPath.$fileName;
                    if (file_exists($archivo_ruta)) {
                        unlink($archivo_ruta); // Eliminar el archivo
                    }

                    $archivo_ruta = $destinationPath.$fileName.'.pem';
                    if (file_exists($archivo_ruta)) {
                        unlink($archivo_ruta); // Eliminar el archivo
                    }
                }
            }
        }

        if ($pass != null && $pass!='')
        {
            $claveAdicional = config('app.lada_d');
            $cadenaEncriptada = Crypt::encrypt($pass, $claveAdicional);

            $empresa->pass = $cadenaEncriptada;
            $bandera=true;
        }

       
        if ($bandera)
        {
            // Almacenamos en la base de datos el registro.
            if ($empresa->save()) {

                $empresa->pass = null;

                return response()->json(['message'=>'Empresa actualizada.',
                 'empresa'=>$empresa], 200);
            }else{
                return response()->json(['error'=>'Error al actualizar la empresa.'], 500);
            }           
        }
        else
        {
            // Se devuelve un array error con los error encontrados y cabecera HTTP 304 Not Modified – [No Modificada] Usado cuando el cacheo de encabezados HTTP está activo
            // Este código 304 no devuelve ningún body, así que si quisiéramos que se mostrara el mensaje usaríamos un código 200 en su lugar.
            return response()->json(['error'=>'No se ha modificado ningún a la empresa.'],500);
        }
    }

    public function storeArchivo(Request $request)
    {
        $token_result = $this->validarToken($request);
        if($token_result !== true){
            return response()->json($token_result, 401);
        }

        if (!$request->hasFile('archivo')) {
            return response()->json(['error'=>'Archivo no detectado.'], 422);
        }else if(!$request->input('ext')){
            return response()->json(['error'=>'Especifique una extención para el archivo.'], 422);
        }else if(!$request->input('empresa_id')){
            return response()->json(['error'=>'Especifique una empresa.'], 422);
        }

        // Comprobamos si la empresa que nos están pasando existe o no.
        $empresa=CfdiEmpresa::find($request->input('empresa_id'));
        if (!$empresa)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Empresa no encontrada.'], 404);
        } 

        // Genera un nombre de archivo único
        if($request->input('ext') == '.cer'){
            $fileName = 'cer_' . uniqid() . '.cer';
        }else if($request->input('ext') == '.key'){
            $fileName = 'key_' . uniqid() . '.key';
        }else{
            return response()->json(['error'=>'Extención inválida.'], 422);
        }
        
        $destinationPath = public_path().'/sdk2/certificados/';
        $request->file('archivo')->move($destinationPath,$fileName);

        // Obtiene la URL del archivo guardado
        $url = asset('sdk2/certificados/' . $fileName);

        if($request->input('ext') == '.cer'){
            $url_old = $empresa->cer;

            $empresa->cer = $url;

            if($url_old != $url){
                if($url_old != null && $url_old != ''){
                    //Eliminar el archivo viejo
                    $cadenas = explode('/',$url_old);
                    $destinationPath = public_path().DIRECTORY_SEPARATOR."sdk2".DIRECTORY_SEPARATOR."certificados".DIRECTORY_SEPARATOR;
                    $fileName = $cadenas[count($cadenas)-1];
                    $archivo_ruta = $destinationPath.$fileName;
                    if (file_exists($archivo_ruta)) {
                        unlink($archivo_ruta); // Eliminar el archivo
                    }

                    $archivo_ruta = $destinationPath.$fileName.'.pem';
                    if (file_exists($archivo_ruta)) {
                        unlink($archivo_ruta); // Eliminar el archivo
                    }

                    $archivo_ruta = $destinationPath.$fileName.'.pem.txt';
                    if (file_exists($archivo_ruta)) {
                        unlink($archivo_ruta); // Eliminar el archivo
                    }
                }
            }

        }else if($request->input('ext') == '.key'){
            $url_old = $empresa->key;

            $empresa->key = $url;

            if($url_old != $url){
                if($url_old != null && $url_old != ''){
                    //Eliminar el archivo viejo
                    $cadenas = explode('/',$url_old);
                    $destinationPath = public_path().DIRECTORY_SEPARATOR."sdk2".DIRECTORY_SEPARATOR."certificados".DIRECTORY_SEPARATOR;
                    $fileName = $cadenas[count($cadenas)-1];
                    $archivo_ruta = $destinationPath.$fileName;
                    if (file_exists($archivo_ruta)) {
                        unlink($archivo_ruta); // Eliminar el archivo
                    }

                    $archivo_ruta = $destinationPath.$fileName.'.pem';
                    if (file_exists($archivo_ruta)) {
                        unlink($archivo_ruta); // Eliminar el archivo
                    }
                }
            }
        }

        $empresa->save();

        return response()->json([
            'message'=>'Archivo cargado y configurado con éxito.',
            'url'=>$url
         ], 200);
    }

    public function indexEmitidasFilter(Request $request, $cliente_id)
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

        $anio = $request->input('anio');
        $mes = $request->input('mes');
        //$dia = $request->input('dia');

        if($mes >= 1 && $mes <= 9){
            $mes = '0'.$mes;
        }

        // if($dia >= 1 && $dia <= 9){
        //     $dia = '0'.$dia;
        // }

        //$fecha = $anio.'-'.$mes.'-'.$dia;
        $fecha = $anio.'-'.$mes.'-';

        //cotizaciones en emitidas
        $facturas = CfdiComprobante::select('id','cliente_id','status','Serie','Folio','Fecha','Total')
            ->where('cliente_id',$obj->id)
            ->where('status', 1)
            ->where('Fecha', 'like', '%'.$fecha.'%')
            ->with(['receptor' => function ($query){
                $query->select('id','comprobante_id','Rfc','Nombre');
            }])
            ->with(['archivo' => function ($query){
                $query->select('id','comprobante_id','xml_archivo','pdf');
            }])
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'cliente'=>$obj,
            'facturas'=>$facturas
        ], 200);
        
    }

    public function indexCanceladasFilter(Request $request, $cliente_id)
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

        $anio = $request->input('anio');
        $mes = $request->input('mes');
        //$dia = $request->input('dia');

        if($mes >= 1 && $mes <= 9){
            $mes = '0'.$mes;
        }

        // if($dia >= 1 && $dia <= 9){
        //     $dia = '0'.$dia;
        // }

        //$fecha = $anio.'-'.$mes.'-'.$dia;
        $fecha = $anio.'-'.$mes.'-';

        //cotizaciones en emitidas
        $facturas = CfdiComprobante::select('id','cliente_id','status','Serie','Folio','Fecha','Total')
            ->where('cliente_id',$obj->id)
            ->where('status', 2)
            ->where('Fecha', 'like', '%'.$fecha.'%')
            ->with(['receptor' => function ($query){
                $query->select('id','comprobante_id','Rfc','Nombre');
            }])
            ->with(['archivo' => function ($query){
                $query->select('id','comprobante_id','xml_archivo','pdf');
            }])
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'cliente'=>$obj,
            'facturas'=>$facturas
        ], 200);
        
    }

    public function getFactura(Request $request, $factura_id)
    {

        $token_result = $this->validarToken($request);
        if($token_result !== true){
            return response()->json($token_result, 401);
        }

        $factura = CfdiComprobante::
            with(['receptor' => function ($query){
                $query->with('mi_regimen_fiscal')
                    ->with('mi_uso_cfdi');
            }])
            ->with(['conceptos' => function ($query){
                $query->with('mi_clave_prod_serv')
                    ->with('mi_clave_unidad');
            }])
            ->with('impuesto')
            ->with('timbre_fiscal_digital')
            ->with('archivo')
            ->with('mi_forma_pago')
            ->with('mi_metodo_pago')
            ->find($factura_id);

        if(!$factura){
            return response()->json(['error'=>'Factura no encontrada.'],404);
        }

        $TotalImpuestosTrasladados = 0;
        $TotalImpuestosRetenidos = 0;
        $TotalImpuestosRetenidosIva = 0;
        $TotalImpuestosRetenidosIsr = 0;

        for ($i=0; $i < count($factura->conceptos); $i++) { 

            $factura->conceptos[$i]->Impuestos = [];

            if($factura->conceptos[$i]->ObjetoImp == 1){

                $Impuestos = [];

                $factura->conceptos[$i]->ObjetoImp = 'Si obj de impuesto.';
                $Base = $factura->conceptos[$i]->Importe - $factura->conceptos[$i]->Descuento;

                $Importe = number_format(($Base * 0.16), 2, '.', '');
                $TotalImpuestosTrasladados = $TotalImpuestosTrasladados + $Importe;

                $resul = (object) [
                    'Impuesto' => "IVA",
                    'Tipo' => "Traslado",
                    'Base' => $Base,
                    'TipoFactor' => "Tasa",
                    'TasaOCuota' => "16.00%",
                    'Importe' => $Importe
                ];
                array_push($Impuestos,$resul);

                if($factura->conceptos[$i]->ObjetoImpRet == 1){

                    $retencionIva = $Base * ($factura->TasaIva/100);
                    $retencionIva = number_format(($retencionIva), 2, '.', '');
                    $resul = (object) [
                        'Impuesto' => "IVA",
                        'Tipo' => "Retención",
                        'Base' => $Base,
                        'TipoFactor' => "Tasa",
                        'TasaOCuota' => $factura->TasaIva."%",
                        'Importe' => $retencionIva
                    ];
                    array_push($Impuestos,$resul);

                    $retencionIsr = $Base * ($factura->TasaIsr/100);
                    $retencionIsr = number_format(($retencionIsr), 2, '.', '');
                    $resul = (object) [
                        'Impuesto' => "ISR",
                        'Tipo' => "Retención",
                        'Base' => $Base,
                        'TipoFactor' => "Tasa",
                        'TasaOCuota' => $factura->TasaIsr."%",
                        'Importe' => $retencionIsr
                    ];
                    array_push($Impuestos,$resul);

                    $TotalImpuestosRetenidosIva = $TotalImpuestosRetenidosIva + $retencionIva;
                    $TotalImpuestosRetenidosIsr = $TotalImpuestosRetenidosIsr + $retencionIsr;
                    $TotalImpuestosRetenidos = $TotalImpuestosRetenidos + $TotalImpuestosRetenidosIva + $TotalImpuestosRetenidosIsr;

                }

                $factura->conceptos[$i]->Impuestos = $Impuestos;


            }
        }
        $factura->TotalImpuestosTrasladados = number_format($TotalImpuestosTrasladados, 2, '.', '');
        $factura->TotalImpuestosRetenidos = number_format($TotalImpuestosRetenidos, 2, '.', '');
        $factura->TotalImpuestosRetenidosIva = number_format($TotalImpuestosRetenidosIva, 2, '.', '');
        $factura->TotalImpuestosRetenidosIsr = number_format($TotalImpuestosRetenidosIsr, 2, '.', '');
 
        $cliente = BotCliente::find($factura->cliente_id);

        $emisor = CfdiEmpresa::
            with('mi_regimen_fiscal')
            ->find($factura->cliente_id);

        $emisor->cer = null;
        $emisor->key = null;
        $emisor->pass = null;

        return response()->json([
            'emisor' => $emisor,
            'factura'=>$factura,
        ], 200);

        
    }

    public function cancelarFactura(Request $request, $factura_id)
    {

        $token_result = $this->validarToken($request);
        if($token_result !== true){
            return response()->json($token_result, 401);
        }

        $factura = CfdiComprobante::
            with(['receptor' => function ($query){
                $query->with('mi_regimen_fiscal')
                    ->with('mi_uso_cfdi');
            }])
            ->with(['conceptos' => function ($query){
                $query->with('mi_clave_prod_serv')
                    ->with('mi_clave_unidad');
            }])
            ->with('impuesto')
            ->with('timbre_fiscal_digital')
            ->with('archivo')
            ->with('mi_forma_pago')
            ->with('mi_metodo_pago')
            ->find($factura_id);

        if(!$factura){
            return response()->json(['error'=>'Factura no encontrada.'],404);
        }

        if($factura->status == 2){
            return response()->json(['error'=>'Su factura ya está marcada como cancelada.'],409);
        }

        if(!$factura->timbre_fiscal_digital){
            return response()->json(['error'=>'Su factura no tiene un timbre para cancelar.'],409);
        }
 
        $cliente = BotCliente::find($factura->cliente_id);

        $emisor = CfdiEmpresa::
            with('mi_regimen_fiscal')
            ->find($factura->cliente_id);

        // $datos['PAC']['usuario'] = "DEMO700101XXX";
        // $datos['PAC']['pass'] = "DEMO700101XXX";

        $datos['PAC']['usuario'] = 'AUMA9101171B4';
        $datos['PAC']['pass'] = 'AUMA9101171B41234';

        $datos['modulo']="cancelacion2022"; 
        $datos['accion']="cancelar"; 

        // $datos["produccion"]="NO"; 

        $datos['produccion'] = 'SI';

        //$datos["xml"]="../../timbrados/cfdi_ejemplo_factura.xml";
        $datos["uuid"]=$factura->timbre_fiscal_digital->UUID;
        $datos["rfc"] =$emisor->Rfc;

        // La cadena cifrada
        $cadenaEncriptada = $emisor->pass;
        $claveAdicional = config('app.lada_d');
        $cadenaDesencriptada = Crypt::decrypt($cadenaEncriptada, $claveAdicional);

        if (preg_match('/[^\w\s]/', $cadenaDesencriptada)) {
            $datos["password"] = utf8_encode($cadenaDesencriptada);
        } else {
            $datos["password"] = $cadenaDesencriptada;
        }

        //$datos["motivo"]="02";
        $datos["motivo"]=$request->input('motivo');
        //$datos["folioSustitucion"]="";
        $datos["b64Cer"]=str_replace("https://apisocial.internow.com.mx/", "", $emisor->cer);
        $datos["b64Key"]=str_replace("https://apisocial.internow.com.mx/", "", $emisor->key);

        $res = mf_ejecuta_modulo($datos);

        file_put_contents('webhook_log_cfdi_cancelar.txt', print_r($res, true), FILE_APPEND);

        // echo "<pre>";
        // print_r($res);
        // echo "<pre>";

        if(
            isset($res['codigo_mf_texto']) &&
            isset($res['codigo_respuesta_sat_texto']) &&
            $res['codigo_mf_texto'] == "OK" &&
            $res['codigo_respuesta_sat_texto'] != "No Existe" 
        ){
            //Pasar a cancelada
            $factura->status = 2;
            $factura->save();

            return response()->json([
                'message'=>'Factura cancelada con éxito.'
            ], 200);
        }
        else if(
            isset($res['codigo_mf_texto']) &&
            isset($res['codigo_respuesta_sat_texto']) &&
            $res['codigo_mf_texto'] == "OK" &&
            $res['codigo_respuesta_sat_texto'] == "No Existe" 
        )
        {
        
            return response()->json([
                'error'=>'Su factura no existe en el portal del SAT. Si emites una factura electrónica y quieres cancelarla, debes esperar al menos 72 horas antes de hacerlo.'
            ],409);

        }
        else {
            return response()->json([
                'error'=>'Error al conectar con la librería de timbrado.'
            ],500);
        }
        
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

    public function updateProductoPorDefecto(Request $request, $empresa_id)
    {
        $token_result = $this->validarToken($request);
        if($token_result !== true){
            return response()->json($token_result, 401);
        }

        // Comprobamos si la empresa que nos están pasando existe o no.
        $empresa=CfdiEmpresa::find($empresa_id);

        if (!$empresa)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Empresa no encontrada.'], 404);
        }    

        $producto=CfdiProducto::
            where('empresa_id',$empresa_id)
            ->first();

        if (!$producto)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Producto no encontrado.'], 404);
        } 
        
        // Listado de campos recibidos teóricamente.
        $ClaveProdServ=$request->input('ClaveProdServ');
        $ClaveUnidad=$request->input('ClaveUnidad');

        if ($ClaveProdServ == null || $ClaveProdServ == '')
        {
            return response()->json(['error'=>'Clave de Producto o Servicio inválida'],409);
        }

        if ($ClaveUnidad == null || $ClaveUnidad == '')
        {
            return response()->json(['error'=>'Clave de Unidad inválida'],409);
        }

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Actualización parcial de campos.
        if (true)
        {

            //checar si existe en el catalogo
            $ProductoServicioBD = Cfdi40ProductoServicio::
                where('id',$ClaveProdServ)
                ->first();

            if($ProductoServicioBD){
                $producto->ClaveProdServ = $ProductoServicioBD->id_aux;
                $bandera=true; 
            }else{
                // El Producto no existe en el catalogo
                $message = 'La Clave de Producto o Servicio que ingresaste no está disponible en nuestro catálogo. Por favor, intenta ingresar una Clave de Producto o Servicio diferente.';

                return response()->json(['error'=>$message],409);
            }
            
        }

        if (true)
        {

            //checar si existe en el catalogo
            $ClaveUnidadBD = Cfdi40ClaveUnidad::
                where('id',$ClaveUnidad)
                ->first();

            if($ClaveUnidadBD){
                $producto->ClaveUnidad = $ClaveUnidadBD->id_aux;
                $bandera=true; 
            }else{
                // El Producto no existe en el catalogo
                $message = 'La Clave de Unidad que ingresaste no está disponible en nuestro catálogo. Por favor, intenta ingresar una Clave de Unidad diferente.';

                return response()->json(['error'=>$message],409);
            }
            
        }

       
        if ($bandera)
        {
            // Almacenamos en la base de datos el registro.
            if ($producto->save()) {

                $empresa->flag_producto = 1;
                $empresa->save();

                return response()->json(['message'=>'Producto actualizado.',
                 'producto'=>$producto], 200);
            }else{
                return response()->json(['error'=>'Error al actualizar el producto.'], 500);
            }           
        }
        else
        {
            // Se devuelve un array error con los error encontrados y cabecera HTTP 304 Not Modified – [No Modificada] Usado cuando el cacheo de encabezados HTTP está activo
            // Este código 304 no devuelve ningún body, así que si quisiéramos que se mostrara el mensaje usaríamos un código 200 en su lugar.
            return response()->json(['error'=>'No se ha modificado ningún dato al producto.'],500);
        }
    }

    public function getClientesPorRfc(Request $request)
    {
        $termino = $request->input('termino');
        $empresa_id = $request->input('empresa_id');

        $objs = CfdiCliente::
            where("empresa_id", $empresa_id)
            ->where("Rfc", "like", '%'.$termino.'%')
            ->with('mi_regimen_fiscal')
            ->with('mi_uso_cfdi')
            ->get();

        return response()->json(['clientes'=>$objs], 200);
    }

    public function getAllClientes(Request $request)
    {
        $empresa_id = $request->input('empresa_id');

        $objs = CfdiCliente::
            where("empresa_id", $empresa_id)
            ->with('mi_regimen_fiscal')
            ->with('mi_uso_cfdi')
            ->get();

        return response()->json(['clientes'=>$objs], 200);
    }

    public function getCatalogoUsoCfdi()
    {
        $objs = Cfdi40UsoCfdi::all();

        return response()->json([
            'catalogoUsoCfdi'=>$objs
        ], 200);
    }

    
}
