<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Cache;

use Illuminate\Support\Facades\Crypt;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;

use DateTime;
use Carbon\Carbon;

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
use App\Models\Cfdi40UsoCfdi;
use App\Models\Cfdi40FormaPago;
use App\Models\Cfdi40MetodoPago;
use App\Models\CfdiTimbreFiscalDigital;

use App\Models\Cfdi40ProductoServicio;
use App\Models\Cfdi40ClaveUnidad;

use DB;

//ejemplo factura cfdi 4.0
// Se desactivan los mensajes de debug
error_reporting(~(E_WARNING|E_NOTICE));
//error_reporting(E_ALL);

// Se especifica la zona horaria
date_default_timezone_set('America/Mexico_City');

// Se incluye el SDK
//require_once 'sdk2/sdk2.php';
require_once public_path('sdk2/sdk2.php');

class TimbradoController extends Controller
{
    public function timbrar($factura_id)
    {

        $factura = CfdiComprobante::
            with(['receptor' => function ($query){
                $query->with('mi_uso_cfdi');
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

        $emisor = CfdiEmpresa::
            with('mi_regimen_fiscal')
            ->where('bot_cliente_id', $factura->cliente_id)
            ->first();

        // Se especifica la version de CFDi 4.0
        $datos['version_cfdi'] = '4.0';
        $datos['validacion_local']='NO';

        // Ruta del XML Timbrado
        $datos['cfdi']='sdk2/timbrados/cfdi_ejemplo_factura4.xml';

        // Ruta del XML de Debug
        $datos['xml_debug']='sdk2/timbrados/sin_timbrar_ejemplo_factura4.xml';

        // Credenciales de Timbrado
        $datos['PAC']['usuario'] = 'DEMO700101XXX';
        $datos['PAC']['pass'] = 'DEMO700101XXX';
        $datos['PAC']['produccion'] = 'NO';

        // $datos['PAC']['usuario'] = 'AUMA9101171B4';
        // $datos['PAC']['pass'] = 'AUMA9101171B41234';
        // $datos['PAC']['produccion'] = 'SI';

        // Rutas y clave de los CSD
        $datos['conf']['cer'] = str_replace("https://apisocial.internow.com.mx/", "", $emisor->cer);
        $datos['conf']['key'] = str_replace("https://apisocial.internow.com.mx/", "", $emisor->key);
        
        // La cadena cifrada
        $cadenaEncriptada = $emisor->pass;
        $claveAdicional = config('app.lada_d');
        $cadenaDesencriptada = Crypt::decrypt($cadenaEncriptada, $claveAdicional);

        if (preg_match('/[^\w\s]/', $cadenaDesencriptada)) {
           $datos['conf']['pass'] = utf8_encode($cadenaDesencriptada);
        } else {
            $datos['conf']['pass'] = $cadenaDesencriptada;
        }


        // Datos de la Factura
        // if($factura->CondicionesDePago != null && $factura->CondicionesDePago != "" && $factura->receptor->Rfc != "XAXX010101000"){
        //     if (preg_match('/[^\w\s]/', $factura->CondicionesDePago)) {
        //         $datos['factura']['condicionesDePago'] = utf8_encode($factura->CondicionesDePago);
        //     } else {
        //         $datos['factura']['condicionesDePago'] = $factura->CondicionesDePago;
        //     }
        // }

        if($factura->Descuento > 0){
            $datos['factura']['descuento'] = $factura->Descuento;
        }
        
        $datos['factura']['fecha_expedicion'] = $factura->Fecha;
        $datos['factura']['folio'] = $factura->Folio;

        $FormaPago = $factura->FormaPago;
        if($FormaPago >= 1 && $FormaPago <= 8){
            $FormaPago = '0'.$FormaPago;
        }

        $datos['factura']['forma_pago'] = $FormaPago;
        $datos['factura']['LugarExpedicion'] = $emisor->CP;
        $datos['factura']['metodo_pago'] = $factura->mi_metodo_pago->id;
        $datos['factura']['moneda'] = 'MXN';
        $datos['factura']['serie'] = $factura->Serie;
        $datos['factura']['subtotal'] = $factura->Subtotal;
        //$datos['factura']['tipocambio'] = 1;
        $datos['factura']['tipocomprobante'] = 'I';
        $datos['factura']['total'] = $factura->Total;
        ////$datos['factura']['RegimenFiscal'] = '601';
        $datos['factura']['Exportacion'] = '01';


        // Datos del Emisor
        $datos['emisor']['rfc'] = $emisor->Rfc;
        
        if (preg_match('/[^\w\s]/', $emisor->RazonSocial)) {
            $datos['emisor']['nombre'] = utf8_encode($emisor->RazonSocial);
        } else {
            $datos['emisor']['nombre'] = $emisor->RazonSocial;
        }

        $datos['emisor']['RegimenFiscal'] = $emisor->RegimenFiscal;
        //$datos['emisor']['FacAtrAdquirente'] = 'ACCEM SERVICIOS EMPRESARIALES SC';

        // Datos del Receptor
        $datos['receptor']['rfc'] = $factura->receptor->Rfc;
        
        if (preg_match('/[^\w\s]/', $emisor->RazonSocial)) {
            $datos['receptor']['nombre'] = utf8_encode($factura->receptor->Nombre);
        } else {
            $datos['receptor']['nombre'] = $factura->receptor->Nombre;
        }

        $datos['receptor']['UsoCFDI'] = $factura->receptor->mi_uso_cfdi->id;
        //opcional
        if($factura->receptor->Rfc == "XAXX010101000"){
            $datos['receptor']['DomicilioFiscalReceptor'] = $emisor->CP;
            $factura->receptor->DomicilioFiscalReceptor = $emisor->CP;
            $factura->receptor->save();
        }else{
            $datos['receptor']['DomicilioFiscalReceptor'] = $factura->receptor->DomicilioFiscalReceptor;
        }
        
        ////$datos['receptor']['ResidenciaFiscal']= 'MEX';
        ////$datos['receptor']['NumRegIdTrib'] = 'B';
        $datos['receptor']['RegimenFiscalReceptor'] = $factura->receptor->RegimenFiscalReceptor;

        if($factura->receptor->Rfc == "XAXX010101000"){
            //Informacion Global
            $datos['InformacionGlobal']['Periodicidad'] = '02'; //Semanal
            $datos['InformacionGlobal']['Meses'] = date("m");
            $datos['InformacionGlobal']['Año'] = date("Y");
        }

        $TotalImpuestosTrasladados = 0;
        $TotalImpuestosRetenidos = 0;
        $TotalImpuestosRetenidosIva = 0;
        $TotalImpuestosRetenidosIsr = 0;

        $BaseTraslados = 0;
        $BaseRetenciones = 0;

        // Se agregan los conceptos
        for ($i=0; $i < count($factura->conceptos); $i++) { 
            $datos['conceptos'][$i]['cantidad'] = $factura->conceptos[$i]->Cantidad;
            $datos['conceptos'][$i]['unidad'] = $factura->conceptos[$i]->Unidad;
            //$datos['conceptos'][$i]['ID'] = "1726";
            
            // if (preg_match('/[^\w\s]/', $factura->conceptos[$i]->Descripcion)) {
            //     $datos['conceptos'][$i]['descripcion'] = utf8_encode($factura->conceptos[$i]->Descripcion);
            // } else {
            //     $datos['conceptos'][$i]['descripcion'] = $factura->conceptos[$i]->Descripcion;
            // }

            //$datos['conceptos'][$i]['descripcion'] = $factura->conceptos[$i]->Descripcion;

            //$datos['conceptos'][$i]['descripcion'] = iconv('UTF-8', 'ISO-8859-1', $factura->conceptos[$i]->Descripcion);

            $datos['conceptos'][$i]['descripcion'] = utf8_encode($factura->conceptos[$i]->Descripcion);

            //$datos['conceptos'][$i]['descripcion'] = mb_convert_encoding($factura->conceptos[$i]->Descripcion, 'UTF-8', 'auto');

            //$datos['conceptos'][$i]['descripcion'] = mb_convert_encoding($factura->conceptos[$i]->Descripcion, "ISO-8859-1");

            $cadena_ansi = "";
            for ($j = 0; $j < strlen($factura->conceptos[$i]->Descripcion); $j++) {
                $cadena_ansi .= chr(ord($factura->conceptos[$i]->Descripcion[$j]));
            }
            $datos['conceptos'][$i]['descripcion'] = $cadena_ansi;

            //$datos['conceptos'][$i]['descripcion'] = mb_convert_encoding($factura->conceptos[$i]->Descripcion, 'UTF-8');
            
            $datos['conceptos'][$i]['valorunitario'] = $factura->conceptos[$i]->ValorUnitario;
            $datos['conceptos'][$i]['importe'] = $factura->conceptos[$i]->Importe;

            if($factura->conceptos[$i]->Descuento > 0){
                $datos['conceptos'][0]['Descuento'] = $factura->conceptos[$i]->Descuento;
            }

            $datos['conceptos'][$i]['ClaveProdServ'] = $factura->conceptos[$i]->mi_clave_prod_serv->id;
            $datos['conceptos'][$i]['ClaveUnidad'] = $factura->conceptos[$i]->mi_clave_unidad->id;

            $datos['conceptos'][$i]['ObjetoImp'] = '01'; //no

            if($factura->conceptos[$i]->ObjetoImp == 1){
                $datos['conceptos'][$i]['ObjetoImp'] = '02'; //si

                $Base = $factura->conceptos[$i]->Importe - $factura->conceptos[$i]->Descuento;
                $BaseTraslados = $BaseTraslados + $Base;

                $Importe = number_format(($Base * 0.16), 2, '.', '');
                $TotalImpuestosTrasladados = $TotalImpuestosTrasladados + $Importe;

                $datos['conceptos'][$i]['Impuestos']['Traslados'][0]['Base'] = $Base;
                $datos['conceptos'][$i]['Impuestos']['Traslados'][0]['Impuesto'] = '002';
                $datos['conceptos'][$i]['Impuestos']['Traslados'][0]['TipoFactor'] = 'Tasa';
                $datos['conceptos'][$i]['Impuestos']['Traslados'][0]['TasaOCuota'] = '0.160000';
                $datos['conceptos'][$i]['Impuestos']['Traslados'][0]['Importe'] = $Importe;

                if($factura->conceptos[$i]->ObjetoImpRet == 1){

                    $BaseRetenciones = $BaseRetenciones + $Base;
                    $retencionIva = $Base * ($factura->TasaIva/100);
                    $retencionIva = number_format(($retencionIva), 2, '.', '');

                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][0]['Base'] = $Base;
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][0]['Impuesto'] = '002';
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][0]['TipoFactor'] = 'Tasa';
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][0]['TasaOCuota'] = $factura->TasaIva/100;
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][0]['Importe'] = $retencionIva;

                    $retencionIsr = $Base * ($factura->TasaIsr/100);
                    $retencionIsr = number_format(($retencionIsr), 2, '.', '');

                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][1]['Base'] = $Base;
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][1]['Impuesto'] = '001';
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][1]['TipoFactor'] = 'Tasa';
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][1]['TasaOCuota'] = $factura->TasaIsr/100;
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][1]['Importe'] = $retencionIsr;

                    $TotalImpuestosRetenidosIva = $TotalImpuestosRetenidosIva + $retencionIva;
                    $TotalImpuestosRetenidosIsr = $TotalImpuestosRetenidosIsr + $retencionIsr;
                    $TotalImpuestosRetenidos = $TotalImpuestosRetenidos + $TotalImpuestosRetenidosIva + $TotalImpuestosRetenidosIsr;

                }
            }
            
        }

        // Se agregan los Impuestos
        if($factura->conceptos[0]->ObjetoImp == 1){

            $datos['impuestos']['TotalImpuestosTrasladados'] = number_format($TotalImpuestosTrasladados, 2, '.', '');

            if($factura->conceptos[0]->ObjetoImpRet == 1){

                $datos['impuestos']['TotalImpuestosRetenidos'] = number_format($TotalImpuestosRetenidos, 2, '.', '');

            }


            $Importe = number_format(($BaseTraslados * 0.16), 2, '.', '');

            //Neta
            // if($factura->Tipo == 1){
            //     if($Importe < number_format($TotalImpuestosTrasladados, 2, '.', '')){
            //         $Importe = number_format((($BaseTraslados+0.05) * 0.16), 2, '.', '');
            //     }
            // }

            //Validacion adicional
            if($Importe != number_format($TotalImpuestosTrasladados, 2, '.', '')){
                $Importe = number_format($TotalImpuestosTrasladados, 2, '.', '');
            }

            $datos['impuestos']['translados'][0]['Base'] = $BaseTraslados;
            $datos['impuestos']['translados'][0]['impuesto'] = '002';
            $datos['impuestos']['translados'][0]['tasa'] = '0.160000';
            $datos['impuestos']['translados'][0]['importe'] = $Importe;
            $datos['impuestos']['translados'][0]['TipoFactor'] = 'Tasa';

            if($factura->conceptos[0]->ObjetoImpRet == 1){

                $datos['impuestos']['retenciones'][0]['impuesto'] = '002';
                $datos['impuestos']['retenciones'][0]['importe'] = number_format($TotalImpuestosRetenidosIva, 2, '.', '');

                $datos['impuestos']['retenciones'][1]['impuesto'] = '001';
                $datos['impuestos']['retenciones'][1]['importe'] = number_format($TotalImpuestosRetenidosIsr, 2, '.', '');

            }

            
        }

        //dd($datos);

        echo "<pre>";
        print_r($datos);
        echo "</pre>";

        //return 1;

        //echo "<pre>"; echo arr2cs($datos); echo "</pre>".die();
        // Se ejecuta el SDK
        //$res = mf_genera_cfdi($datos);
        $res = mf_genera_cfdi4($datos);

        file_put_contents('webhook_log_cfdi_timbrado.txt', print_r($res, true), FILE_APPEND);

        ///////////    MOSTRAR RESULTADOS DEL ARRAY $res   ///////////

        //dd($res);

        echo "<h1>Respuesta Generar XML y Timbrado</h1>";
        foreach ($res AS $variable => $valor) {
            $valor = htmlentities($valor);
            $valor = str_replace('&lt;br/&gt;', '<br/>', $valor);
            echo "<b>[$variable]=</b>$valor<hr>";
        }
        return 0;

        //en caso de que no timbre
        if(
            isset($res['codigo_mf_texto']) &&
            isset($res['cancelada']) &&
            isset($res['abortar']) && 
            isset($res['error_debug_log_respuesta']) &&
            $res['codigo_mf_texto'] != null && $res['codigo_mf_texto'] != "" &&
            $res['cancelada'] == "SI" &&
            $res['abortar'] == 1 &&
            $res['error_debug_log_respuesta'] != null && $res['error_debug_log_respuesta'] != "" 
        ){
            return $res['codigo_mf_texto'];
        }
        //en caso de que si timbre
        else if(
            isset($res['cfdi']) &&
            isset($res['cancelada']) &&
            isset($res['abortar']) && 
            $res['cancelada'] == "NO" &&
            $res['abortar'] != 1
        )
        {
            
            $archivo_xml = $res['cfdi'];
            $archivo_png = $res['png'];

            $nuevoObjArchivo=CfdiArchivo::create([
                'comprobante_id'=>$factura->id,
                'xml'=>$archivo_xml,
                'png'=>$archivo_png,
            ]);

            // Genera un nombre de archivo único
            $nombreArchivo = 'xml_' . uniqid() . '.xml';

            // Guarda el XML en la carpeta "public" del directorio raíz
            Storage::disk('public_root')->put('xmls_facturas/'.$nombreArchivo, $archivo_xml);

            // Obtiene la URL del archivo guardado
            $url = asset('xmls_facturas/' . $nombreArchivo);

            DB::table('cfdi_archivos')
            ->where('comprobante_id', $factura->id)
            ->update([
                'xml_archivo' => $url,
            ]);

            $factura->Sello = $res['representacion_impresa_sello'][0];
            $factura->NoCertificado = $res['representacion_impresa_certificado_no'];
            $factura->save();

            $nuevoTimbreFiscalDigital=CfdiTimbreFiscalDigital::create([
                'comprobante_id'=>$factura->id,
                'Version'=>null,
                'UUID'=>$res['uuid'],
                'FechaTimbrado'=>$res['representacion_impresa_fecha_timbrado'][0],
                'RfcProvCertif'=>null,
                'SelloCFD'=>null,
                'NoCertificadoSAT'=>$res['representacion_impresa_certificadoSAT'][0],
                'SelloSAT'=>$res['representacion_impresa_selloSAT'][0],
                
            ]);

            //para debug
            $factura->timbre_fiscal_digital = $nuevoTimbreFiscalDigital;

            return 1;

           
        }
        else if(
            isset($res['codigo_mf_texto']) &&
            isset($res['error_debug_log_respuesta']) &&
            $res['codigo_mf_texto'] != null && $res['codigo_mf_texto'] != "" &&
            $res['error_debug_log_respuesta'] != null && $res['error_debug_log_respuesta'] != "" 
        ){
            return $res['codigo_mf_texto'];
        }
        else {
            return 'Error al conectar con la librería de timbrado';      
        }

    }


    public function timbrar2($factura_id)
    {

        //ini_set("default_charset", "ISO-8859-1");
        //ini_set("default_charset", "UTF-8");

        // Esto le dice a PHP que usaremos cadenas UTF-8 hasta el final
        // mb_internal_encoding('UTF-8');
         
        // // Esto le dice a PHP que generaremos cadenas UTF-8
        // mb_http_output('UTF-8');

        $factura = CfdiComprobante::
            with(['receptor' => function ($query){
                $query->with('mi_uso_cfdi');
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

        $emisor = CfdiEmpresa::
            with('mi_regimen_fiscal')
            ->where('bot_cliente_id', $factura->cliente_id)
            ->first();

        // Se especifica la version de CFDi 4.0
        $datos['version_cfdi'] = '4.0';
        $datos['validacion_local']='NO';

        // Ruta del XML Timbrado
        $datos['cfdi']='sdk2/timbrados/cfdi_ejemplo_factura4.xml';

        // Ruta del XML de Debug
        $datos['xml_debug']='sdk2/timbrados/sin_timbrar_ejemplo_factura4.xml';

        // Credenciales de Timbrado
        $datos['PAC']['usuario'] = 'DEMO700101XXX';
        $datos['PAC']['pass'] = 'DEMO700101XXX';
        $datos['PAC']['produccion'] = 'NO';

        // $datos['PAC']['usuario'] = 'AUMA9101171B4';
        // $datos['PAC']['pass'] = 'AUMA9101171B41234';
        // $datos['PAC']['produccion'] = 'SI';

        // Rutas y clave de los CSD
        $datos['conf']['cer'] = str_replace("https://apisocial.internow.com.mx/", "", $emisor->cer);
        $datos['conf']['key'] = str_replace("https://apisocial.internow.com.mx/", "", $emisor->key);
        
        // La cadena cifrada
        $cadenaEncriptada = $emisor->pass;
        $claveAdicional = config('app.lada_d');
        $cadenaDesencriptada = Crypt::decrypt($cadenaEncriptada, $claveAdicional);

        $datos['conf']['pass'] = $cadenaDesencriptada;


        // Datos de la Factura
        // if($factura->CondicionesDePago != null && $factura->CondicionesDePago != "" && $factura->receptor->Rfc != "XAXX010101000"){
        //     if (preg_match('/[^\w\s]/', $factura->CondicionesDePago)) {
        //         $datos['factura']['condicionesDePago'] = utf8_encode($factura->CondicionesDePago);
        //     } else {
        //         $datos['factura']['condicionesDePago'] = $factura->CondicionesDePago;
        //     }
        // }

        if($factura->Descuento > 0){
            $datos['factura']['descuento'] = $factura->Descuento;
        }
        
        $datos['factura']['fecha_expedicion'] = $factura->Fecha;
        $datos['factura']['folio'] = $factura->Folio;

        $FormaPago = $factura->FormaPago;
        if($FormaPago >= 1 && $FormaPago <= 8){
            $FormaPago = '0'.$FormaPago;
        }

        $datos['factura']['forma_pago'] = $FormaPago;
        $datos['factura']['LugarExpedicion'] = $emisor->CP;
        $datos['factura']['metodo_pago'] = $factura->mi_metodo_pago->id;
        $datos['factura']['moneda'] = 'MXN';
        $datos['factura']['serie'] = $factura->Serie;
        $datos['factura']['subtotal'] = $factura->Subtotal;
        //$datos['factura']['tipocambio'] = 1;
        $datos['factura']['tipocomprobante'] = 'I';
        $datos['factura']['total'] = $factura->Total;
        ////$datos['factura']['RegimenFiscal'] = '601';
        $datos['factura']['Exportacion'] = '01';


        // Datos del Emisor
        $datos['emisor']['rfc'] = $emisor->Rfc;
        
        $datos['emisor']['nombre'] = $emisor->RazonSocial;

        $datos['emisor']['RegimenFiscal'] = $emisor->RegimenFiscal;
        //$datos['emisor']['FacAtrAdquirente'] = 'ACCEM SERVICIOS EMPRESARIALES SC';

        // Datos del Receptor
        $datos['receptor']['rfc'] = $factura->receptor->Rfc;
        
        $datos['receptor']['nombre'] = $factura->receptor->Nombre;

        $datos['receptor']['UsoCFDI'] = $factura->receptor->mi_uso_cfdi->id;
        //opcional
        if($factura->receptor->Rfc == "XAXX010101000"){
            $datos['receptor']['DomicilioFiscalReceptor'] = $emisor->CP;
            $factura->receptor->DomicilioFiscalReceptor = $emisor->CP;
            $factura->receptor->save();
        }else{
            $datos['receptor']['DomicilioFiscalReceptor'] = $factura->receptor->DomicilioFiscalReceptor;
        }
        
        ////$datos['receptor']['ResidenciaFiscal']= 'MEX';
        ////$datos['receptor']['NumRegIdTrib'] = 'B';
        $datos['receptor']['RegimenFiscalReceptor'] = $factura->receptor->RegimenFiscalReceptor;

        if($factura->receptor->Rfc == "XAXX010101000"){
            //Informacion Global
            $datos['InformacionGlobal']['Periodicidad'] = '02'; //Semanal
            $datos['InformacionGlobal']['Meses'] = date("m");
            $datos['InformacionGlobal']['Año'] = date("Y");
            //$datos['InformacionGlobal']['AÃ±o'] = date("Y");
            //$datos['InformacionGlobal']['A\u00c3\u00b1o'] = date("Y");
        }

        $TotalImpuestosTrasladados = 0;
        $TotalImpuestosRetenidos = 0;
        $TotalImpuestosRetenidosIva = 0;
        $TotalImpuestosRetenidosIsr = 0;

        $BaseTraslados = 0;
        $BaseRetenciones = 0;

        // Se agregan los conceptos
        for ($i=0; $i < count($factura->conceptos); $i++) { 
            $datos['conceptos'][$i]['cantidad'] = $factura->conceptos[$i]->Cantidad;
            $datos['conceptos'][$i]['unidad'] = $factura->conceptos[$i]->Unidad;
            //$datos['conceptos'][$i]['ID'] = "1726";
            
            $datos['conceptos'][$i]['descripcion'] = $factura->conceptos[$i]->Descripcion;
            //$datos['conceptos'][$i]['descripcion'] = "Cigarros & ' \" perros ñ";

            //$datos['conceptos'][$i]['descripcion'] = utf8_encode('esto es una prueba año');
            //$datos['conceptos'][$i]['descripcion'] = "Cigarros & ' \" perros A\u00c3\u00b1o";

            $datos['conceptos'][$i]['valorunitario'] = $factura->conceptos[$i]->ValorUnitario;
            $datos['conceptos'][$i]['importe'] = $factura->conceptos[$i]->Importe;

            if($factura->conceptos[$i]->Descuento > 0){
                $datos['conceptos'][0]['Descuento'] = $factura->conceptos[$i]->Descuento;
            }

            $datos['conceptos'][$i]['ClaveProdServ'] = $factura->conceptos[$i]->mi_clave_prod_serv->id;
            $datos['conceptos'][$i]['ClaveUnidad'] = $factura->conceptos[$i]->mi_clave_unidad->id;

            $datos['conceptos'][$i]['ObjetoImp'] = '01'; //no

            if($factura->conceptos[$i]->ObjetoImp == 1){
                $datos['conceptos'][$i]['ObjetoImp'] = '02'; //si

                $Base = $factura->conceptos[$i]->Importe - $factura->conceptos[$i]->Descuento;
                $BaseTraslados = $BaseTraslados + $Base;

                $Importe = number_format(($Base * 0.16), 2, '.', '');
                $TotalImpuestosTrasladados = $TotalImpuestosTrasladados + $Importe;

                $datos['conceptos'][$i]['Impuestos']['Traslados'][0]['Base'] = $Base;
                $datos['conceptos'][$i]['Impuestos']['Traslados'][0]['Impuesto'] = '002';
                $datos['conceptos'][$i]['Impuestos']['Traslados'][0]['TipoFactor'] = 'Tasa';
                $datos['conceptos'][$i]['Impuestos']['Traslados'][0]['TasaOCuota'] = '0.160000';
                $datos['conceptos'][$i]['Impuestos']['Traslados'][0]['Importe'] = $Importe;

                if($factura->conceptos[$i]->ObjetoImpRet == 1){

                    $BaseRetenciones = $BaseRetenciones + $Base;
                    $retencionIva = $Base * ($factura->TasaIva/100);
                    $retencionIva = number_format(($retencionIva), 2, '.', '');

                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][0]['Base'] = $Base;
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][0]['Impuesto'] = '002';
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][0]['TipoFactor'] = 'Tasa';
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][0]['TasaOCuota'] = $factura->TasaIva/100;
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][0]['Importe'] = $retencionIva;

                    $retencionIsr = $Base * ($factura->TasaIsr/100);
                    $retencionIsr = number_format(($retencionIsr), 2, '.', '');

                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][1]['Base'] = $Base;
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][1]['Impuesto'] = '001';
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][1]['TipoFactor'] = 'Tasa';
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][1]['TasaOCuota'] = $factura->TasaIsr/100;
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][1]['Importe'] = $retencionIsr;

                    $TotalImpuestosRetenidosIva = $TotalImpuestosRetenidosIva + $retencionIva;
                    $TotalImpuestosRetenidosIsr = $TotalImpuestosRetenidosIsr + $retencionIsr;
                    $TotalImpuestosRetenidos = $TotalImpuestosRetenidos + $TotalImpuestosRetenidosIva + $TotalImpuestosRetenidosIsr;

                }
            }
            
        }

        // Se agregan los Impuestos
        if($factura->conceptos[0]->ObjetoImp == 1){

            $datos['impuestos']['TotalImpuestosTrasladados'] = number_format($TotalImpuestosTrasladados, 2, '.', '');

            if($factura->conceptos[0]->ObjetoImpRet == 1){

                $datos['impuestos']['TotalImpuestosRetenidos'] = number_format($TotalImpuestosRetenidos, 2, '.', '');

            }


            $Importe = number_format(($BaseTraslados * 0.16), 2, '.', '');

            //Neta
            if($factura->Tipo == 1){
                if($Importe < number_format($TotalImpuestosTrasladados, 2, '.', '')){
                    $Importe = number_format((($BaseTraslados+0.05) * 0.16), 2, '.', '');
                }
            }

            $datos['impuestos']['translados'][0]['Base'] = $BaseTraslados;
            $datos['impuestos']['translados'][0]['impuesto'] = '002';
            $datos['impuestos']['translados'][0]['tasa'] = '0.160000';
            $datos['impuestos']['translados'][0]['importe'] = $Importe;
            $datos['impuestos']['translados'][0]['TipoFactor'] = 'Tasa';

            if($factura->conceptos[0]->ObjetoImpRet == 1){

                $datos['impuestos']['retenciones'][0]['impuesto'] = '002';
                $datos['impuestos']['retenciones'][0]['importe'] = number_format($TotalImpuestosRetenidosIva, 2, '.', '');

                $datos['impuestos']['retenciones'][1]['impuesto'] = '001';
                $datos['impuestos']['retenciones'][1]['importe'] = number_format($TotalImpuestosRetenidosIsr, 2, '.', '');

            }

            
        }

        //dd($datos);

        echo "<pre>";
        print_r($datos);
        echo "</pre>";

        //return 1;

        //echo "<pre>"; echo arr2cs($datos); echo "</pre>".die();
        // Se ejecuta el SDK
        //$res = mf_genera_cfdi($datos);
        $res = mf_genera_cfdi4($datos);

        file_put_contents('webhook_log_cfdi_timbrado.txt', print_r($res, true), FILE_APPEND);

        ///////////    MOSTRAR RESULTADOS DEL ARRAY $res   ///////////

        //dd($res);

        echo "<h1>Respuesta Generar XML y Timbrado</h1>";
        foreach ($res AS $variable => $valor) {
            $valor = htmlentities($valor);
            $valor = str_replace('&lt;br/&gt;', '<br/>', $valor);
            echo "<b>[$variable]=</b>$valor<hr>";
        }
        return 0;

        //en caso de que no timbre
        if(
            isset($res['codigo_mf_texto']) &&
            isset($res['cancelada']) &&
            isset($res['abortar']) && 
            isset($res['error_debug_log_respuesta']) &&
            $res['codigo_mf_texto'] != null && $res['codigo_mf_texto'] != "" &&
            $res['cancelada'] == "SI" &&
            $res['abortar'] == 1 &&
            $res['error_debug_log_respuesta'] != null && $res['error_debug_log_respuesta'] != "" 
        ){
            return $res['codigo_mf_texto'];
        }
        //en caso de que si timbre
        else if(
            isset($res['cfdi']) &&
            isset($res['cancelada']) &&
            isset($res['abortar']) && 
            $res['cancelada'] == "NO" &&
            $res['abortar'] != 1
        )
        {
            
            $archivo_xml = $res['cfdi'];
            $archivo_png = $res['png'];

            $nuevoObjArchivo=CfdiArchivo::create([
                'comprobante_id'=>$factura->id,
                'xml'=>$archivo_xml,
                'png'=>$archivo_png,
            ]);

            // Genera un nombre de archivo único
            $nombreArchivo = 'xml_' . uniqid() . '.xml';

            // Guarda el XML en la carpeta "public" del directorio raíz
            Storage::disk('public_root')->put('xmls_facturas/'.$nombreArchivo, $archivo_xml);

            // Obtiene la URL del archivo guardado
            $url = asset('xmls_facturas/' . $nombreArchivo);

            DB::table('cfdi_archivos')
            ->where('comprobante_id', $factura->id)
            ->update([
                'xml_archivo' => $url,
            ]);

            $factura->Sello = $res['representacion_impresa_sello'][0];
            $factura->NoCertificado = $res['representacion_impresa_certificado_no'];
            $factura->save();

            $nuevoTimbreFiscalDigital=CfdiTimbreFiscalDigital::create([
                'comprobante_id'=>$factura->id,
                'Version'=>null,
                'UUID'=>$res['uuid'],
                'FechaTimbrado'=>$res['representacion_impresa_fecha_timbrado'][0],
                'RfcProvCertif'=>null,
                'SelloCFD'=>null,
                'NoCertificadoSAT'=>$res['representacion_impresa_certificadoSAT'][0],
                'SelloSAT'=>$res['representacion_impresa_selloSAT'][0],
                
            ]);

            //para debug
            $factura->timbre_fiscal_digital = $nuevoTimbreFiscalDigital;

            return 1;

           
        }
        else if(
            isset($res['codigo_mf_texto']) &&
            isset($res['error_debug_log_respuesta']) &&
            $res['codigo_mf_texto'] != null && $res['codigo_mf_texto'] != "" &&
            $res['error_debug_log_respuesta'] != null && $res['error_debug_log_respuesta'] != "" 
        ){
            return $res['codigo_mf_texto'];
        }
        else {
            return 'Error al conectar con la librería de timbrado';      
        }

    }

   
}
