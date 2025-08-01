<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\View;
//use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

use Exception;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

use App\Models\User;
use App\Models\SocialBrand;
use App\Models\SocialNetwork;
use App\Models\SocialPost;
use App\Models\SocialImage;
use App\Models\Sistema;
use App\Models\SocialFrame;

use DB;
use DateTime;
use DateInterval;

use Carbon\Carbon;

date_default_timezone_set('America/Mexico_City');

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;

use Illuminate\Support\Facades\Crypt;

use App\Http\Traits\ApiMetaTrait;
use App\Http\Traits\VpsTrait;
//use App\Http\Traits\EmailTrait;
use App\Http\Traits\ApiOpenAiTrait;
use App\Http\Traits\BotFunctionsTrait;

//use App\Mail\MyTestMail;

use Spatie\Browsershot\Browsershot;
use Barryvdh\DomPDF\Facade\Pdf;
use Imagick;

use App\Models\Pedido;
use App\Models\PedidoDetalle;

use App\Models\Cotizacion;
use App\Models\CotizacionGasto;

use App\Models\BotCliente;

use App\Models\Cfdi40CodigoPostal;
use App\Models\Cfdi40UsoCfdi;
use App\Models\Cfdi40FormaPago;
use App\Models\Cfdi40MetodoPago;
use App\Models\Cfdi40RegimenFiscal;
use App\Models\Cfdi40ProductoServicio;
use App\Models\Cfdi40ClaveUnidad;

use App\Models\CfdiCliente;
use App\Models\CfdiEmpresa;
use App\Models\CfdiProducto;
use App\Models\CfdiComprobante;


use Mail;
use Session;
use Redirect;
use Swift_SmtpTransport;
use Swift_Mailer;

class PruebasController extends Controller
{

    use ApiMetaTrait;
    use VpsTrait;
    //use EmailTrait;
    use ApiOpenAiTrait;
    use BotFunctionsTrait;

    public function checkCatalogo($modelo,$frase){

        $unidades = [
            "bit",/*"are",*/"mol","uno","rad",
            "tex","var","rem","tue","pie",
            "clo","kit",/*"lux",*/"bel",/*"mil",*/
            "mes",/*"nil",*/"mho",/*"ohm",*/"rhe",
            "par","red","rod"
        ];

        $acentos = [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'Á' => 'A',
            'É' => 'E',
            'Í' => 'I',
            'Ó' => 'O',
            'Ú' => 'U',
        ];

        //$frase = "Personas Físicas";
        $userInput = strtr($frase, $acentos);
        $palabrasClave = explode(" ", strtolower($userInput));

        // Consulta en la base de datos para obtener todos los textos
        if($modelo == 1){
            $textosEnBD = Cfdi40CodigoPostal::all();
        }else if($modelo == 2){
            $textosEnBD = Cfdi40RegimenFiscal::all();
        }else if($modelo == 3){
            $textosEnBD = Cfdi40UsoCfdi::all();
        }else if($modelo == 4){
            $textosEnBD = Cfdi40FormaPago::all();
        }else if($modelo == 5){
            $textosEnBD = Cfdi40MetodoPago::all();
        }else if($modelo == 6){

            $textosEnBD = Cfdi40ProductoServicio::
                where('id', $frase)
                ->orWhere('texto', 'like', '%'.$frase.'%')
                ->get();

            // Ordena el arreglo
            $n = count($textosEnBD);
            for ($i = 0; $i < $n - 1; $i++) {
                for ($j = 0; $j < $n - $i - 1; $j++) {
                    if (strlen($textosEnBD[$j]->texto) > strlen($textosEnBD[$j + 1]->texto)) {
                        // Intercambiar $textosEnBD[$j] y $textosEnBD[$j + 1]
                        $temp = $textosEnBD[$j];
                        $textosEnBD[$j] = $textosEnBD[$j + 1];
                        $textosEnBD[$j + 1] = $temp;
                    }
                }
            }

        }else if($modelo == 7){

            $textosEnBD = Cfdi40ClaveUnidad::
                where('id', $frase)
                ->orWhere('texto', 'like', '%'.$frase.'%')
                ->get();

            // Ordena el arreglo
            $n = count($textosEnBD);
            for ($i = 0; $i < $n - 1; $i++) {
                for ($j = 0; $j < $n - $i - 1; $j++) {
                    if (strlen($textosEnBD[$j]->texto) > strlen($textosEnBD[$j + 1]->texto)) {
                        // Intercambiar $textosEnBD[$j] y $textosEnBD[$j + 1]
                        $temp = $textosEnBD[$j];
                        $textosEnBD[$j] = $textosEnBD[$j + 1];
                        $textosEnBD[$j + 1] = $temp;
                    }
                }
            }

        }else{
            return null;
        }
        
        // Itera a través de los textos 
        foreach ($textosEnBD as $textoEnBD) {
            $textoEnBD->coincidencias = 0;
            // Convierte el texto de la BD a minúsculas
            if($modelo == 1){
                $texto = strtolower($textoEnBD->id);
            }else if($modelo == 2 || $modelo == 3 || $modelo == 4 || $modelo == 5){
                $texto = strtolower($textoEnBD->texto); 
            }else if($modelo == 6){
                if (preg_match('/^[0-9]{8}$/', $frase)) {
                    //La cadena tiene exactamente 8 dígitos.
                    $texto = strtolower($textoEnBD->id);
                } else {
                    //La cadena no cumple con el formato de 8 dígitos.
                    $texto = strtolower($textoEnBD->texto);
                }
            }else if($modelo == 7){

                // Inicializamos una variable para verificar si la entrada del usuario es diferente a todas las cadenas
                $esDiferente = true;

                // Recorremos el array de cadenas
                foreach ($unidades as $cadena) {
                    if ($cadena === strtolower($frase)) {
                        // Si encontramos una coincidencia, establecemos $esDiferente en falso y salimos del bucle
                        $esDiferente = false;
                        break;
                    }
                }

                /*
                si tiene 3 o menos caracteres 
                y es diferente de las unidades de 3 caracteres
                */
                if (strlen($frase) <= 3 && $esDiferente) {
                    //Es una clave.
                    $texto = strtolower($textoEnBD->id);
                } else {
                    //Es un texto.
                    $texto = strtolower($textoEnBD->texto);
                }
            }
            $texto = strtr($texto, $acentos);
            
            foreach ($palabrasClave as $palabra) {
                if (strpos($texto, strtolower($palabra)) !== false) {
                    $textoEnBD->coincidencias = $textoEnBD->coincidencias + 1;
                }
            }
        }

        // Ordena el arreglo
        $n = count($textosEnBD);
        for ($i = 0; $i < $n - 1; $i++) {
            for ($j = 0; $j < $n - $i - 1; $j++) {
                if ($textosEnBD[$j]->coincidencias < $textosEnBD[$j + 1]->coincidencias) {
                    // Intercambiar $textosEnBD[$j] y $textosEnBD[$j + 1]
                    $temp = $textosEnBD[$j];
                    $textosEnBD[$j] = $textosEnBD[$j + 1];
                    $textosEnBD[$j + 1] = $temp;
                }
            }
        }

        if(count($textosEnBD) > 0 && $textosEnBD[0]->coincidencias !== 0){
            return $textosEnBD[0];
        }else{
            //return null;
            return response()->json(['message'=>'Clave no encontrada.'], 200);
        }
    }

    public function catTest()
    {
        //$cat = Cfdi40UsoCfdi::find('D03');

        // $cat = Cfdi40FormaPago::find(3);

        // $cat2 = Cfdi40MetodoPago::find(2);

        // //$cat_all = Cfdi40UsoCfdi::all();

        // $cat_all = Cfdi40FormaPago::all();

        // $cat_all2 = Cfdi40MetodoPago::all();

        // $clienteCurso = CfdiCliente::
        //     where('empresa_id',1)
        //     ->where('status', 0)
        //     ->with('mi_regimen_fiscal')
        //     ->with('mi_uso_cfdi')
        //     ->first();

        $empresa = CfdiEmpresa::
            with(['producto' => function ($query){
                $query->with('mi_clave_prod_serv')
                    ->with('mi_clave_unidad');
            }])
            ->with('mi_regimen_fiscal')
            ->where('bot_cliente_id', 1)
            ->first();

        $pedidoCurso = CfdiComprobante::
            where('cliente_id',1)
            ->where('status', 0)
            ->with('receptor')
            ->with('conceptos')
            ->with('impuesto')
            ->with('timbre_fiscal_digital')
            ->with('archivo')
            ->with('mi_forma_pago')
            ->with('mi_metodo_pago')
            ->first();

        $fecha = date('Y-m-d\TH:i:s', time() - (60*60));

        return response()->json([
            'Fecha'=>date('Y-m-d\TH:i:s', time() - (60*60)),
            'empresa'=>$empresa,
            //'clienteCurso'=>$clienteCurso,
            'pedidoCurso'=>$pedidoCurso,
            // 'cat'=>$cat,
            // 'cat2'=>$cat2,
            // 'cat_all'=>$cat_all,
            // 'cat_all2'=>$cat_all2,
        ], 200);
    }

    public function cotizacionPdf($cotizacion_id)
    {

        set_time_limit(500);

        $cotizacion = Cotizacion::
            with('gastos')
            ->find($cotizacion_id);

        if(!$cotizacion){
            return response()->json(['error'=>'Cotización no encontrada.'],404);
        }

        $cliente = BotCliente::find($cotizacion->cliente_id);

        $rgb = $this->hexToRgb($cliente->color_a);

        $data = [
            'r' => $rgb['r'],
            'g' => $rgb['g'],
            'b' => $rgb['b'],
            'header' => $cliente->header,
            'footer' => $cliente->footer,
            'cotizacion' => $cotizacion
        ];

        //$pdf = Pdf::loadView('cotizaciones.cotizacion', $data);
        // Crea una instancia de Pdf y establece el tamaño de papel en hoja carta
        $pdf = Pdf::loadView('cotizaciones.cotizacion', $data)->setPaper('letter');
        $pdfContent = $pdf->output();

        // Genera un nombre de archivo único
        $nombreArchivo = 'pdf_' . uniqid() . '.pdf';

        // Guarda el PDF en la carpeta "public" del directorio raíz
        Storage::disk('public_root')->put('pdfs_cotizaciones/'.$nombreArchivo, $pdf->output());

        // Obtiene la URL del archivo guardado
        $url = asset('pdfs_cotizaciones/' . $nombreArchivo);

        return $url;
    }

    public function mostrarVistaPedido()
    {
        $pedido = Pedido::
            with('detalles', 'detalles.producto', 'detalles.color', 'detalles.tipo')
            ->find(1);

        $datos = [
            'pedido' => $pedido
        ];

        return view('pedidos.pedidoA', $datos);
    }

    public function mostrarVistaCotizacion($id)
    {
        $cotizacion = Cotizacion::
            with('gastos')
            ->find($id);

        if(!$cotizacion){
            return response()->json(['error'=>'Cotización no encontrada.'],404);
        }

        $cliente = BotCliente::find($cotizacion->cliente_id);

        $rgb = $this->hexToRgb($cliente->color_a);

        $data = [
            'r' => $rgb['r'],
            'g' => $rgb['g'],
            'b' => $rgb['b'],
            'header' => $cliente->header,
            'footer' => $cliente->footer,
            'cotizacion' => $cotizacion
        ];

        return view('cotizaciones.cotizacion', $data);
    }

    public function mostrarVistaFactura($id)
    {
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
            ->find($id);

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

        $emisor = CfdiEmpresa::with('producto')
            ->with('mi_regimen_fiscal')
            ->find($factura->cliente_id);

        // return response()->json([
        //     'emisor' => $emisor,
        //     'factura'=>$factura,
        // ], 200);

        $data = [
            'header' => $cliente->header,
            'footer' => $cliente->footer,
            'emisor' => $emisor,
            'factura' => $factura
        ];

        return view('facturas.factura', $data);
    }

    public function facturaPdf($factura_id)
    {

        set_time_limit(500);

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

        $emisor = CfdiEmpresa::with('mi_regimen_fiscal')->find($factura->cliente_id);

        // return response()->json([
        //     'emisor' => $emisor,
        //     'factura'=>$factura,
        // ], 200);

        $data = [
            'header' => $cliente->header,
            'footer' => $cliente->footer,
            'emisor' => $emisor,
            'factura' => $factura
        ];

        //$pdf = Pdf::loadView('cotizaciones.cotizacion', $data);
        // Crea una instancia de Pdf y establece el tamaño de papel en hoja carta
        $pdf = Pdf::loadView('facturas.factura', $data)->setPaper('letter');
        $pdfContent = $pdf->output();

        // Genera un nombre de archivo único
        $nombreArchivo = 'pdf_' . uniqid() . '.pdf';

        // Guarda el PDF en la carpeta "public" del directorio raíz
        Storage::disk('public_root')->put('pdfs_facturas/'.$nombreArchivo, $pdf->output());

        // Obtiene la URL del archivo guardado
        $url = asset('pdfs_facturas/' . $nombreArchivo);

        return $url;
    }

    public function hexToRgb($hex) {
        // Elimina cualquier carácter no deseado del valor hexadecimal
        $hex = preg_replace('/[^a-f0-9]/i', '', $hex);

        // Verifica si el valor hexadecimal tiene 3 o 6 caracteres y ajusta si es necesario
        if (strlen($hex) == 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        // Convierte el valor hexadecimal a valores RGB
        $r = hexdec($hex[0] . $hex[1]);
        $g = hexdec($hex[2] . $hex[3]);
        $b = hexdec($hex[4] . $hex[5]);

        // Devuelve un arreglo con los valores RGB
        return array('r' => $r, 'g' => $g, 'b' => $b);
    }

    public function generarPdf()
    {
        $data = ['title' => 'Mi PDF'];
        $pdf = Pdf::loadView('pedidos.pedidoA', $data);
        $pdfContent = $pdf->output();

        // Genera un nombre de archivo único
        $nombreArchivo = 'pdf_' . uniqid() . '.pdf';

        // Guarda el PDF en la carpeta "public" del directorio raíz
        Storage::disk('public_root')->put('pdfs/'.$nombreArchivo, $pdf->output());

        // Obtiene la URL del archivo guardado
        $url = asset('pdfs/' . $nombreArchivo);

        return $url;
    }

    public function hora(){

        $date = Carbon::now();
        $dia = $date->dayOfWeek;
        $hora = $date->hour;
        $minutos = $date->minute;

        if($hora == 0){
            $hora = 23;
        }/*else{
            $hora = $hora - 1;
        }*/

        return response()->json([
            'dia'=>$dia,
            'hora'=>$hora,
            'minutos'=>$minutos,
        ], 200);
        
    }

    public function emailTest($factura_id)
    {
        // $details = [ ];

        // return view('test.index', $details);

        $factura = CfdiComprobante::select('id','cliente_id')
            ->with(['cliente_bot' => function ($query){
                $query->select('id','color_a','color_b','color_c','logo');
            }])
            ->with(['receptor' => function ($query){
                $query->select('id','comprobante_id','Rfc','Nombre','Email');
            }])
            ->with(['archivo' => function ($query){
                $query->select('id','comprobante_id','xml_archivo','pdf');
            }])
            ->find($factura_id);

        return $factura;

        if(!$factura){
            return response()->json(['error'=>'Factura no encontrada.'],404);
        }
        
        $details = [

            'logo' => $factura->cliente_bot->logo,

            'color_a' => $factura->cliente_bot->color_a,

            'color_b' => $factura->cliente_bot->color_b,

            'color_c' => $factura->cliente_bot->color_c,

            'Nombre' => $factura->receptor->Nombre,

            'Rfc' => $factura->receptor->Rfc,

        ];

        $attachment1 = $factura->archivo->pdf;
        $attachment2 = $factura->archivo->xml_archivo;

        //return view('emails.factura', $details);

        \Mail::to('ramirez.fred016@gmail.com')->send(new \App\Mail\NuevaFacturaEmail($details,$attachment1,$attachment2));

        dd("Email is Sent.");

    }

    public function encrypt(Request $request)
    {

        //Generar código alatorio
        $salt = 'abcdefghijklmnopqrstuvwxyz1234567890';
        $rand = '';
        $i = 0;
        $length = 5;
        while ($i < $length) {
            //Loop hasta que el string aleatorio contenga la longitud ingresada.
            $num = rand() % strlen($salt);
            $tmp = substr($salt, $num, 1);
            $rand = $rand . $tmp;
            $i++;
        }
        $codigo = $rand;

        $cadena = $request->input('cadena').$codigo;
        //$cadena = '2';

        //$claveAdicional = 'myrandomkey1234567890abcdefghijklmnop';
        $claveAdicional = config('app.lada_b');

        $cadenaEncriptada = Crypt::encrypt($cadena, $claveAdicional);

        $cadenaDesencriptada = Crypt::decrypt($cadenaEncriptada, $claveAdicional);

        return response()->json([
            'cadenaEncriptada'=>$cadenaEncriptada,
            'cadenaDesencriptada'=>$cadenaDesencriptada,
        ], 200);
    }

    public function decrypt(Request $request)
    {
 
        $cadenaEncriptada = $request->input('cadena');

        $claveAdicional = config('app.lada_b');

        $cadenaDesencriptada = Crypt::decrypt($cadenaEncriptada, $claveAdicional);

        $cadenaDesencriptada = substr($cadenaDesencriptada, 0, -5);

        return response()->json([
            'cadenaDesencriptada'=>$cadenaDesencriptada,
        ], 200);
    }

    public function testToken()
    {

        $user=User::find(10);

        $token = JWTAuth::fromUser($user);

        $nomFunction = '_verCitas';
        $resp = $this->$nomFunction();

        return response()->json([
            'user'=>$user,
            'token'=>$token,
            'resp'=>$resp,
        ], 200);
    }

    public function validarToken(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            return response()->json(['user' => $user], 200);

        } catch (Exception $e) {
            if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenInvalidException){
                return response()->json(['error' => 'Token is Invalid'], 401);
            }else if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenExpiredException){
                return response()->json(['error' => 'Token is Expired'], 401);
            }else{
                return response()->json(['error' => 'Authorization Token not found'], 401);
            }
        }
    }

    public function notificarNewClientes(){

        $clientes = BotCliente::select('id','telefono','empresa','count_alertas','fecha_alerta')
            ->where(function ($query) {
               $query
                   ->whereNull('count_alertas')
                   ->orWhere('count_alertas', '<', 3);
               })
            ->whereNotNull('fecha_alerta')
            // ->where(function ($query) {
            //    $query
            //        ->whereNull('empresa')
            //        ->orWhere('empresa','');
            //    })
            ->where('id',2)
            ->get();

        //fecha actual
        $date = Carbon::now();
        //$date = Carbon::create(2023, 12, 5, 12, 00);

        for ($i=0; $i < count($clientes); $i++) { 
            $diferencia = $date->diffInHours($clientes[$i]->fecha_alerta);

            if($diferencia >= 8){

                //enviar alerta

                $clientes[$i]->fecha_alerta = $date;
                $clientes[$i]->count_alertas = $clientes[$i]->count_alertas + 1;
                $clientes[$i]->save();

            }
        }

        return response()->json([
            'date'=>$date,
            'clientes'=>$clientes
        ], 200);

    }

    public function catalogoVistaGoopy()
    {

        set_time_limit(500);


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.goopy.app/pruebas/ropa/inventario_pdf");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        $err = curl_error($ch);

        curl_close($ch);

        if ($err) {
            //echo "cURL Error #:" . $err;

            return response()->json([
                'error'=>'Error al conectar con Meta',
                'goopy'=>$err
            ], 409);

        } else {

            $goopy_obj = json_decode($response);

            $catalogo = $goopy_obj->productos_ropa;
          
            // return response()->json([
            //     'catalogo'=>$catalogo,
            // ], 200);

            $data = [
                'tipo_catalogo'=>2,
                'catalogo'=>$catalogo,
            ];

            return view('goopy.catalogo', $data);

        } 
        

    }

    public function catalogoPdfGoopy()
    {

        set_time_limit(600);


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.goopy.app/pruebas/ropa/inventario_pdf");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        $err = curl_error($ch);

        curl_close($ch);

        if ($err) {
            //echo "cURL Error #:" . $err;

            return response()->json([
                'error'=>'Error al conectar con Meta',
                'goopy'=>$err
            ], 409);

        } else {

            $goopy_obj = json_decode($response);

            $catalogo = $goopy_obj->productos_ropa;
          
            // return response()->json([
            //     'catalogo'=>$catalogo,
            // ], 200);

            // Comprimir las imágenes antes de pasarlas a la vista
            $countProductos = count($catalogo);
            for ($i = 0; $i < $countProductos; $i++) {
                $producto = $catalogo[$i];
                $countImagenes = count($producto->imagenes);
                for ($j = 0; $j < $countImagenes; $j++) {
                    $imagen =& $producto->imagenes[$j];
                    // Ruta de la imagen original
                    //$imagenPath = public_path($imagen->url);
                    $imagenPath = $imagen->url;

                    // Crear un objeto Imagick a partir de la imagen original
                    $imagick = new Imagick($imagenPath);

                    // Establecer la calidad deseada (valores entre 0 y 100)
                    $imagick->setImageCompressionQuality(30);

                    // // Guardar la imagen comprimida en una ubicación temporal
                    // $tempImagePath = tempnam(sys_get_temp_dir(), 'compressed_image_');
                    // $imagick->writeImage($tempImagePath);

                    // Carpeta específica para almacenar las imágenes comprimidas
                    $carpetaDestino = public_path('/pdfs_goopy/compressed_images/');

                    // Asegurarse de que la carpeta de destino exista
                    if (!file_exists($carpetaDestino)) {
                        mkdir($carpetaDestino, 0777, true);
                    }

                    // Nombre del archivo en la carpeta de destino
                    $nombreArchivoDestino = 'imagen_comprimida_' . $i . '_' . $j . '.jpg';

                    // Guardar la imagen comprimida en la carpeta de destino
                    $imagenDestinoPath = $carpetaDestino . $nombreArchivoDestino;
                    $imagick->writeImage($imagenDestinoPath);

                    // Actualizar la URL de la imagen con la ubicación temporal comprimida
                    $imagen->url = $imagenDestinoPath;
                }
            }

            $data = [
                'tipo_catalogo'=>2,
                'catalogo'=>$catalogo,
            ];

            //return view('goopy.catalogo', $data);

            //$pdf = Pdf::loadView('goopy.catalogo', $data);
            // Crea una instancia de Pdf y establece el tamaño de papel en hoja carta
            $pdf = Pdf::loadView('goopy.catalogo', $data)->setPaper('letter');
            $pdfContent = $pdf->output();

            $countProductos = count($catalogo);
            for ($i = 0; $i < $countProductos; $i++) {
                $producto = $catalogo[$i];
                $countImagenes = count($producto->imagenes);
                for ($j = 0; $j < $countImagenes; $j++) {
                    $imagen = $producto->imagenes[$j];

                    if (file_exists($imagen->url)) {
                        unlink($imagen->url); // Eliminar la imagen
                    }
                }
            }

            // Genera un nombre de archivo único
            $nombreArchivo = 'pdf_catalogo.pdf';

            // Guarda el PDF en la carpeta "public" del directorio raíz
            Storage::disk('public_root')->put('pdfs_goopy/'.$nombreArchivo, $pdf->output());

            // Obtiene la URL del archivo guardado
            $url = asset('pdfs_goopy/' . $nombreArchivo);

            return $url;
              
        } 
        

    }

    public function sendEmail(){
        //Enviar Email
        $details = [
            'title' => 'Test email',
            'body' => 'Body email'
        ];

        try {
            // \Mail::to('tonii.jaam@gmail.com')->send(new \App\Mail\PostEmail($details));
            \Mail::to('ramirez.fred016@gmail.com')->send(new \App\Mail\PostEmail($details));
        } catch (Exception $e) {
            return response()->json(['error'=>"Error al enviar email: " . $e->getMessage()], 409);
        }

        // dd("Email is Sent.");

        return response()->json([
            'message'=>'Email is Sent.'
        ], 200);
    }

    public static function testAI(Request $request)
    {

        $base_url_openai = "https://api.openai.com";
        $path_openai = "/v1";

        $token = env('OPENAI_API_KEY');

        //$model_openai = "text-davinci-003";
        $model_openai = "gpt-3.5-turbo-instruct";

        $prompt = $request->input('prompt');
        
        //Armando la peticion cURL        
        $fields = array(
            "model" => $model_openai,
            "prompt" => $prompt,
            "temperature" => 0.5,
            "max_tokens" => 2048 
        ); 

            
        $fields = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $base_url_openai.$path_openai."/completions");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer ".$token,
            "Content-Type: application/json"
        ));

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        $err = curl_error($ch);

        curl_close($ch);

        if ($err) {
            //echo "cURL Error #:" . $err;
            // return [
            //     'status'=>409,
            //     'error'=>'Error al conectar con OpenAi',
            //     'open_ai'=>$err
            // ];

            return response()->json([
                'error'=>'Error al conectar con OpenAi',
                'open_ai'=>$err
            ], 500);

        } else {

            $open_ai_obj = json_decode($response);

            return response()->json([
                'open_ai'=>$open_ai_obj
            ], 200);
          
        }  

    }

 
}
