<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Cache;

use Illuminate\Support\Facades\Crypt;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;

use App\Models\User;
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
use App\Models\Cfdi40UsoCfdi;
use App\Models\Cfdi40FormaPago;
use App\Models\Cfdi40MetodoPago;
use App\Models\CfdiTimbreFiscalDigital;

use App\Models\Cfdi40ProductoServicio;
use App\Models\Cfdi40ClaveUnidad;

//use Hash;
use DB;
//use Validator;
use Exception;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

use DateTime;
use Carbon\Carbon;

use Mail;
use Session;
use Redirect;
use Swift_SmtpTransport;
use Swift_Mailer;

use App\Http\Traits\ApiWhatsAppTrait;
use App\Http\Traits\ApiTextCortexTrait;
use App\Http\Traits\ApiOpenAiTrait;
use App\Http\Traits\BotFunctionsTrait;

//ejemplo factura cfdi 4.0
// Se desactivan los mensajes de debug
error_reporting(~(E_WARNING|E_NOTICE));
//error_reporting(E_ALL);

// Se especifica la zona horaria
date_default_timezone_set('America/Mexico_City');

// Se incluye el SDK
//require_once 'sdk2/sdk2.php';
require_once public_path('sdk2/sdk2.php');

class FlowFacturaController extends Controller
{
    use ApiWhatsAppTrait;
    use ApiTextCortexTrait;
    use ApiOpenAiTrait;
    use BotFunctionsTrait;

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

    public function generarEmpresas()
    {

        $clientes = BotCliente::all();

        for ($i=0; $i < count($clientes); $i++) { 
            $empresa = CfdiEmpresa::with('producto')
                ->with('mi_regimen_fiscal')
                ->where('bot_cliente_id', $clientes[$i]->id)
                ->first();

            if(!$empresa){
                //Crear la empresa emisora para las facturas
                $nuevaEmpresa=CfdiEmpresa::create([
                    'bot_cliente_id'=>$clientes[$i]->id,
                    'tipo_persona'=>null,
                    'Rfc'=>null,
                    'RazonSocial'=>null,
                    'RegimenFiscal'=>null,
                    'FacAtrAdquirente'=>null,
                    'CP'=>null,
                    'cer'=>null,
                    'key'=>null,
                    'pass'=>null,
                    
                ]);
            }
        }

        // Regresar una respuesta exitosa
        return response('OK', 200);
        
    }

    public function messageTextToCliente($cliente,$message){
        $respC = $this->_messageText($cliente->bot_id,$cliente->telefono,$message);

        file_put_contents('webhook_ws_log200.txt', print_r($respC, true), FILE_APPEND);

        if ($respC['status'] == 200) {

            $this->storeMsgChat($cliente->bot_id,$cliente->id,null,$message,0); //bot

            return 1;

        }else{

            return 0;

        }
    }

    private function storeMsgChat($bot_id, $cliente_id, $wamid, $text, $autor)
    {

        //validacion para no duplicar el mensaje del cliente
        if($autor == 1){
            $contador=BotChat::
                where('bot_id',$bot_id)
                ->where('cliente_id',$cliente_id)
                ->where('wamid',$wamid)
                ->count();

            if($contador>0){
                return 0;
            }
        }

        $nuevoObj=BotChat::create([
            'bot_id'=>$bot_id,
            'cliente_id'=>$cliente_id,
            'wamid'=>$wamid,
            'text'=>$text,
            'autor'=>$autor,
            'status'=>0, //sin procesar
            
        ]);

        return $nuevoObj->id;
        
    }

    public function shortenURL($url)
    {
        $apiUrl = 'https://is.gd/api.php';
        $response = file_get_contents($apiUrl . '?longurl=' . urlencode($url));

        // Verificar si se obtuvo una respuesta válida
        if (filter_var($response, FILTER_VALIDATE_URL)) {
            return $response; // Devuelve el enlace acortado
        } else {
            // Manejar el error en caso de no obtener un enlace acortado válido
            return $url; // Devuelve la URL original sin acortar
        }
    }

    public function flowNoAplicable($cliente)
    {

        DB::table('bot_clientes')
            ->where('id', $cliente->id)
            ->update([
                'flow_id' => null,
            ]);

        $this->cancelarFlowComprobante($cliente,0);


        $salto = '
';

        $message = 'Actualmente estas son mis habilidades en las que puedo ayudarte:'.$salto;

        if($cliente->hab_citas == 1){

            $message = $message.$salto.'🗓️ CITAS
    - Crear cita
    - Ver citas (Aquí, gestionas tus citas)'.$salto;

        }

        if($cliente->hab_redes == 1){

            $message = $message.$salto.'🌐 REDES SOCIALES
    - Publicar en redes sociales'.$salto;

        }

        if($cliente->hab_pedidos == 1){

            $message = $message.$salto.'🛍️ PEDIDOS
    - Ver productos (Aquí, gestionas tus productos)
    - Crear pedido
    - Ver pedidos (Aquí, gestionas tus pedidos)'.$salto;

        }

        if($cliente->hab_cotizaciones == 1){

            $message = $message.$salto.'💰 COTIZACIONES
    - Crear cotización
    - Ver cotizaciones (Aquí, gestionas tus cotizaciones)'.$salto;

        } 

        if($cliente->hab_facturas == 1){

            $message = $message.$salto.'💵 FACTURAS
    - Crear factura
    - Ver facturas (Aquí, gestionas tus facturas)
    - Configurar emisor de factura'.$salto;

        } 

        $this->messageTextToCliente($cliente,$message);

        return 1;


    }

    public function flowCfdiFactura($cliente)
    {
        if($cliente->hab_facturas != 1){
            $this->flowNoAplicable($cliente);
            return 1;
        }

        //Indicar que el cliente inicio el primer estado de un flujo
        DB::table('bot_clientes')
            ->where('id', $cliente->id)
            ->update([
                'flow_id' => 4,
            ]);

        $empresa = CfdiEmpresa::with('producto')
            ->with('mi_regimen_fiscal')
            ->where('bot_cliente_id', $cliente->id)
            ->first();

        if(
            $empresa->Rfc === null || $empresa->RazonSocial === null ||
            $empresa->RegimenFiscal === null || /*$empresa->FacAtrAdquirente === null ||*/
            $empresa->CP === null || 
            $empresa->cer === null || $empresa->key === null ||
            $empresa->pass === null 
        ){
            $message = 'Para crear una factura, primero debes configurar tus datos de emisor. 🏠';

            $this->messageTextToCliente($cliente,$message);

            $this->flowCfdiEmpresa($cliente);
                
            return 1;
        }else{
            $this->flowCfdi($cliente);
            return 1;
        }

        // $this->flowCfdi($cliente);
        // return 1;

    }

    public function flowCfdiEmpresa($cliente)
    {

        if($cliente->hab_facturas != 1){
            $this->flowNoAplicable($cliente);
            return 1;
        }

        //quitar el flujo
        DB::table('bot_clientes')
            ->where('id', $cliente->id)
            ->update([
                'flow_id' => null,
            ]);

        $this->cancelarFlowComprobante($cliente,0);

        $user_token=User::find(56);
        $token = JWTAuth::fromUser($user_token);

        $claveAdicional = config('app.lada_d');

        $cadenaEncriptada = Crypt::encrypt($cliente->id, $claveAdicional);

        $link = 'https://social.internow.com.mx/#/cfdi-config-empresa-bot/'.$cadenaEncriptada.'/'.$token;

        $short_link = $this->shortenURL($link);

        $message = 'Ingresa en el siguiente enlace para configurar tus datos de emisor:

{{short_link}}';

        $message = str_replace("{{short_link}}", $short_link, $message);

        $this->messageTextToCliente($cliente,$message);

        return 1;

    }

    public function flowCfdiClienteNuevo($cliente)
    {

        if($cliente->hab_facturas != 1){
            $this->flowNoAplicable($cliente);
            return 1;
        }

        $empresa = CfdiEmpresa::with('producto')
            ->with('mi_regimen_fiscal')
            ->where('bot_cliente_id', $cliente->id)
            ->first();

        $prompt = 'Creando un cliente receptor de un cfdi 4.0 a partir del mensaje del usuario

Datos recaudados hasta el momento:

{{detalles}}

Mensaje del usuario: {{mensaje}}

Genera un JSON con la siguiente estructura:

{
   "Rfc": "",
   "Nombre": "",
   "CodigoPostal": "",
   "RegimenFiscalReceptor": "",
   "Email": "",
   "nuevo": 0,
   "confirmar": 0,
   "cancelar": 0
}

Instrucciones:
- Solo usa la información del mensaje.
- Extrae la información del mensaje para modificar los datos recaudados y completar el JSON resultante.
- Solo quiero que generes y retornes únicamente el JSON con la estructura indicada, no debes generar código, solo el JSON que te estoy indicando.

Valores posibles para "nuevo":
- 1: El usuario quiere un nuevo cliente.
- 0: El usuario no desea un nuevo cliente.

Valores posibles para "confirmar":
- 1: El usuario confirma que los datos del cliente son correctos.
- 0: El usuario no está confirmando los datos del cliente.

Valores posibles para "cancelar":
- 1: El usuario desea cancelar el proceso de creación del cliente.
- 0: El usuario no quiere cancelar el proceso de creación del cliente.

Ejemplos de mensajes:
Para "nuevo cliente": "Quiero crear un cliente", "Nuevo cliente".
Para "confirmar cliente": "Los datos son correctos", "De acuerdo", "Confirmar", "Correcto", "Si".
Para "cancelar proceso": "Cancelar", "Cancelar proceso".';
        
        $text_mensajes = '';
        for ($i=0; $i < count($cliente->mensajes); $i++) { 
            if($i == 0){
                $text_mensajes = $cliente->mensajes[$i];
            }else{
                $text_mensajes = $text_mensajes.', '.$cliente->mensajes[$i];
            }
        }
        
        $prompt = str_replace("{{mensaje}}", $text_mensajes, $prompt);

        $clienteCurso = CfdiCliente::
            where('empresa_id',$empresa->id)
            ->where('status', 0)
            ->with('mi_regimen_fiscal')
            ->with('mi_uso_cfdi')
            ->first();


        if(!$clienteCurso){
            $prompt = str_replace("{{detalles}}", "{}", $prompt);
        }else{

            $Rfc = "";
            $Nombre = "";
            $CodigoPostal = "";
            $RegimenFiscalReceptor = "";
            $Email = "";

            if($clienteCurso->Rfc != null && $clienteCurso->Rfc != ''){
                $Rfc = $clienteCurso->Rfc;
            }
            if($clienteCurso->Nombre != null && $clienteCurso->Nombre != ''){
                $Nombre = $clienteCurso->Nombre;
            }
            if($clienteCurso->DomicilioFiscalReceptor != null && $clienteCurso->DomicilioFiscalReceptor != ''){
                $CodigoPostal = $clienteCurso->DomicilioFiscalReceptor;
            }
            if($clienteCurso->RegimenFiscalReceptor != null && $clienteCurso->RegimenFiscalReceptor != ''){
                $RegimenFiscalReceptor = $clienteCurso->mi_regimen_fiscal->texto;
            }
            if($clienteCurso->Email != null && $clienteCurso->Email != ''){
                $Email = $clienteCurso->Email;
            }

            $detalles = [
                "Rfc" => $Rfc,
                "Nombre" => $Nombre,
                "CodigoPostal" => $CodigoPostal,
                "RegimenFiscalReceptor" => $RegimenFiscalReceptor,
                "Email" => $Email,
            ];

            $detallesString = json_encode($detalles);
            
            $prompt = str_replace("{{detalles}}", $detallesString, $prompt);
        } 

        $respB = $this->_davinciRespuestaPrompt($prompt);
        if ($respB['status'] == 200) {

            $cadena = $respB['text'];

            $log = [];
            array_push($log,$text_mensajes);
            array_push($log,$cadena);

            file_put_contents('webhook_log_cfdi_clientes.txt', print_r($log, true), FILE_APPEND);

            // $this->messageTextToCliente($cliente,$cadena);
            // //quitar el flujo
            // // DB::table('bot_clientes')
            // //     ->where('id', $cliente->id)
            // //     ->update([
            // //         'flow_id' => null,
            // //     ]);
            // return 1;

            $posicionA = strpos($cadena, '{');
            $posicionB = strrpos($cadena, '}');
            $cadena = substr($cadena,$posicionA,$posicionB+1-($posicionA));

            if ($posicionA === false || $posicionB === false) {
                return 0; // Retornar cadena vacía si no se encuentran los caracteres
            }

            $obj = json_decode($cadena);

            $cadenaEnMayusculas = strtoupper($text_mensajes);
            $subcadena = 'CANCELAR';

            // Buscar la subcadena en la cadena original (sensible a mayúsculas y minúsculas)
            $posicion = strpos($cadenaEnMayusculas, $subcadena);

            if ($posicion !== false) {
                $obj->nuevo = 0;
                $obj->confirmar = 0;
                $obj->cancelar = 1;
            }

            $subcadena = 'CONFIRMAR';

            // Buscar la subcadena en la cadena original (sensible a mayúsculas y minúsculas)
            $posicion = strpos($cadenaEnMayusculas, $subcadena);

            if ($posicion !== false) {
                $obj->nuevo = 0;
                $obj->confirmar = 1;
                $obj->cancelar = 0;
            }

            $obj->Nombre = strtoupper($obj->Nombre);

            $clienteCurso = CfdiCliente::
                where('empresa_id',$empresa->id)
                ->where('status', 0)
                ->with('mi_regimen_fiscal')
                ->with('mi_uso_cfdi')
                ->first();

            /*Si no hay informacion referente al cliente,
            responder con el prompt general*/
            if(
                $obj->Rfc == "" &&
                $obj->Nombre == "" &&
                $obj->CodigoPostal == "" &&
                $obj->RegimenFiscalReceptor == "" &&
                $obj->Email == "" &&

                $obj->nuevo == 0 &&
                $obj->confirmar == 0 &&
                $obj->cancelar == 0 
            ){

                //retornar detalles
                $message = $this->detallesCliente($empresa);
                $this->messageTextToCliente($cliente,$message);
                return 1;
            }

            /*Si quiere iniciar el proceso*/
            if(
                $obj->Rfc == "" &&
                $obj->Nombre == "" &&
                $obj->CodigoPostal == "" &&
                $obj->RegimenFiscalReceptor == "" &&
                $obj->Email == "" &&

                $obj->nuevo == 1
            ){

                //elimino el cliente en curso en caso de que tenga
                if($clienteCurso){
                    $clienteCurso->delete();
                }

                //crear un cliente nuevo en curso
                $nuevoObj=CfdiCliente::create([
                    'empresa_id'=>$empresa->id,
                    'status'=>0,
                    'Rfc'=>"",
                    'Nombre'=>"",
                    'DomicilioFiscalReceptor'=>"",
                    'ResidenciaFiscal'=>"",
                    'RegimenFiscalReceptor'=>"",
                    'UsoCFDI'=>15, //Gastos en general
                    'Email'=>"",
                ]);

                $message = '¡Hola! Estoy aquí para ayudarte a dar de alta un cliente. Por favor, proporciona los siguientes datos:

🆔 RFC:
👤 Razón Social:
🏠 Código Postal:
📅 Régimen fiscal:
📧 Email:

Espero tus respuestas para dar de alta el cliente correctamente.';

                $this->messageTextToCliente($cliente,$message);
                
                return 1;

            }

            /*Si quiere cancelar el proceso*/
            if(
                $obj->cancelar == 1
            ){

                //quitar el flujo
                DB::table('bot_clientes')
                    ->where('id', $cliente->id)
                    ->update([
                        'flow_id' => null,
                    ]);

                DB::table('cfdi_comprobante')
                    ->where('cliente_id', $cliente->id)
                    ->where('status', 0)
                    ->delete();

                //elimino el cliente en curso en caso de que tenga
                if($clienteCurso){
                    $clienteCurso->delete();
                }

                $message = 'Proceso cancelado correctamente';

                $this->messageTextToCliente($cliente,$message);
                
                return 1;

            }

            /*Si quiere confirmar el cliente*/
            if(
                $obj->confirmar == 1
            ){

                if(
                    $clienteCurso && 

                    $clienteCurso->Rfc &&
                    $clienteCurso->Nombre &&
                    $clienteCurso->DomicilioFiscalReceptor &&
                    $clienteCurso->RegimenFiscalReceptor &&
                    $clienteCurso->Email 
                ){

                    //quitar el flujo
                    DB::table('bot_clientes')
                        ->where('id', $cliente->id)
                        ->update([
                            'flow_id' => null,
                        ]);

                    //pasar a confirmado
                    $clienteCurso->status = 1;
                    $clienteCurso->save();

                    $message = '✅ *Cliente creado exitosamente:*

🆔 RFC: {{Rfc}} 
👤 Razón Social: {{Nombre}}
🏠 Código Postal: {{CodigoPostal}}
📅 Régimen fiscal: {{RegimenFiscalReceptor}}
📧 Email: {{Email}}';

                    $message = str_replace("{{Rfc}}", $clienteCurso->Rfc, $message);
                    $message = str_replace("{{Nombre}}", $clienteCurso->Nombre, $message);
                    $message = str_replace("{{CodigoPostal}}", $clienteCurso->DomicilioFiscalReceptor, $message);
                    $message = str_replace("{{RegimenFiscalReceptor}}", $clienteCurso->mi_regimen_fiscal->texto, $message);
                    $message = str_replace("{{Email}}", $clienteCurso->Email, $message);
                
                    $this->messageTextToCliente($cliente,$message);

                    //retornar el cliente para seguir con el flujo
                    return $clienteCurso;
                                        
                }else if(
                    $obj->Rfc == "" ||
                    $obj->Nombre == "" ||
                    $obj->CodigoPostal == "" ||
                    $obj->RegimenFiscalReceptor == "" ||
                    $obj->Email == "" 
                ){
                    
                    //retornar detalles
                    $message = $this->detallesCliente($empresa);
                    $this->messageTextToCliente($cliente,$message);
                    return 1;
                }  

            }

            //si no tiene cliente en curso, creo uno para las validaciones
            if(!$clienteCurso){
                //crear un cliente nuevo en curso
                $nuevoObj=CfdiCliente::create([
                    'empresa_id'=>$empresa->id,
                    'status'=>0,
                    'Rfc'=>"",
                    'Nombre'=>"",
                    'DomicilioFiscalReceptor'=>"",
                    'ResidenciaFiscal'=>"",
                    'RegimenFiscalReceptor'=>"",
                    'UsoCFDI'=>15,
                    'Email'=>"",
                ]);

                $clienteCurso = CfdiCliente::
                    where('empresa_id',$empresa->id)
                    ->where('status', 0)
                    ->with('mi_regimen_fiscal')
                    ->with('mi_uso_cfdi')
                    ->first();
            }

            //validar Rfc
            if($obj->Rfc != ""){

                // Eliminar espacios en blanco y guiones si los hay
                $Rfc = str_replace([' ', '-'], '', $obj->Rfc);
                $Rfc = strtoupper($Rfc);

                $rfcValido = "/^[A-Z0-9]{12,13}$/";

                if (preg_match($rfcValido, $Rfc)) {

                    /*
                    checar si existe un cliente con ese Rfc

                    si ya existe retorno el que existe para seguir con el flujo

                    si aun no existe sigo con el proceso para crearlo
                    */

                    $clienteExiste = CfdiCliente::
                        where('empresa_id',$empresa->id)
                        ->where('status', 1)
                        ->where('Rfc', $Rfc)
                        ->with('mi_regimen_fiscal')
                        ->with('mi_uso_cfdi')
                        ->first();

                    if($clienteExiste){

                        //elimino cliente en curso en caso de que tenga
                        if($clienteCurso){
                            $clienteCurso->delete();
                        }

                        return $clienteExiste;
                    }else{
                        DB::table('cfdi_clientes')
                            ->where('empresa_id', $empresa->id)
                            ->where('status', 0)
                            ->update([
                                'Rfc' => $Rfc,
                            ]);

                        if($Rfc == 'XAXX010101000'){
                            $obj->Nombre = 'PUBLICO EN GENERAL';
                            $obj->RegimenFiscalReceptor = 'Sin obligaciones fiscales';
                        }
                    }
                    
                } else {
                    // El Rfc es inválido
                    $message = 'Por favor, verifica el Rfc. En el caso de que sea una persona física, este campo debe contener una longitud de 13 posiciones, si se trata de personas morales debe contener una longitud de 12 posiciones. 🆔';
                    $this->messageTextToCliente($cliente,$message);
 
                }

            }

            //validar Nombre
            if($obj->Nombre != ""){

                DB::table('cfdi_clientes')
                    ->where('empresa_id', $empresa->id)
                    ->where('status', 0)
                    ->update([
                        'Nombre' => $obj->Nombre,
                    ]);

            }

            //validar DomicilioFiscalReceptor
            if($obj->CodigoPostal != ""){

                // Eliminar espacios en blanco y guiones si los hay
                $Cp = str_replace([' ', '-'], '', $obj->CodigoPostal);

                $cpValido = "/^[0-9]{5}$/";

                if (preg_match($cpValido, $Cp)) {

                    //checar si existe en el catalogo
                    $CpBD = Cfdi40CodigoPostal::
                        where('id', $Cp)
                        ->first();

                    if($CpBD){
                        DB::table('cfdi_clientes')
                            ->where('empresa_id', $empresa->id)
                            ->where('status', 0)
                            ->update([
                                'DomicilioFiscalReceptor' => $CpBD->id,
                            ]);   
                    }else{
                        // El CP no existe en el catalogo
                        $message = 'El código postal que ingresaste no está disponible en nuestro catálogo. Por favor, intenta ingresar un código postal diferente. 🏠';
                        $this->messageTextToCliente($cliente,$message);

                    }

                } else {
                    // El CP es inválido
                    $message = 'Por favor, verifica el Código Postal. Este campo es el código postal del domicilio fiscal del contribuyente y debe contener una longitud de 5 posiciones. 🏠';
                    $this->messageTextToCliente($cliente,$message);

                }

            }

            //validar RegimenFiscalReceptor
            if($obj->RegimenFiscalReceptor != ""){

                $RegimenFiscal = $obj->RegimenFiscalReceptor;

                //checar si existe en el catalogo
                $RegimenFiscalBD = $this->checkCatalogo(2,$RegimenFiscal);

                if($RegimenFiscalBD){
                    DB::table('cfdi_clientes')
                        ->where('empresa_id', $empresa->id)
                        ->where('status', 0)
                        ->update([
                            'RegimenFiscalReceptor' => $RegimenFiscalBD->id,
                        ]);   
                }else{
                    // El RegimenFiscal no existe en el catalogo
                    $message = 'El Régimen fiscal que ingresaste no está disponible en nuestro catálogo. Por favor, intenta ingresar un Régimen fiscal diferente. 📅';
                    $this->messageTextToCliente($cliente,$message);

                }

            }

            //validar Email
            if($obj->Email != ""){

                // Eliminar espacios en blanco y guiones si los hay
                $Email = str_replace([' '], '', $obj->Email);
                $Email = strtolower($Email);


                $emailValido = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';

                if (preg_match($emailValido, $Email)) {

                    $clienteExiste = CfdiCliente::
                        where('empresa_id',$empresa->id)
                        ->where('status', 1)
                        ->where('Email', $Email)
                        ->with('mi_regimen_fiscal')
                        ->with('mi_uso_cfdi')
                        ->first();

                    if(!$clienteExiste){
                        DB::table('cfdi_clientes')
                            ->where('empresa_id', $empresa->id)
                            ->where('status', 0)
                            ->update([
                                'Email' => $Email,
                            ]);   
                    }else{
                        $message = 'Ya tienes otro cliente con el Email que ingresaste. Por favor, intenta ingresar un Email diferente. 📧';
                        $this->messageTextToCliente($cliente,$message);

                    }
                    
                } else {
                    // El Email es inválido
                    $message = 'Por favor, verifica el Email. Este campo debe contener un formato de Email válido. 📧';
                    $this->messageTextToCliente($cliente,$message);

                }

            }

            $message = $this->detallesCliente($empresa); 

            $respC = $this->_messageText($cliente->bot_id,$cliente->telefono,$message);

            if ($respC['status'] == 200) {

                $this->storeMsgChat($cliente->bot_id,$cliente->id,null,$message,0); //bot

                return 1;

            }else{

                return 0;

            }
            
        }else{

            $this->messageTextToCliente($cliente,$respB['error']);
            return 0;

        } 

    }

    public function detallesCliente($empresa)
    {
        $clienteCurso = CfdiCliente::
            where('empresa_id',$empresa->id)
            ->where('status', 0)
            ->with('mi_regimen_fiscal')
            ->with('mi_uso_cfdi')
            ->first();

        if(!$clienteCurso){
            //crear un cliente nuevo en curso
            $nuevoObj=CfdiCliente::create([
                'empresa_id'=>$empresa->id,
                'status'=>0,
                'Rfc'=>"",
                'Nombre'=>"",
                'DomicilioFiscalReceptor'=>"",
                'ResidenciaFiscal'=>"",
                'RegimenFiscalReceptor'=>"",
                'UsoCFDI'=>15,
                'Email'=>"",
            ]);

            $clienteCurso = CfdiCliente::
                where('empresa_id',$empresa->id)
                ->where('status', 0)
                ->with('mi_regimen_fiscal')
                ->with('mi_uso_cfdi')
                ->first();
        }

        $message = '';

        if(
            $clienteCurso && 

            $clienteCurso->Rfc &&
            $clienteCurso->Nombre &&
            $clienteCurso->DomicilioFiscalReceptor &&
            //$clienteCurso->ResidenciaFiscal &&
            $clienteCurso->RegimenFiscalReceptor &&
            $clienteCurso->Email 
        ){

            $message = 'Por favor, confirma que los datos del cliente son correctos:

🆔 RFC: {{Rfc}} 
👤 Razón Social: {{Nombre}}
🏠 Código Postal: {{CodigoPostal}}
📅 Régimen fiscal: {{RegimenFiscalReceptor}}
📧 Email: {{Email}}

Espero tu confirmación o también puedes cancelar el proceso.';

            $message = str_replace("{{Rfc}}", $clienteCurso->Rfc, $message);
            $message = str_replace("{{Nombre}}", $clienteCurso->Nombre, $message);
            $message = str_replace("{{CodigoPostal}}", $clienteCurso->DomicilioFiscalReceptor, $message);
            $message = str_replace("{{RegimenFiscalReceptor}}", $clienteCurso->mi_regimen_fiscal->texto, $message);
            $message = str_replace("{{Email}}", $clienteCurso->Email, $message);
                 
        }else{
                

            $message = 'Para terminar de dar de alta el cliente aún faltan datos. Por favor, proporciona los datos faltantes:

🆔 RFC: {{Rfc}} 
👤 Razón Social: {{Nombre}}
🏠 Código Postal: {{CodigoPostal}}
📅 Régimen fiscal: {{RegimenFiscalReceptor}}
📧 Email: {{Email}}

Espero tus respuestas para dar de alta el cliente correctamente.';

            $Rfc = '';
            $Nombre = '';
            $CodigoPostal = '';
            $RegimenFiscalReceptor = '';
            $Email = '';

            if($clienteCurso->Rfc != null && $clienteCurso->Rfc != ''){
                $Rfc = $clienteCurso->Rfc;
            }
            if($clienteCurso->Nombre != null && $clienteCurso->Nombre != ''){
                $Nombre = $clienteCurso->Nombre;
            }
            if($clienteCurso->DomicilioFiscalReceptor != null && $clienteCurso->DomicilioFiscalReceptor != ''){
                $CodigoPostal = $clienteCurso->DomicilioFiscalReceptor;
            }
            if($clienteCurso->RegimenFiscalReceptor != null && $clienteCurso->RegimenFiscalReceptor != ''){
                $RegimenFiscalReceptor = $clienteCurso->mi_regimen_fiscal->texto;
            }
            if($clienteCurso->Email != null && $clienteCurso->Email != ''){
                $Email = $clienteCurso->Email;
            }

            $message = str_replace("{{Rfc}}", $Rfc, $message);
            $message = str_replace("{{Nombre}}", $Nombre, $message);
            $message = str_replace("{{CodigoPostal}}", $CodigoPostal, $message);
            $message = str_replace("{{RegimenFiscalReceptor}}", $RegimenFiscalReceptor, $message);
            $message = str_replace("{{Email}}", $Email, $message);
            
        }

        return $message;
    }

    public function flowCfdi($cliente)
    {
        
        if($cliente->hab_facturas != 1){
            $this->flowNoAplicable($cliente);
            return 1;
        }

        $empresa = CfdiEmpresa::with('producto')
            ->with('mi_regimen_fiscal')
            ->where('bot_cliente_id', $cliente->id)
            ->first();

        $comprobanteCurso = CfdiComprobante::
            where('cliente_id',$cliente->id)
            ->where('status', 0)
            ->with('receptor')
            ->with('conceptos')
            ->with('impuesto')
            ->with('timbre_fiscal_digital')
            ->with('archivo')
            ->first();

        if(!$comprobanteCurso){
            //crear un comprobante nuevo en curso
            $Folio = (CfdiComprobante::count())+1;

            $Serie = (CfdiComprobante::
            where('cliente_id',$cliente->id)
            ->count())+1;

            //crear un pedido nuevo en curso
            $nuevoObj=CfdiComprobante::create([
                'bot_id'=>$cliente->bot_id,
                'cliente_id'=>$cliente->id,
                'status'=>0,
                'flag_cancelada'=>null,
                'Serie'=>"S-".$empresa->id."-".$Serie,
                'Folio'=>"F-".$empresa->id."-".$Folio,
                'Fecha'=>date('Y-m-d\TH:i:s', time() - (60*60)),
                'Sello'=>"",
                'FormaPago'=>"",
                'NoCertificado'=>"",
                'Certificado'=>"",
                'CondicionesDePago'=>"",
                'Subtotal'=>0.00,
                'Descuento'=>0.00,
                'Moneda'=>"MXN",
                'TipoCambio'=>"",
                'Total'=>0.00,
                'TipoDeComprobante'=>"I",
                'Exportacion'=>"01",
                'MetodoPago'=>"",
                'LugarExpedicion'=>$empresa->CP,
                'Confirmacion'=>"",
                'estado'=>null,
                'function'=>null,
                'TasaIva'=>null,
                'TasaIsr'=>null,
                'Tipo'=>null,
            ]);

            $comprobanteCurso = CfdiComprobante::
                where('cliente_id',$cliente->id)
                ->where('status', 0)
                ->with('receptor')
                ->with('conceptos')
                ->with('impuesto')
                ->with('timbre_fiscal_digital')
                ->with('archivo')
                ->first();
        }

        //aplicar el promt general
        if($comprobanteCurso->estado === null && $comprobanteCurso->function === null){

            $prompt = 'Evaluando la intención del mensaje de un usuario

Mensaje del usuario: {{mensaje}}

Genera un JSON con la siguiente estructura:

{
   "nueva_factura": 0,
   "ver_facturas": 0,
   "configurar_empresa": 0
}

Instrucciones:
- Solo usa la información del mensaje.
- Extrae la información del mensaje para completar el JSON resultante.
- Solo quiero que generes y retornes únicamente el JSON con la estructura indicada, no debes generar código, solo el JSON que te estoy indicando.

Valores posibles para "nueva_factura":
- 1: El usuario quiere una nueva factura.
- 0: El usuario no desea una nueva factura.

Valores posibles para "ver_facturas":
- 1: El usuario quiere ver el listado de sus facturas.
- 0: El usuario no desea ver el listado de sus facturas.

Valores posibles para "configurar_empresa":
- 1: El usuario quiere configurar su empresa.
- 0: El usuario no desea configurar su empresa.

Ejemplos de mensajes:
Para "nueva factura": "Quiero crear una factura", "Nueva factura", "Quiero hacer una factura".
Para "ver facturas": "Ver mis facturas", "Muéstrame mis facturas", "Ver facturas".
Para "configurar empresa": "Quiero configurar mi empresa", "Configurar empresa".';

            $text_mensajes = '';
            for ($i=0; $i < count($cliente->mensajes); $i++) { 
                if($i == 0){
                    $text_mensajes = $cliente->mensajes[$i];
                }else{
                    $text_mensajes = $text_mensajes.', '.$cliente->mensajes[$i];
                }
            }
            
            $prompt = str_replace("{{mensaje}}", $text_mensajes, $prompt);

            $respB = $this->_davinciRespuestaPrompt($prompt);
            if ($respB['status'] == 200) {

                $cadena = $respB['text'];

                $log = [];
                array_push($log,$text_mensajes);
                array_push($log,$cadena);

                file_put_contents('webhook_log_cfdi_facturas.txt', print_r($log, true), FILE_APPEND);

                // $this->messageTextToCliente($cliente,$cadena);
                // //quitar el flujo
                // // DB::table('bot_clientes')
                // //     ->where('id', $cliente->id)
                // //     ->update([
                // //         'flow_id' => null,
                // //     ]);
                // return 1;

                $posicionA = strpos($cadena, '{');
                $posicionB = strrpos($cadena, '}');
                $cadena = substr($cadena,$posicionA,$posicionB+1-($posicionA));

                if ($posicionA === false || $posicionB === false) {
                    return 0; // Retornar cadena vacía si no se encuentran los caracteres
                }

                $obj = json_decode($cadena);

                /*Si no hay informacion referente a las facturas,
                responder con el no aplicable*/
                if(
                    $obj->nueva_factura == 0 &&
                    $obj->ver_facturas == 0 &&
                    $obj->configurar_empresa == 0 
                ){

                    //elimino comprobante en curso en caso de que tenga
                    if($comprobanteCurso){
                        $comprobanteCurso->delete();
                    }

                    //respondo con flujo no aplicable
                    $this->flowNoAplicable($cliente);
                    return 1;

                }

                /*Si quiere configurar su empresa*/
                if(
                    $obj->configurar_empresa == 1
                ){

                    //respondo con flujo de configurar empresa
                    $this->flowCfdiEmpresa($cliente);
                    return 1;

                }

                /*Si quiere ver sus facturas*/
                if(
                    $obj->ver_facturas == 1
                ){

                    //respondo con flujo para ver facturas
                    $this->flowListaFacturas($cliente);
                    return 1;

                }

                /*Si quiere crear un nuevo cliente*/
                if(
                    $obj->nueva_factura == 1
                ){

                    if($cliente->count_facturas >= $cliente->max_facturas){

                        //elimino comprobante en curso en caso de que tenga
                        if($comprobanteCurso){
                            $comprobanteCurso->delete();
                        }


                        $message = 'Has alcanzado el máximo de '.$cliente->max_facturas.' facturas por mes.';

                        //respondo con pregunta
                        $this->messageTextToCliente($cliente,$message);
                        return 1; 

                    }else{
                        $comprobanteCurso->estado = 1;
                        $comprobanteCurso->save();

                        $user_token=User::find(56);
                        $token = JWTAuth::fromUser($user_token);

                        $claveAdicional = config('app.lada_d');

                        $cadenaEncriptada = Crypt::encrypt($cliente->id, $claveAdicional);

                        $link = 'https://social.internow.com.mx/#/cfdi-store-factura-bot/'.$cadenaEncriptada.'/'.$token;

                        $short_link = $this->shortenURL($link);

                        $message = 'Deseas generar tu factura con un:

    - Cliente existente
    - Cliente nuevo

También puedes cancelar el proceso

O puedes facturar dando click aquí 
{{short_link}}';

                        $message = str_replace("{{short_link}}", $short_link, $message);

                        //respondo con pregunta
                        $this->messageTextToCliente($cliente,$message);
                        return 1;   

                    }
                    
                }

            }else{

                $this->messageTextToCliente($cliente,$respB['error']);
                return 0;

            } 

        }

        if($comprobanteCurso->estado == 1 && $comprobanteCurso->function === null){

            $prompt = 'Evaluando la intención del mensaje de un usuario

Mensaje del usuario: {{mensaje}}

Genera un JSON con la siguiente estructura:

{
   "cliente_nuevo": 0,
   "cliente_existente": 0,
   "cancelar": 0
}

Instrucciones:
- Solo usa la información del mensaje.
- Extrae la información del mensaje para completar el JSON resultante.
- Solo quiero que generes y retornes únicamente el JSON con la estructura indicada, no debes generar código, solo el JSON que te estoy indicando.

Valores posibles para "cliente_nuevo":
- 1: El usuario quiere un nuevo cliente.
- 0: El usuario no desea un nuevo cliente.

Valores posibles para "cliente_existente":
- 1: El usuario quiere un cliente existente.
- 0: El usuario no desea un cliente existente.

Valores posibles para "cancelar":
- 1: El usuario quiere cancelar el proceso.
- 0: El usuario no desea cancelar el proceso.

Ejemplos de mensajes:
Para "cliente nuevo": "Nuevo", "Nuevo cliente".
Para "cliente existente": "Existente", "Cliente existente".
Para "cancelar": "Cancelar", "Cancelar proceso".';

            $text_mensajes = '';
            for ($i=0; $i < count($cliente->mensajes); $i++) { 
                if($i == 0){
                    $text_mensajes = $cliente->mensajes[$i];
                }else{
                    $text_mensajes = $text_mensajes.', '.$cliente->mensajes[$i];
                }
            }
            
            $prompt = str_replace("{{mensaje}}", $text_mensajes, $prompt);

            $respB = $this->_davinciRespuestaPrompt($prompt);
            if ($respB['status'] == 200) {

                $cadena = $respB['text'];

                $log = [];
                array_push($log,$text_mensajes);
                array_push($log,$cadena);

                file_put_contents('webhook_log_cfdi_facturas.txt', print_r($log, true), FILE_APPEND);

                // $this->messageTextToCliente($cliente,$cadena);
                // //quitar el flujo
                // // DB::table('bot_clientes')
                // //     ->where('id', $cliente->id)
                // //     ->update([
                // //         'flow_id' => null,
                // //     ]);
                // return 1;

                $posicionA = strpos($cadena, '{');
                $posicionB = strrpos($cadena, '}');
                $cadena = substr($cadena,$posicionA,$posicionB+1-($posicionA));

                if ($posicionA === false || $posicionB === false) {
                    return 0; // Retornar cadena vacía si no se encuentran los caracteres
                }

                $obj = json_decode($cadena);

                /*Si no hay informacion referente a las facturas,
                responder con el no aplicable*/
                if(
                    $obj->cliente_nuevo == 0 &&
                    $obj->cliente_existente == 0 &&
                    $obj->cancelar == 0 
                ){

                    $comprobanteCurso->estado = 1;
                    $comprobanteCurso->save();

                    $message = 'Deseas generar tu factura con un:

    - Cliente existente
    - Cliente nuevo

También puedes cancelar el proceso.';

                    //respondo con pregunta
                    $this->messageTextToCliente($cliente,$message);
                    return 1;
                }


                /*Si quiere con un nuevo cliente*/
                if(
                    $obj->cliente_nuevo == 1
                ){

                    $comprobanteCurso->estado = 2;
                    $comprobanteCurso->function = 'flowCfdiClienteNuevo';
                    $comprobanteCurso->save();

                    //respondo con flujo de nuevo cliente
                    $this->flowCfdiClienteNuevo($cliente);
                    return 1;

                }

                /*Si quiere con un cliente existente*/
                if(
                    $obj->cliente_existente == 1
                ){

                    $comprobanteCurso->estado = 1;
                    $comprobanteCurso->function = 'flowCfdiClienteExistente';
                    $comprobanteCurso->save();

                    //respondo con flujo de nuevo cliente
                    $this->flowCfdiClienteExistente($cliente);
                    return 1;

                }

                /*Si quiere cancelar el proceso*/
                if(
                    $obj->cancelar == 1
                ){

                    $this->cancelarFlowComprobante($cliente);
                    return 1;

                }

            }else{

                $this->messageTextToCliente($cliente,$respB['error']);
                return 0;

            } 

            

        }

        if($comprobanteCurso->estado != null  && $comprobanteCurso->function != null){
            $nomFunctionFlow = $comprobanteCurso->function;
            $resultado = $this->$nomFunctionFlow($cliente);

            if($comprobanteCurso->estado == 2){

                if($resultado === 0 || $resultado === 1){
                    return 1;
                }else{
                    
                    //crear el receptor
                    $newObjReceptor=CfdiReceptor::create([
                        'comprobante_id'=>$comprobanteCurso->id,
                        'Rfc'=>$resultado->Rfc,
                        'Nombre'=>$resultado->Nombre,
                        'DomicilioFiscalReceptor'=>$resultado->DomicilioFiscalReceptor,
                        'ResidenciaFiscal'=>$resultado->ResidenciaFiscal,
                        'NumRegIdTrib'=>$resultado->NumRegIdTrib,
                        'RegimenFiscalReceptor'=>$resultado->RegimenFiscalReceptor,
                        'UsoCFDI'=>'',
                        'Email'=>$resultado->Email,
                    ]);

                    $comprobanteCurso->estado = 3;
                    $comprobanteCurso->function = 'flowCfdiDataFactura';
                    $comprobanteCurso->save();

                    $cliente->mensajes = ['Crear nueva factura sin tipo'];

                    $message = 'Iniciando proceso de facturación...';
                    $this->messageTextToCliente($cliente,$message);

                    $this->flowCfdiDataFactura($cliente);
                    return 1;
                }

            }else if($comprobanteCurso->estado == 3){

                if($resultado === 0 || $resultado === 1){
                    return 1;
                }else{
                    
                    $comprobanteCurso->estado = 4;
                    $comprobanteCurso->function = 'flowCfdiConceptosFactura';
                    $comprobanteCurso->save();

                    $cliente->mensajes = ['Crear nueva factura'];

                    $message = 'Iniciando proceso de solicitud de conceptos...';
                    $this->messageTextToCliente($cliente,$message);

                    $this->flowCfdiConceptosFactura($cliente);
                    return 1;
                }

            }else if($comprobanteCurso->estado == 4){
                return 1;
            }
            
            
        }

        $message = 'Debug: estado no evaluado.';
        $this->messageTextToCliente($cliente,$message);
        return 1;
    }

    
    public function flowCfdiClienteExistente($cliente)
    {
        $comprobanteCurso = CfdiComprobante::
            where('cliente_id',$cliente->id)
            ->where('status', 0)
            ->with('receptor')
            ->with('conceptos')
            ->with('impuesto')
            ->with('timbre_fiscal_digital')
            ->with('archivo')
            ->first();

        $comprobanteCurso->estado = 2;
        $comprobanteCurso->function = 'flowCfdiBuscarCliente';
        $comprobanteCurso->save();

        $message = 'Por favor, proporciona el RFC o Nombre del cliente

🆔 RFC o Nombre:

Espero tu respuesta.';

        $this->messageTextToCliente($cliente,$message);
        
        return 1;

    }

    public function flowCfdiBuscarCliente($cliente)
    {
        $empresa = CfdiEmpresa::with('producto')
            ->with('mi_regimen_fiscal')
            ->where('bot_cliente_id', $cliente->id)
            ->first();

        $text_mensajes = '';
        for ($i=0; $i < count($cliente->mensajes); $i++) { 
            if($i == 0){
                $text_mensajes = $cliente->mensajes[$i];
            }else{
                $text_mensajes = $text_mensajes.', '.$cliente->mensajes[$i];
            }
        }
        
        $cadenaEnMayusculas = strtoupper($text_mensajes);
        $subcadena = 'CANCELAR';

        // Buscar la subcadena en la cadena original (sensible a mayúsculas y minúsculas)
        $posicion = strpos($cadenaEnMayusculas, $subcadena);

        if ($posicion !== false) {
            $this->cancelarFlowComprobante($cliente);
            return 1;
        } else {

            $Rfc = $cadenaEnMayusculas;

            //checar si existe el rfc en los clientes
            $RfcBD = CfdiCliente::
                where(function ($query) use ($Rfc){
                    $query->where('Rfc', 'like', '%'.$Rfc.'%')
                        ->orWhere('Nombre', 'like', '%'.$Rfc.'%');
                })
                ->where('empresa_id',$empresa->id)
                ->where('status',1)
                ->with('mi_regimen_fiscal')
                ->with('mi_uso_cfdi')
                ->first();

            if($RfcBD){
                $message = '*Cliente:*

🆔 RFC: {{Rfc}} 
👤 Razón Social: {{Nombre}}
🏠 Código Postal: {{CodigoPostal}}
📅 Régimen fiscal: {{RegimenFiscalReceptor}}
📧 Email: {{Email}}';

                    $message = str_replace("{{Rfc}}", $RfcBD->Rfc, $message);
                    $message = str_replace("{{Nombre}}", $RfcBD->Nombre, $message);
                    $message = str_replace("{{CodigoPostal}}", $RfcBD->DomicilioFiscalReceptor, $message);
                    $message = str_replace("{{RegimenFiscalReceptor}}", $RfcBD->mi_regimen_fiscal->texto, $message);
                    $message = str_replace("{{Email}}", $RfcBD->Email, $message);
                
                    $this->messageTextToCliente($cliente,$message);
                    return $RfcBD;
            }else{
                // El RfcBD no existe en los clientes
                $message = 'El Rfc o Nombre que ingresaste no está disponible en tus clientes. Por favor, intenta ingresar un Rfc o Nombre diferente o también puedes cancelar el proceso.';
                $this->messageTextToCliente($cliente,$message);
                return 1;
            }
            
        }

    }

    public function cancelarFlowComprobante($cliente, $option=1)
    {
        //quitar el flujo
        DB::table('bot_clientes')
            ->where('id', $cliente->id)
            ->update([
                'flow_id' => null,
            ]);

        $comprobanteCurso = CfdiComprobante::
            where('cliente_id',$cliente->id)
            ->where('status', 0)
            ->with('receptor')
            ->with('conceptos')
            ->with('impuesto')
            ->with('timbre_fiscal_digital')
            ->with('archivo')
            ->first();

        //elimino el comprobante en curso en caso de que tenga
        if($comprobanteCurso){

            DB::table('cfdi_receptor')
            ->where('comprobante_id', $comprobanteCurso->id)
            ->delete();

            DB::table('cfdi_conceptos')
            ->where('comprobante_id', $comprobanteCurso->id)
            ->delete();

            $comprobanteCurso->delete();
        }

        if($option==1){
            $message = 'Proceso cancelado correctamente';

            $this->messageTextToCliente($cliente,$message);
        }
        
        return 1;

    }

    public function flowCfdiDataFactura($cliente)
    {
        set_time_limit(500);

        if($cliente->hab_facturas != 1){
            $this->flowNoAplicable($cliente);
            return 1;
        }

        $empresa = CfdiEmpresa::
            with(['producto' => function ($query){
                $query->with('mi_clave_prod_serv')
                    ->with('mi_clave_unidad');
            }])
            ->with('mi_regimen_fiscal')
            ->where('bot_cliente_id', $cliente->id)
            ->first();

        $salto = '
';

        $prompt = 'Creando una factura a partir del mensaje de un usuario

Datos recaudados hasta el momento:

{{detalles}}

Mensaje del cliente: {{mensaje}}

Genera un JSON con la siguiente estructura:

{
   "UsoCFDI": "",
   "FormaPago": "",
   "MetodoPago": "",
   "Tipo": "",
   "confirmar": 0,
   "cancelar": 0
}

Instrucciones:
- Sino hay niguna información de referente a la factura retorna el JSON con sus propiedades vacias.
- Solo usa la información del mensaje, no inventes ningún dato.
- Extrae la información del mensaje para modificar los datos recaudados y completar el JSON resultante.
- Solo quiero que generes y retornes únicamente el JSON con la estructura indicada, no debes generar código, solo el JSON que te estoy indicando.

Valores posibles para "Tipo":
- 1: El usuario indica que el tipo de factura es Neta.
- 2: El usuario indica que el tipo de factura es Más IVA.

Valores posibles para "confirmar":
- 1: El usuario confirma que los datos de la factura son correctos, solo se puede confirmar si el usuario envía alguno de los mensajes de ejemplo para confirmar.
- 0: El usuario no está confirmando los datos de la factura.

Valores posibles para "cancelar":
- 1: El usuario desea cancelar la factura.
- 0: El usuario no quiere cancelar la factura.

Ejemplos de mensajes:
Para "confirmar factura": "confirmar factura", "confirmar", "Los datos son correctos", "De acuerdo", "Confirmar", "Correcto", "Si".
Para "cancelar factura": "Cancelar factura", "Cancelar".';
        
        $text_mensajes = '';
        for ($i=0; $i < count($cliente->mensajes); $i++) { 
            if($i == 0){
                $text_mensajes = $cliente->mensajes[$i];
            }else{
                $text_mensajes = $text_mensajes.', '.$cliente->mensajes[$i];
            }
        }
        
        $prompt = str_replace("{{mensaje}}", $text_mensajes, $prompt);
        //$prompt = $this->contextoFecha($prompt);

        $pedidoCurso = CfdiComprobante::
            where('cliente_id',$cliente->id)
            ->where('status', 0)
            ->with('receptor')
            ->with('impuesto')
            ->with('timbre_fiscal_digital')
            ->with('archivo')
            ->with('mi_forma_pago')
            ->with('mi_metodo_pago')
            ->first();

        if(!$pedidoCurso){
            $prompt = str_replace("{{detalles}}", "{}", $prompt);
        }else{

            //cargar relaciones
            $FormaPago = "";
            if($pedidoCurso->FormaPago != null && $pedidoCurso->FormaPago != ''){
                $FormaPago = $pedidoCurso->mi_forma_pago->texto;
            }

            $MetodoPago = "";
            if($pedidoCurso->MetodoPago != null && $pedidoCurso->MetodoPago != ''){
                $MetodoPago = $pedidoCurso->mi_metodo_pago->texto;
            }

            $Tipo = "";
            if($pedidoCurso->Tipo != null && $pedidoCurso->Tipo != ''){
                $Tipo = $pedidoCurso->Tipo;
            }

            $UsoCFDI = "";
            if($pedidoCurso->receptor->UsoCFDI != null && $pedidoCurso->receptor->UsoCFDI != ''){
                $UsoCFDI = $pedidoCurso->receptor->mi_uso_cfdi->texto;
            }

            $factura = [
                "UsoCFDI" => $UsoCFDI,
                "FormaPago" => $FormaPago,
                "MetodoPago" => $MetodoPago,
                "Tipo" => $Tipo,
            ];

            $facturaString = json_encode($factura);
            
            $prompt = str_replace("{{detalles}}", $facturaString, $prompt);
        } 

        $respB = $this->_davinciRespuestaPrompt($prompt);
        if ($respB['status'] == 200) {

            $cadena = $respB['text'];

            $log = [];
            array_push($log,$text_mensajes);
            array_push($log,$cadena);

            file_put_contents('webhook_log_cfdi_data.txt', print_r($log, true), FILE_APPEND);

            // $this->messageTextToCliente($cliente,$cadena);
            // //quitar el flujo
            // DB::table('bot_clientes')
            //     ->where('id', $cliente->id)
            //     ->update([
            //         'flow_id' => null,
            //     ]);
            // return 1;

            $posicionA = strpos($cadena, '{');
            $posicionB = strrpos($cadena, '}');
            $cadena = substr($cadena,$posicionA,$posicionB+1-($posicionA));

            if ($posicionA === false || $posicionB === false) {

                $message = $this->detallesDataFactura($cliente);
                $this->messageTextToCliente($cliente,$message);

                return 0; // Retornar cadena vacía si no se encuentran los caracteres
            }

            $obj = json_decode($cadena);

            if($pedidoCurso->receptor->Rfc == 'XAXX010101000'){
                $obj->UsoCFDI = 'Sin efectos fiscales';
                $obj->MetodoPago = 'Pago en una sola exhibición';
            }
            $obj->confirmar = 0;
            $obj->cancelar = 0;

            $cadenaEnMayusculas = strtoupper($text_mensajes);
            $subcadena = 'CANCELAR';

            // Buscar la subcadena en la cadena original (sensible a mayúsculas y minúsculas)
            $posicion = strpos($cadenaEnMayusculas, $subcadena);

            if ($posicion !== false) {
                $obj->confirmar = 0;
                $obj->cancelar = 1;
            }

            $subcadena = 'CONFIRMAR';

            // Buscar la subcadena en la cadena original (sensible a mayúsculas y minúsculas)
            $posicion = strpos($cadenaEnMayusculas, $subcadena);

            if ($posicion !== false) {
                $obj->confirmar = 1;
                $obj->cancelar = 0;
            }

            $pedidoCurso = CfdiComprobante::
                where('cliente_id',$cliente->id)
                //->with('conceptos')
                ->with(['conceptos' => function ($query){
                    $query->with('mi_clave_prod_serv')
                        ->with('mi_clave_unidad');
                }])
                ->where('status', 0)
                ->with('receptor')
                ->with('impuesto')
                ->with('timbre_fiscal_digital')
                ->with('archivo')
                ->with('mi_forma_pago')
                ->with('mi_metodo_pago')
                ->first();

            

            //Si quiere cancelar el proceso
            if(
                $obj->cancelar == 1
            ){

                //quitar el flujo
                DB::table('bot_clientes')
                    ->where('id', $cliente->id)
                    ->update([
                        'flow_id' => null,
                    ]);

                //elimino cotizacion curso en caso de que tenga
                if($pedidoCurso){
                    for ($i=0; $i < count($pedidoCurso->conceptos); $i++) { 
                        $pedidoCurso->conceptos[$i]->delete();
                    }
                    $pedidoCurso->receptor->delete();
                    $pedidoCurso->delete();
                }

                $message = 'Tu factura ha sido cancelada correctamente';

                $this->messageTextToCliente($cliente,$message);
                
                return 1;

            }

            //Si quiere confirmar la factura
            if(
                $obj->confirmar == 1
            ){

                if(
                    $pedidoCurso &&
                    $pedidoCurso->receptor->UsoCFDI != "" &&
                    $pedidoCurso->Fecha != "" &&
                    $pedidoCurso->FormaPago != "" &&
                    $pedidoCurso->MetodoPago != "" && 
                    $pedidoCurso->Tipo != "" 
                ){

                    return 'Data confirmada';
                                        
                }else{
                    $message = $this->detallesDataFactura($cliente);
                    $this->messageTextToCliente($cliente,$message);
                    
                    return 1;
                }  

            }

            //si no tiene factura en curso, creo una para las validaciones
            if(!$pedidoCurso){

                $Folio = (CfdiComprobante::count())+1;

                $Serie = (CfdiComprobante::
                    where('cliente_id',$cliente->id)
                    ->count())+1;

                //crear un pedido nuevo en curso
                $nuevoObj=CfdiComprobante::create([
                    'bot_id'=>$cliente->bot_id,
                    'cliente_id'=>$cliente->id,
                    'status'=>0,
                    'flag_cancelada'=>null,
                    'Serie'=>"S-".$empresa->id."-".$Serie,
                    'Folio'=>"F-".$empresa->id."-".$Folio,
                    'Fecha'=>date('Y-m-d\TH:i:s', time() - (60*60)),
                    'Sello'=>"",
                    'FormaPago'=>"",
                    'NoCertificado'=>"",
                    'Certificado'=>"",
                    'CondicionesDePago'=>"",
                    'Subtotal'=>0.00,
                    'Descuento'=>0.00,
                    'Moneda'=>"MXN",
                    'TipoCambio'=>"",
                    'Total'=>0.00,
                    'TipoDeComprobante'=>"I",
                    'Exportacion'=>"01",
                    'MetodoPago'=>"",
                    'LugarExpedicion'=>$empresa->CP,
                    'Confirmacion'=>"",
                    'estado'=>null,
                    'function'=>null,
                    'TasaIva'=>null,
                    'TasaIsr'=>null,
                    'Tipo'=>null,
                ]);

                $pedidoCurso = CfdiComprobante::
                    where('cliente_id',$cliente->id)
                    ->where('status', 0)
                    ->with('receptor')
                    //->with('conceptos')
                    ->with(['conceptos' => function ($query){
                        $query->with('mi_clave_prod_serv')
                            ->with('mi_clave_unidad');
                    }])
                    ->with('impuesto')
                    ->with('timbre_fiscal_digital')
                    ->with('archivo')
                    ->with('mi_forma_pago')
                    ->with('mi_metodo_pago')
                    ->first();
            }

            if(
                $obj->UsoCFDI != ""
            ){
                $UsoCFDI = $obj->UsoCFDI;

                $UsoCFDIBD = $this->checkCatalogo(3,$UsoCFDI);

                if($UsoCFDIBD){
                    DB::table('cfdi_receptor')
                        ->where('comprobante_id', $pedidoCurso->id)
                        ->update([
                            'UsoCFDI' => $UsoCFDIBD->id_aux,
                        ]);   
                }else{
                    // El UsoCFDI no existe en el catalogo
                    $message = 'El Uso de CFDI que ingresaste no está disponible en nuestro catálogo. Por favor, intenta ingresar un Uso de CFDI diferente. ⌚';
                    $this->messageTextToCliente($cliente,$message);
                    //return 1;
                }
            }

            if(
                $obj->FormaPago != ""
            ){
                $FormaPago = $obj->FormaPago;

                $FormaPagoBD = $this->checkCatalogo(4,$FormaPago);

                if($FormaPagoBD){
                    DB::table('cfdi_comprobante')
                        ->where('cliente_id', $cliente->id)
                        ->where('status', 0)
                        ->update([
                            'FormaPago' => $FormaPagoBD->id,
                        ]);   
                }else{
                    // La FormaPago no existe en el catalogo
                    $message = 'La Forma de Pago que ingresaste no está disponible en nuestro catálogo. Por favor, intenta ingresar una Forma de Pago diferente. 💸';
                    $this->messageTextToCliente($cliente,$message);
                    //return 1;
                }
            }

            if(
                $obj->MetodoPago != ""
            ){
                $MetodoPago = $obj->MetodoPago;

                $MetodoPagoBD = $this->checkCatalogo(5,$MetodoPago);

                if($MetodoPagoBD){
                    DB::table('cfdi_comprobante')
                        ->where('cliente_id', $cliente->id)
                        ->where('status', 0)
                        ->update([
                            'MetodoPago' => $MetodoPagoBD->id_aux,
                        ]);   
                }else{
                    // El MetodoPago no existe en el catalogo
                    $message = 'El Método de Pago que ingresaste no está disponible en nuestro catálogo. Por favor, intenta ingresar un Método de Pago diferente. 💵';
                    $this->messageTextToCliente($cliente,$message);
                    //return 1;
                }
            }

            if(
                $obj->Tipo != ""

            ){

                $Tipo = $obj->Tipo;

                DB::table('cfdi_comprobante')
                    ->where('cliente_id', $cliente->id)
                    ->where('status', 0)
                    ->update([
                        'Tipo' => $Tipo,
                    ]);

            }

            $message = $this->detallesDataFactura($cliente);

            if($message == 'Data confirmada'){
                return 'Data confirmada';
            }

            $respC = $this->_messageText($cliente->bot_id,$cliente->telefono,$message);

            if ($respC['status'] == 200) {

                $this->storeMsgChat($cliente->bot_id,$cliente->id,null,$message,0); //bot

                return 1;

            }else{

                return 0;

            }
            
        }else{

            $this->messageTextToCliente($cliente,$respB['error']);
            return 0;

        } 

    }

    public function detallesDataFactura($cliente){

        $empresa = CfdiEmpresa::with('producto')
            ->with('mi_regimen_fiscal')
            ->where('bot_cliente_id', $cliente->id)
            ->first();

        $pedidoCurso = CfdiComprobante::
            where('cliente_id',$cliente->id)
            ->where('status', 0)
            ->with('receptor')
            //->with('conceptos')
            ->with(['conceptos' => function ($query){
                $query->with('mi_clave_prod_serv')
                    ->with('mi_clave_unidad');
            }])
            ->with('impuesto')
            ->with('timbre_fiscal_digital')
            ->with('archivo')
            ->with('mi_forma_pago')
            ->with('mi_metodo_pago')
            ->first();

        $salto = '
';

        $message = '';

        // Si no hay informacion referente la factura
        if(
            $pedidoCurso && (
                $pedidoCurso->receptor->Rfc != 'XAXX010101000' && (
                    $pedidoCurso->receptor->UsoCFDI == "" &&
                    $pedidoCurso->FormaPago == "" &&
                    $pedidoCurso->MetodoPago == "" && 
                    $pedidoCurso->Tipo == ""
                ) ||
                $pedidoCurso->receptor->Rfc == 'XAXX010101000' && (
                    $pedidoCurso->FormaPago == "" &&
                    $pedidoCurso->Tipo == ""
                )
            )
        ){


            $message = 'Por favor, proporciona los datos para tu factura en el siguiente formato:';

            $this->messageTextToCliente($cliente,$message);

$message = 'Factura Neta o Más IVA:'.$salto;

if ($pedidoCurso->receptor->Rfc != 'XAXX010101000') {
    $message .= 'Uso de CFDI:'.$salto;
}

$message .= 'Forma de Pago:'.$salto;

if ($pedidoCurso->receptor->Rfc != 'XAXX010101000') {
    $message .= 'Método de Pago:';
}

            
            return $message;

        }else if(
            $pedidoCurso && (
                $pedidoCurso->receptor->Rfc != 'XAXX010101000' && (
                    $pedidoCurso->receptor->UsoCFDI == "" ||
                    $pedidoCurso->FormaPago == "" ||
                    $pedidoCurso->MetodoPago == "" || 
                    $pedidoCurso->Tipo == ""
                ) ||
                $pedidoCurso->receptor->Rfc == 'XAXX010101000' && (
                    $pedidoCurso->FormaPago == "" ||
                    $pedidoCurso->Tipo == ""
                )
            )
        ){

            $Tipo = '';
            if($pedidoCurso->Tipo == 1){
                $Tipo = 'Neta';
            }else if($pedidoCurso->Tipo == 2){
                $Tipo = 'Más IVA';
            }

            $message = '*Detalles:*'.$salto.$salto.
            '*Neta / Más IVA:* '.$Tipo.$salto;

            if ($pedidoCurso->receptor->Rfc != 'XAXX010101000') {
                $message .= '*Uso de CFDI:* '.$pedidoCurso->receptor->mi_uso_cfdi->texto.$salto;
            }

            $message .= '*Forma de Pago:* '.$pedidoCurso->mi_forma_pago->texto.$salto;

            if ($pedidoCurso->receptor->Rfc != 'XAXX010101000') {
                $message .= '*Método de Pago:* '.$pedidoCurso->mi_metodo_pago->texto.$salto.$salto;
            }
            
            $message .= $salto.'*Completa los datos faltantes.*';
                                
        }else{

            $Tipo = '';
            if($pedidoCurso->Tipo == 1){
                $Tipo = 'Neta';
            }else if($pedidoCurso->Tipo == 2){
                $Tipo = 'Más IVA';
            }

            $message = '*Detalles:*'.$salto.$salto.
            '*Neta / Más IVA:* '.$Tipo.$salto;
            
            if ($pedidoCurso->receptor->Rfc != 'XAXX010101000') {
                $message .= '*Uso de CFDI:* '.$pedidoCurso->receptor->mi_uso_cfdi->texto.$salto;
            }

            $message .= '*Forma de Pago:* '.$pedidoCurso->mi_forma_pago->texto.$salto;

            if ($pedidoCurso->receptor->Rfc != 'XAXX010101000') {
                $message .= '*Método de Pago:* '.$pedidoCurso->mi_metodo_pago->texto.$salto.$salto;
            }

            $message .= $salto.'*Escribe la Opción:*'.$salto.$salto.
            'Cancelar'.$salto.
            'Confirmar';

            $message = 'Data confirmada';

        }

        return $message;
    }

    public function flowCfdiConceptosFactura($cliente)
    {
        set_time_limit(500);

        if($cliente->hab_facturas != 1){
            $this->flowNoAplicable($cliente);
            return 1;
        }

        $empresa = CfdiEmpresa::
            with(['producto' => function ($query){
                $query->with('mi_clave_prod_serv')
                    ->with('mi_clave_unidad');
            }])
            ->with('mi_regimen_fiscal')
            ->where('bot_cliente_id', $cliente->id)
            ->first();

        $salto = '
';

        $prompt = 'Creando una factura a partir del mensaje de un usuario

Datos recaudados hasta el momento:

{{detalles}}

Mensaje del cliente: {{mensaje}}

Genera un JSON con la siguiente estructura:

{
   "TasaIva": "",
   "TasaIsr": "",
   "conceptos": [{
      "ClaveProdServ": "",
      "Cantidad": "",
      "ClaveUnidad": "",
      "Descripcion": "",
      "ValorUnitario": "",
      "Descuento": ""
   }],
   "confirmar": 0,
   "cancelar": 0
}

Instrucciones:
- Sino hay niguna información de referente a la factura retorna el JSON con sus propiedades vacias.
- Solo usa la información del mensaje, no inventes ningún dato.
- La propiedad ClaveProdServ del array de conceptos puede ser una clave de 8 numeros o el nombre de producto/servicio.
- En la propiedad ClaveUnidad del array de conceptos solo coloca el texto que indica el usuario, no coloques su acrónimo.
- La propiedad TasaIva, es la tasa de retención de IVA, debe ser un numero positivo con cuatro decimales.
- La propiedad TasaIsr, es la tasa de retención de ISR, debe ser un numero positivo con cuatro decimales.
- La propiedad Cantidad del array de conceptos debe ser un numero mayor a cero con dos decimales.
- La propiedad ValorUnitario del array de conceptos debe ser un numero positivo con dos decimales.
- La propiedad Descuento del array de conceptos debe ser un numero positivo con dos decimales.
- El array conceptos del JSON resultante puede contener N cantidad de elementos.
- Solo mantener una única instancia de cada elemento en el array conceptos del JSON resultante.
- Extrae la información del mensaje para modificar los datos recaudados y completar el JSON resultante.
- Solo quiero que generes y retornes únicamente el JSON con la estructura indicada, no debes generar código, solo el JSON que te estoy indicando.

Valores posibles para "confirmar":
- 1: El usuario confirma que los datos de la factura son correctos, solo se puede confirmar si el usuario envía alguno de los mensajes de ejemplo para confirmar.
- 0: El usuario no está confirmando los datos de la factura.

Valores posibles para "cancelar":
- 1: El usuario desea cancelar la factura.
- 0: El usuario no quiere cancelar la factura.

Ejemplos de mensajes:
Para "confirmar factura": "confirmar factura", "confirmar", "Los datos son correctos", "De acuerdo", "Confirmar", "Correcto", "Si".
Para "cancelar factura": "Cancelar factura", "Cancelar".';
        
        $text_mensajes = '';
        for ($i=0; $i < count($cliente->mensajes); $i++) { 
            if($i == 0){
                $text_mensajes = $cliente->mensajes[$i];
            }else{
                $text_mensajes = $text_mensajes.', '.$cliente->mensajes[$i];
            }
        }
        
        $prompt = str_replace("{{mensaje}}", $text_mensajes, $prompt);
        //$prompt = $this->contextoFecha($prompt);

        $pedidoCurso = CfdiComprobante::
            where('cliente_id',$cliente->id)
            ->where('status', 0)
            ->with('receptor')
            //->with('conceptos')
            ->with(['conceptos' => function ($query){
                $query->with('mi_clave_prod_serv')
                    ->with('mi_clave_unidad');
            }])
            ->with('impuesto')
            ->with('timbre_fiscal_digital')
            ->with('archivo')
            ->with('mi_forma_pago')
            ->with('mi_metodo_pago')
            ->first();

        if(!$pedidoCurso){
            $prompt = str_replace("{{detalles}}", "{}", $prompt);
        }else{

            $conceptos = [];
            for ($i=0; $i < count($pedidoCurso->conceptos); $i++) { 

                //cargar relaciones
                $ClaveProdServ = "";
                if($pedidoCurso->conceptos[$i]->ClaveProdServ != null && $pedidoCurso->conceptos[$i]->ClaveProdServ != ''){
                    $ClaveProdServ = $pedidoCurso->conceptos[$i]->mi_clave_prod_serv->id;
                }

                $ClaveUnidad = "";
                if($pedidoCurso->conceptos[$i]->ClaveUnidad != null && $pedidoCurso->conceptos[$i]->ClaveUnidad != ''){
                    $ClaveUnidad = $pedidoCurso->conceptos[$i]->mi_clave_unidad->id;
                }

                $resul = (object) [
                    'ClaveProdServ' => $ClaveProdServ,
                    'Cantidad' => number_format(($pedidoCurso->conceptos[$i]->Cantidad), 2, '.', ''),
                    'ClaveUnidad' => $ClaveUnidad,
                    'Descripcion' => $pedidoCurso->conceptos[$i]->Descripcion,
                    'ValorUnitario' => number_format(($pedidoCurso->conceptos[$i]->ValorUnitario), 2, '.', ''),
                    'Descuento' => number_format(($pedidoCurso->conceptos[$i]->Descuento), 2, '.', ''),
                ];
                array_push($conceptos,$resul);
            }

            //cargar relaciones
            $TasaIva = "";
            if($pedidoCurso->TasaIva != null && $pedidoCurso->TasaIva != ''){
                $TasaIva = $pedidoCurso->TasaIva;
            }

            $TasaIsr = "";
            if($pedidoCurso->TasaIsr != null && $pedidoCurso->TasaIsr != ''){
                $TasaIsr = $pedidoCurso->TasaIsr;
            }

            $factura = [
                "TasaIva" => $TasaIva,
                "TasaIsr" => $TasaIsr,
                "conceptos" => $conceptos,
            ];

            $facturaString = json_encode($factura);
            
            $prompt = str_replace("{{detalles}}", $facturaString, $prompt);
        } 

        $respB = $this->_davinciRespuestaPrompt($prompt);
        if ($respB['status'] == 200) {

            $cadena = $respB['text'];

            $log = [];
            array_push($log,$text_mensajes);
            array_push($log,$cadena);

            file_put_contents('webhook_log_cfdi_comprobante.txt', print_r($log, true), FILE_APPEND);

            // $this->messageTextToCliente($cliente,$cadena);
            // //quitar el flujo
            // DB::table('bot_clientes')
            //     ->where('id', $cliente->id)
            //     ->update([
            //         'flow_id' => null,
            //     ]);
            // return 1;

            $posicionA = strpos($cadena, '{');
            $posicionB = strrpos($cadena, '}');
            $cadena = substr($cadena,$posicionA,$posicionB+1-($posicionA));

            if ($posicionA === false || $posicionB === false) {

                $message = $this->detallesConceptosFactura($cliente);
                $this->messageTextToCliente($cliente,$message);

                return 0; // Retornar cadena vacía si no se encuentran los caracteres
            }

            $obj = json_decode($cadena);

            //fusionar los items duplicados
            $array_conceptos = [];
            for ($i=0; $i < count($obj->conceptos); $i++) {

                //si tiene activo el producto por defecto y tiene un producto
                if($empresa->flag_producto == 1 && $empresa->producto &&
                 $empresa->producto->ClaveProdServ && $empresa->producto->ClaveUnidad){
                    //cargar relaciones
                    $ClaveProdServ = $empresa->producto->mi_clave_prod_serv->id;
                    $ClaveUnidad = $empresa->producto->mi_clave_unidad->id;

                    $obj->conceptos[$i]->ClaveProdServ = $ClaveProdServ;
                    $obj->conceptos[$i]->ClaveUnidad = $ClaveUnidad;
                }

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
                $Descripcion = strtr($obj->conceptos[$i]->Descripcion, $acentos);

                $obj->conceptos[$i]->Descripcion = strtoupper($Descripcion);
                $obj->conceptos[$i]->ClaveUnidad = strtoupper($obj->conceptos[$i]->ClaveUnidad);

                $esta = false;

                for ($j=0; $j < count($array_conceptos); $j++) { 

                    if(
                        $obj->conceptos[$i]->ClaveProdServ == $array_conceptos[$j]->ClaveProdServ &&
                        $obj->conceptos[$i]->ClaveUnidad == $array_conceptos[$j]->ClaveUnidad &&
                        $obj->conceptos[$i]->Descripcion == $array_conceptos[$j]->Descripcion 
                        //$obj->conceptos[$i]->ValorUnitario == $array_conceptos[$j]->ValorUnitario &&
                        //$obj->conceptos[$i]->Descuento == $array_conceptos[$j]->Descuento

                    ){
                        $esta = true;
                        $array_conceptos[$j]->Cantidad = $array_conceptos[$j]->Cantidad + $obj->conceptos[$i]->Cantidad;
                        $array_conceptos[$j]->ValorUnitario = $array_conceptos[$j]->ValorUnitario;
                    }
                }
                if(!$esta){
                    array_push($array_conceptos,$obj->conceptos[$i]);
                }

            }
            $obj->conceptos = $array_conceptos;

            $cadenaEnMayusculas = strtoupper($text_mensajes);
            $subcadena = 'CANCELAR';

            // Buscar la subcadena en la cadena original (sensible a mayúsculas y minúsculas)
            $posicion = strpos($cadenaEnMayusculas, $subcadena);

            if ($posicion !== false) {
                $obj->confirmar = 0;
                $obj->cancelar = 1;
            }

            $subcadena = 'CONFIRMAR';

            // Buscar la subcadena en la cadena original (sensible a mayúsculas y minúsculas)
            $posicion = strpos($cadenaEnMayusculas, $subcadena);

            if ($posicion !== false) {
                $obj->confirmar = 1;
                $obj->cancelar = 0;
            }

            $pedidoCurso = CfdiComprobante::
                where('cliente_id',$cliente->id)
                ->where('status', 0)
                ->with('receptor')
                //->with('conceptos')
                ->with(['conceptos' => function ($query){
                    $query->with('mi_clave_prod_serv')
                        ->with('mi_clave_unidad');
                }])
                ->with('impuesto')
                ->with('timbre_fiscal_digital')
                ->with('archivo')
                ->with('mi_forma_pago')
                ->with('mi_metodo_pago')
                ->first();


            //Si quiere cancelar el proceso
            if(
                $obj->cancelar == 1
            ){

                //quitar el flujo
                DB::table('bot_clientes')
                    ->where('id', $cliente->id)
                    ->update([
                        'flow_id' => null,
                    ]);

                //elimino cotizacion curso en caso de que tenga
                if($pedidoCurso){
                    for ($i=0; $i < count($pedidoCurso->conceptos); $i++) { 
                        $pedidoCurso->conceptos[$i]->delete();
                    }
                    $pedidoCurso->receptor->delete();
                    $pedidoCurso->delete();
                }

                $message = 'Tu factura ha sido cancelada correctamente';

                $this->messageTextToCliente($cliente,$message);
                
                return 1;

            }

            //Si quiere confirmar la cotizacion
            if(
                $obj->confirmar == 1
            ){

                if(
                    $pedidoCurso &&
                    count($pedidoCurso->conceptos)>0
                ){

                    //quitar el flujo
                    DB::table('bot_clientes')
                        ->where('id', $cliente->id)
                        ->update([
                            'flow_id' => null,
                        ]);

                    $SubTotal = 0;
                    $Descuento = 0;
                    $Total = 0;
                    $TotalImpuestosTrasladados = 0;
                    $TotalImpuestosRetenidos = 0;

                    $conceptos = '*Conceptos:*'.$salto.$salto;
                    for ($i=0; $i < count($pedidoCurso->conceptos); $i++) {

                        if($empresa->flag_objetoImp == 1){
                            $Base = $pedidoCurso->conceptos[$i]->Importe - $pedidoCurso->conceptos[$i]->Descuento;
                            $TotalImpuestosTrasladados = $TotalImpuestosTrasladados + ($Base * 0.16);
                            $TotalImpuestosTrasladados = number_format($TotalImpuestosTrasladados, 2, '.', '');

                            if($empresa->flag_retencion == 1){
                                $retencionIva = $Base * ($pedidoCurso->TasaIva/100);
                                $retencionIva = number_format(($retencionIva), 2, '.', '');

                                $retencionIsr = $Base * ($pedidoCurso->TasaIsr/100);
                                $retencionIsr = number_format(($retencionIsr), 2, '.', '');

                                $TotalImpuestosRetenidos = $TotalImpuestosRetenidos + $retencionIva + $retencionIsr;

                                $TotalImpuestosRetenidos = number_format($TotalImpuestosRetenidos, 2, '.', '');
                            }
                        }

                        $conceptos = $conceptos
                        .'*Clave de Prod o Serv:* '.$pedidoCurso->conceptos[$i]->mi_clave_prod_serv->id.$salto
                        .'*Cantidad:* '.$pedidoCurso->conceptos[$i]->Cantidad.$salto
                        .'*Clave de Unidad:* '.$pedidoCurso->conceptos[$i]->mi_clave_unidad->id.$salto
                        .'*Descripción:* '.$pedidoCurso->conceptos[$i]->Descripcion.$salto
                        .'*Valor Unitario:* '.$pedidoCurso->conceptos[$i]->ValorUnitario.$salto;

                        if ($empresa->flag_descuento == 1) {
                            $conceptos .= '*Descuento:* '.$pedidoCurso->conceptos[$i]->Descuento.$salto;
                        }

                        $conceptos .= '*Importe:* '.$pedidoCurso->conceptos[$i]->Importe.$salto
                        .$salto;

                        $SubTotal = $SubTotal + ($pedidoCurso->conceptos[$i]->Importe);
                        $Descuento = $Descuento + ($pedidoCurso->conceptos[$i]->Descuento);
                    }

                    $SubTotal = number_format($SubTotal, 2, '.', '');
                    $Descuento = number_format($Descuento, 2, '.', '');

                    $Total = $SubTotal - $Descuento + $TotalImpuestosTrasladados - $TotalImpuestosRetenidos;

                    $Total = number_format($Total, 2, '.', '');

                    $pedidoCurso->SubTotal = $SubTotal;
                    $pedidoCurso->Descuento = $Descuento;
                    $pedidoCurso->Total = $Total;

                    //pasar a confirmado
                    $pedidoCurso->status = 1;
                    $pedidoCurso->save();

                    $Tipo = '';
                    if($pedidoCurso->Tipo == 1){
                        $Tipo = 'Neta';
                    }else if($pedidoCurso->Tipo == 2){
                        $Tipo = 'Más IVA';
                    }

                    $message = '✅ *Factura confirmada:*'.$salto.$salto.
                    '*Tipo:* '.$Tipo.$salto.
                    '*Rfc Receptor:* '.$pedidoCurso->receptor->Rfc.$salto.
                    '*Uso de CFDI:* '.$pedidoCurso->receptor->mi_uso_cfdi->texto.$salto.
                    '*Fecha:* '.$pedidoCurso->Fecha.$salto.
                    '*Forma de Pago:* '.$pedidoCurso->mi_forma_pago->texto.$salto.
                    '*Método de Pago:* '.$pedidoCurso->mi_metodo_pago->texto.$salto.
                    $salto.$conceptos.
                    'Subtotal *'.$SubTotal.'* 💲'.$salto;
                    
                    if ($empresa->flag_descuento == 1) {
                        $message .= 'Descuento *'.$Descuento.'* 💲'.$salto;
                    }

                    if ($empresa->flag_objetoImp == 1) {
                        $message .= 'Impuestos Traslados *'.$TotalImpuestosTrasladados.'* 💲'.$salto;
                    }

                    if ($empresa->flag_objetoImp == 1 && $empresa->flag_retencion == 1) {
                        $message .= 'Impuestos Retenidos *'.$TotalImpuestosRetenidos.'* 💲'.$salto;
                    }

                    $message .= 'Total *'.$Total.'* 💰';
                
                    //mensaje de texto con el detalle
                    $this->messageTextToCliente($cliente,$message);

                    $message = 'Estamos generando tu factura. Por favor, espera un momento...';
                    $this->messageTextToCliente($cliente,$message);

                    $resTimbrado = $this->timbrar($pedidoCurso->id);

                    if($resTimbrado != 1){

                        //rectivar el flujo de las facturas
                        DB::table('bot_clientes')
                            ->where('id', $cliente->id)
                            ->update([
                                'flow_id' => 4,
                            ]);

                        //pasar a en curso de nuevo
                        $pedidoCurso->status = 0;
                        $pedidoCurso->save();

                        $message = $resTimbrado;
                        $this->messageTextToCliente($cliente,$message);

                        $message = $this->detallesConceptosFactura($cliente);
                        $this->messageTextToCliente($cliente,$message);

                    }else{

                        $count_facturas = $cliente->count_facturas + 1;
                        DB::table('bot_clientes')
                        ->where('id', $cliente->id)
                        ->update([
                            'count_facturas' => $count_facturas,
                        ]);

                        $document = $this->facturaPdf($pedidoCurso->id);

                        DB::table('cfdi_archivos')
                            ->where('comprobante_id', $pedidoCurso->id)
                            ->update([
                                'pdf' => $document,
                            ]);

                        $this->_messageDocument($cliente->bot_id,$cliente->telefono,$document,'factura');

                        $this->storeMsgChat($cliente->bot_id,$cliente->id,null,$document,0); //bot

                        $cfdiArchivo = CfdiArchivo::
                            where('comprobante_id', $pedidoCurso->id)
                            ->first();

                        $this->_messageDocument($cliente->bot_id,$cliente->telefono,$cfdiArchivo->xml_archivo,'factura','xml');

                        $this->storeMsgChat($cliente->bot_id,$cliente->id,null,$cfdiArchivo->xml_archivo,0); //bot

                        $this->emailFactura($pedidoCurso->id);  
                    }

                    return 1;
                                        
                }else{
                    $message = $this->detallesConceptosFactura($cliente);
                    $this->messageTextToCliente($cliente,$message);
                    
                    return 1;
                }  

            }

            //si no tiene factura en curso, creo una para las validaciones
            if(!$pedidoCurso){

                $Folio = (CfdiComprobante::count())+1;

                $Serie = (CfdiComprobante::
                    where('cliente_id',$cliente->id)
                    ->count())+1;

                //crear un pedido nuevo en curso
                $nuevoObj=CfdiComprobante::create([
                    'bot_id'=>$cliente->bot_id,
                    'cliente_id'=>$cliente->id,
                    'status'=>0,
                    'flag_cancelada'=>null,
                    'Serie'=>"S-".$empresa->id."-".$Serie,
                    'Folio'=>"F-".$empresa->id."-".$Folio,
                    'Fecha'=>date('Y-m-d\TH:i:s', time() - (60*60)),
                    'Sello'=>"",
                    'FormaPago'=>"",
                    'NoCertificado'=>"",
                    'Certificado'=>"",
                    'CondicionesDePago'=>"",
                    'Subtotal'=>0.00,
                    'Descuento'=>0.00,
                    'Moneda'=>"MXN",
                    'TipoCambio'=>"",
                    'Total'=>0.00,
                    'TipoDeComprobante'=>"I",
                    'Exportacion'=>"01",
                    'MetodoPago'=>"",
                    'LugarExpedicion'=>$empresa->CP,
                    'Confirmacion'=>"",
                    'estado'=>null,
                    'function'=>null,
                    'TasaIva'=>null,
                    'TasaIsr'=>null,
                    'Tipo'=>null,
                ]);

                $pedidoCurso = CfdiComprobante::
                    where('cliente_id',$cliente->id)
                    ->where('status', 0)
                    ->with('receptor')
                    //->with('conceptos')
                    ->with(['conceptos' => function ($query){
                        $query->with('mi_clave_prod_serv')
                            ->with('mi_clave_unidad');
                    }])
                    ->with('impuesto')
                    ->with('timbre_fiscal_digital')
                    ->with('archivo')
                    ->with('mi_forma_pago')
                    ->with('mi_metodo_pago')
                    ->first();
            }

            if(
                $empresa->flag_objetoImp == 1 && 
                $empresa->flag_retencion == 1 &&
                $obj->TasaIva != ""
            ){

                $TasaIva = $obj->TasaIva;

                if($TasaIva > 0){
                    DB::table('cfdi_comprobante')
                        ->where('cliente_id', $cliente->id)
                        ->where('status', 0)
                        ->update([
                            'TasaIva' => $TasaIva,
                        ]);
                }else{
                    $message = 'La propiedad Tasa de retención de IVA debe ser mayor o igual a cero.';
                    $this->messageTextToCliente($cliente,$message);
                } 
            }

            if(
                $empresa->flag_objetoImp == 1 && 
                $empresa->flag_retencion == 1 &&
                $obj->TasaIsr != ""
            ){

                $TasaIsr = $obj->TasaIsr;

                if($TasaIsr > 0){
                    DB::table('cfdi_comprobante')
                        ->where('cliente_id', $cliente->id)
                        ->where('status', 0)
                        ->update([
                            'TasaIsr' => $TasaIsr,
                        ]);
                }else{
                    $message = 'La propiedad Tasa de retención de ISR debe ser mayor o igual a cero.';
                    $this->messageTextToCliente($cliente,$message);
                }
            }

            //validar formato de entrada
            for ($i=0; $i < count($obj->conceptos); $i++) { 
                if(
                    $obj->conceptos[$i]->ClaveProdServ == "" ||
                    $obj->conceptos[$i]->ClaveUnidad == "" ||
                    $obj->conceptos[$i]->Descripcion == "" 
                ){

                    $flag_primero = 0;
                    $primero = ' ';
                    if(count($pedidoCurso->conceptos)==0){
                        $flag_primero = 1;
                        $primero = ' primer ';
                    }

                    $message = 'Por favor, proporciona el'.$primero.'concepto para tu factura en el siguiente formato:';

                    $this->messageTextToCliente($cliente,$message);

$message = '';

if ($empresa->flag_producto != 1) {
    $message .= 'Clave de Prod o Serv:';
}

$message .= $salto.'Cantidad:';

if ($empresa->flag_producto != 1) {
    $message .= $salto.'Clave de Unidad:';
}

$message .= $salto.'Descripción:
Valor Unitario:';

if ($empresa->flag_descuento == 1) {
    $message .= $salto.'Descuento:';
}

if ($flag_primero == 1 && $empresa->flag_objetoImp == 1 && $empresa->flag_retencion == 1) {
    $message .= $salto.'Tasa de retención de IVA:'.
                $salto.'Tasa de retención de ISR:';
}


                    $this->messageTextToCliente($cliente,$message);
                    
                    return 1;

                }

                if(
                    $obj->conceptos[$i]->Cantidad == "" ||
                    $obj->conceptos[$i]->Cantidad < 0
                ){

                    $message = 'La propiedad Cantidad debe ser mayor o igual a cero.';
                    $this->messageTextToCliente($cliente,$message);

                    $message = $this->detallesConceptosFactura($cliente);
                    $this->messageTextToCliente($cliente,$message);
                    
                    return 1;

                }

                if(
                    $obj->conceptos[$i]->ValorUnitario == "" ||
                    $obj->conceptos[$i]->ValorUnitario <= 0
                ){

                    $message = 'La propiedad Valor Unitario debe ser mayor a cero.';
                    $this->messageTextToCliente($cliente,$message);

                    $message = $this->detallesConceptosFactura($cliente);
                    $this->messageTextToCliente($cliente,$message);
                    
                    return 1;

                }

                if($empresa->flag_descuento == 1){
                    if($obj->conceptos[$i]->Descuento == ""){
                        $obj->conceptos[$i]->Descuento = 0.00;
                    }else{
                        $obj->conceptos[$i]->Descuento = number_format($obj->conceptos[$i]->Descuento, 2, '.', '');
                    }
                    $Importe = $obj->conceptos[$i]->Cantidad*$obj->conceptos[$i]->ValorUnitario;
                    if(
                        $obj->conceptos[$i]->Descuento != "" &&
                        ($obj->conceptos[$i]->Descuento < 0 ||
                        $obj->conceptos[$i]->Descuento > $Importe)
                        
                    ){

                        $message = 'La propiedad Descuento debe ser mayor o igual a cero y menor o igual al importe.';
                        $this->messageTextToCliente($cliente,$message);

                        $message = $this->detallesConceptosFactura($cliente);
                        $this->messageTextToCliente($cliente,$message);
                        
                        return 1;

                    }
                }else{
                    $obj->conceptos[$i]->Descuento = 0.00;
                }

                $ClaveProdServ = $obj->conceptos[$i]->ClaveProdServ;
                //checar si existe en el catalogo
                $ClaveProdServBD = $this->checkCatalogo(6,$ClaveProdServ);

                if(!$ClaveProdServBD){
                    // La ClaveProdServ no existe en el catalogo
                    $message = 'La Clave de Prod o Serv que ingresaste no está disponible en nuestro catálogo. Por favor, intenta ingresar una Clave de Prod o Serv diferente.';
                    $this->messageTextToCliente($cliente,$message);

                    $message = $this->detallesConceptosFactura($cliente);
                    $this->messageTextToCliente($cliente,$message);

                    return 1;   
                }else{
                    $obj->conceptos[$i]->ClaveProdServ = $ClaveProdServBD->id;
                }

                $ClaveUnidad = $obj->conceptos[$i]->ClaveUnidad;
                $ClaveUnidadBD = $this->checkCatalogo(7,$ClaveUnidad);

                if(!$ClaveUnidadBD){
                    // La ClaveUnidad no existe en el catalogo
                    $message = 'La Clave de Unidad que ingresaste no está disponible en nuestro catálogo. Por favor, intenta ingresar una Clave de Unidad diferente.';
                    $this->messageTextToCliente($cliente,$message);

                    $message = $this->detallesConceptosFactura($cliente);
                    $this->messageTextToCliente($cliente,$message);

                    return 1;   
                }else{
                    $obj->conceptos[$i]->ClaveUnidad = $ClaveUnidadBD->id;
                }

            }

            $pedidoCurso = CfdiComprobante::
                where('cliente_id',$cliente->id)
                ->where('status', 0)
                ->with('receptor')
                //->with('conceptos')
                ->with(['conceptos' => function ($query){
                    $query->with('mi_clave_prod_serv')
                        ->with('mi_clave_unidad');
                }])
                ->with('impuesto')
                ->with('timbre_fiscal_digital')
                ->with('archivo')
                ->with('mi_forma_pago')
                ->with('mi_metodo_pago')
                ->first();

            //validar insercion o edicion
            for ($i=0; $i < count($obj->conceptos); $i++) {
                $esta = false;
                for ($j=0; $j < count($pedidoCurso->conceptos); $j++) { 

                    if(
                        $obj->conceptos[$i]->ClaveProdServ == $pedidoCurso->conceptos[$j]->mi_clave_prod_serv->id &&
                        $obj->conceptos[$i]->ClaveUnidad == $pedidoCurso->conceptos[$j]->mi_clave_unidad->id &&
                        $obj->conceptos[$i]->Descripcion == $pedidoCurso->conceptos[$j]->Descripcion 
                        //$obj->conceptos[$i]->ValorUnitario == $pedidoCurso->conceptos[$j]->ValorUnitario &&
                        //$obj->conceptos[$i]->Descuento == $pedidoCurso->conceptos[$j]->Descuento
                    ){
                        $esta = true;

                        $Importe = number_format($obj->conceptos[$i]->ValorUnitario * $obj->conceptos[$i]->Cantidad, 2, '.', '');

                        //Neta
                        if($pedidoCurso->Tipo == 1){
                            $Base = $Importe - $obj->conceptos[$i]->Descuento;
                            $Importe = ($Base / 1.16);
                            $Importe = number_format($Importe, 2, '.', '');
                        }

                        //modificar importe
                        $pedidoCurso->conceptos[$j]->Cantidad = number_format($obj->conceptos[$i]->Cantidad, 2, '.', '');
                        $pedidoCurso->conceptos[$j]->ValorUnitario = number_format($obj->conceptos[$i]->ValorUnitario, 2, '.', '');
                        //$pedidoCurso->conceptos[$j]->Importe = number_format($obj->conceptos[$i]->Cantidad * $obj->conceptos[$i]->ValorUnitario, 2, '.', '');
                        $pedidoCurso->conceptos[$j]->Importe = number_format($Importe, 2, '.', '');
                        $pedidoCurso->conceptos[$j]->save();

                        if($obj->conceptos[$i]->Cantidad == 0){
                            $pedidoCurso->conceptos[$j]->delete();
                        }

                    }
                }
                if(!$esta){

                    $ClaveProdServ = $obj->conceptos[$i]->ClaveProdServ;
                    $ClaveProdServBD = $this->checkCatalogo(6,$ClaveProdServ);

                    $ClaveUnidad = $obj->conceptos[$i]->ClaveUnidad;
                    $ClaveUnidadBD = $this->checkCatalogo(7,$ClaveUnidad);

                    //validar insercion
                    $flag_insertar = 0;

                    if($pedidoCurso->Tipo != "" && $pedidoCurso->Tipo != null){
                        if($empresa->flag_objetoImp != 1){
                            $flag_insertar = 1;
                        }else if($empresa->flag_objetoImp == 1){
                            if($empresa->flag_retencion != 1){
                                $flag_insertar = 1;
                            }else if($empresa->flag_retencion == 1){
                                if(
                                    $pedidoCurso->TasaIva != "" && $pedidoCurso->TasaIva != null &&
                                    $pedidoCurso->TasaIsr != "" && $pedidoCurso->TasaIsr != null
                                ){
                                    $flag_insertar = 1;
                                }
                            } 
                        }
                    }

                    if($flag_insertar == 1){

                        $Importe = number_format($obj->conceptos[$i]->ValorUnitario * $obj->conceptos[$i]->Cantidad, 2, '.', '');

                        //Neta
                        if($pedidoCurso->Tipo == 1){

                            $Base = $Importe - $obj->conceptos[$i]->Descuento;
                            $Importe = ($Base / 1.16);
                            $Importe = number_format($Importe, 2, '.', '');
                        }

                        //agregar nuevo concepto
                        $nuevoConcepto=CfdiConcepto::create([
                            'comprobante_id' => $pedidoCurso->id,
                            'ClaveProdServ' => $ClaveProdServBD->id_aux,
                            'NoIdentificacion' => "",
                            'Cantidad' => number_format($obj->conceptos[$i]->Cantidad, 2, '.', ''),
                            'ClaveUnidad' => $ClaveUnidadBD->id_aux,
                            'Unidad' => $ClaveUnidadBD->texto,
                            'Descripcion' => $obj->conceptos[$i]->Descripcion,
                            'ValorUnitario' => number_format($obj->conceptos[$i]->ValorUnitario, 2, '.', ''),
                            //'Importe' => number_format($obj->conceptos[$i]->ValorUnitario * $obj->conceptos[$i]->Cantidad, 2, '.', ''),
                            'Importe' => number_format($Importe, 2, '.', ''),
                            'Descuento' => $obj->conceptos[$i]->Descuento,
                            'ObjetoImp' => $empresa->flag_objetoImp,
                            'ObjetoImpRet' => $empresa->flag_retencion,
                        ]);
                    }

                    
                }

            }

            //validar eliminacion
            $pedidoCurso = CfdiComprobante::
                where('cliente_id',$cliente->id)
                ->where('status', 0)
                ->with('receptor')
                //->with('conceptos')
                ->with(['conceptos' => function ($query){
                    $query->with('mi_clave_prod_serv')
                        ->with('mi_clave_unidad');
                }])
                ->with('impuesto')
                ->with('timbre_fiscal_digital')
                ->with('archivo')
                ->with('mi_forma_pago')
                ->with('mi_metodo_pago')
                ->first();

            for ($i=0; $i < count($pedidoCurso->conceptos); $i++) {
                $esta = false;
                for ($j=0; $j < count($obj->conceptos); $j++) {
                    if(
                        $pedidoCurso->conceptos[$i]->mi_clave_prod_serv->id == $obj->conceptos[$j]->ClaveProdServ &&
                        $pedidoCurso->conceptos[$i]->mi_clave_unidad->id == $obj->conceptos[$j]->ClaveUnidad &&
                        $pedidoCurso->conceptos[$i]->Descripcion == $obj->conceptos[$j]->Descripcion
                        //$pedidoCurso->conceptos[$i]->ValorUnitario == $obj->conceptos[$j]->ValorUnitario &&
                        //$pedidoCurso->conceptos[$i]->Descuento == $obj->conceptos[$j]->Descuento
                    ){
                        $esta = true;
                    }
                }
                if(!$esta){
                    //eliminar concepto
                    $pedidoCurso->conceptos[$i]->delete();
                }
            }

            //recalcular los importes
            $pedidoCurso = CfdiComprobante::
                where('cliente_id',$cliente->id)
                ->where('status', 0)
                ->with('receptor')
                //->with('conceptos')
                ->with(['conceptos' => function ($query){
                    $query->with('mi_clave_prod_serv')
                        ->with('mi_clave_unidad');
                }])
                ->with('impuesto')
                ->with('timbre_fiscal_digital')
                ->with('archivo')
                ->with('mi_forma_pago')
                ->with('mi_metodo_pago')
                ->first();

            for ($j=0; $j < count($pedidoCurso->conceptos); $j++) { 

                $Importe = number_format($pedidoCurso->conceptos[$j]->ValorUnitario * $pedidoCurso->conceptos[$j]->Cantidad, 2, '.', '');

                //Neta
                if($pedidoCurso->Tipo == 1){
                    $Base = $Importe - $pedidoCurso->conceptos[$j]->Descuento;
                    $Importe = ($Base / 1.16);
                    $Importe = number_format($Importe, 2, '.', '');
                }

                //modificar importe
                //$pedidoCurso->conceptos[$j]->Importe = number_format($obj->conceptos[$i]->Cantidad * $obj->conceptos[$i]->ValorUnitario, 2, '.', '');
                $pedidoCurso->conceptos[$j]->Importe = number_format($Importe, 2, '.', '');
                $pedidoCurso->conceptos[$j]->save();

            }

            $message = $this->detallesConceptosFactura($cliente);

            $respC = $this->_messageText($cliente->bot_id,$cliente->telefono,$message);

            if ($respC['status'] == 200) {

                $this->storeMsgChat($cliente->bot_id,$cliente->id,null,$message,0); //bot

                return 1;

            }else{

                return 0;

            }
            
        }else{

            $this->messageTextToCliente($cliente,$respB['error']);
            return 0;

        } 

    }

    public function detallesConceptosFactura($cliente){

        $empresa = CfdiEmpresa::with('producto')
            ->with('mi_regimen_fiscal')
            ->where('bot_cliente_id', $cliente->id)
            ->first();

        $pedidoCurso = CfdiComprobante::
            where('cliente_id',$cliente->id)
            ->where('status', 0)
            ->with('receptor')
            //->with('conceptos')
            ->with(['conceptos' => function ($query){
                $query->with('mi_clave_prod_serv')
                    ->with('mi_clave_unidad');
            }])
            ->with('impuesto')
            ->with('timbre_fiscal_digital')
            ->with('archivo')
            ->with('mi_forma_pago')
            ->with('mi_metodo_pago')
            ->first();

        $salto = '
';

        $message = '';

        if(
            $pedidoCurso && (  
                count($pedidoCurso->conceptos)==0
            )
        ){

            $flag_primero = 1;

            $message = 'Por favor, proporciona el primer concepto para tu factura en el siguiente formato:';

            $this->messageTextToCliente($cliente,$message);

$message = '';


if ($empresa->flag_producto != 1) {
    $message .= 'Clave de Prod o Serv:';
}

$message .= $salto.'Cantidad:';

if ($empresa->flag_producto != 1) {
    $message .= $salto.'Clave de Unidad:';
}

$message .= $salto.'Descripción:
Valor Unitario:';

if ($empresa->flag_descuento == 1) {
    $message .= $salto.'Descuento:';
}

if ($flag_primero == 1 && $empresa->flag_objetoImp == 1 && $empresa->flag_retencion == 1) {
    $message .= $salto.'Tasa de retención de IVA:'.
                $salto.'Tasa de retención de ISR:';
}


        }else{

            $SubTotal = 0;
            $Descuento = 0;
            $Total = 0;
            $TotalImpuestosTrasladados = 0;
            $TotalImpuestosRetenidos = 0;

            $conceptos = '*Conceptos:*'.$salto.$salto;
            for ($i=0; $i < count($pedidoCurso->conceptos); $i++) {

                if($empresa->flag_objetoImp == 1){
                    $Base = $pedidoCurso->conceptos[$i]->Importe - $pedidoCurso->conceptos[$i]->Descuento;
                    $TotalImpuestosTrasladados = $TotalImpuestosTrasladados + ($Base * 0.16);
                    $TotalImpuestosTrasladados = number_format($TotalImpuestosTrasladados, 2, '.', '');

                    if($empresa->flag_retencion == 1){

                        $retencionIva = $Base * ($pedidoCurso->TasaIva/100);
                        $retencionIva = number_format(($retencionIva), 2, '.', '');

                        $retencionIsr = $Base * ($pedidoCurso->TasaIsr/100);
                        $retencionIsr = number_format(($retencionIsr), 2, '.', '');

                        $TotalImpuestosRetenidos = $TotalImpuestosRetenidos + $retencionIva + $retencionIsr;

                        $TotalImpuestosRetenidos = number_format($TotalImpuestosRetenidos, 2, '.', '');
                    }
                }

                $conceptos = $conceptos
                .'*Clave de Prod o Serv:* '.$pedidoCurso->conceptos[$i]->mi_clave_prod_serv->id.$salto
                .'*Cantidad:* '.$pedidoCurso->conceptos[$i]->Cantidad.$salto
                .'*Clave de Unidad:* '.$pedidoCurso->conceptos[$i]->mi_clave_unidad->id.$salto
                .'*Descripción:* '.$pedidoCurso->conceptos[$i]->Descripcion.$salto
                .'*Valor Unitario:* '.$pedidoCurso->conceptos[$i]->ValorUnitario.$salto;

                if ($empresa->flag_descuento == 1) {
                    $conceptos .= '*Descuento:* '.$pedidoCurso->conceptos[$i]->Descuento.$salto;
                }

                $conceptos .= '*Importe:* '.$pedidoCurso->conceptos[$i]->Importe.$salto
                .$salto;

                $SubTotal = $SubTotal + ($pedidoCurso->conceptos[$i]->Importe);
                $Descuento = $Descuento + ($pedidoCurso->conceptos[$i]->Descuento);
            }

            $SubTotal = number_format($SubTotal, 2, '.', '');
            $Descuento = number_format($Descuento, 2, '.', '');

            $Total = $SubTotal - $Descuento + $TotalImpuestosTrasladados - $TotalImpuestosRetenidos;

            $Total = number_format($Total, 2, '.', '');

            $pedidoCurso->SubTotal = $SubTotal;
            $pedidoCurso->Descuento = $Descuento;
            $pedidoCurso->Total = $Total;
            $pedidoCurso->save();

            $Tipo = '';
            if($pedidoCurso->Tipo == 1){
                $Tipo = 'Neta';
            }else if($pedidoCurso->Tipo == 2){
                $Tipo = 'Más IVA';
            }

            $message = $conceptos.
            'Subtotal *'.$SubTotal.'* 💲'.$salto;
            
            if ($empresa->flag_descuento == 1) {
                $message .= 'Descuento *'.$Descuento.'* 💲'.$salto;
            }

            if ($empresa->flag_objetoImp == 1) {
                $message .= 'Impuestos Traslados *'.$TotalImpuestosTrasladados.'* 💲'.$salto;
            }

            if ($empresa->flag_objetoImp == 1 && $empresa->flag_retencion == 1) {
                $message .= 'Impuestos Retenidos *'.$TotalImpuestosRetenidos.'* 💲'.$salto;
            }

            $message .= 'Total *'.$Total.'* 💰'.$salto.$salto.
            '*Escribe la Opción:*'.$salto.$salto.
            'Cancelar factura'.$salto.
            'Confirmar factura'.$salto.$salto.
            'Ó'.$salto.$salto.
            'Envíame más conceptos para agregarlos a tu factura aquí la forma 👇🏻:';

            $this->messageTextToCliente($cliente,$message);

            $message = 'Agrega este otro concepto';

            if ($empresa->flag_producto != 1) {
                $message .= $salto.'Clave de Prod o Serv:';
            }

            $message .= $salto.'Cantidad:';

            if ($empresa->flag_producto != 1) {
                $message .= $salto.'Clave de Unidad:';
            }

            $message .= $salto.'Descripción:'.
                    $salto.'Valor Unitario:';

            if ($empresa->flag_descuento == 1) {
                $message .= $salto.'Descuento:';
            }
        }

        return $message;
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

    public function pdfToImagen($pdfUrl,$carpeta){

        set_time_limit(500);

        // Obtener el contenido del PDF desde la URL
        $pdfContent = Http::get($pdfUrl)->body();

        // Crear una instancia de Imagick
        $imagick = new \Imagick();

        // Leer el contenido del PDF en Imagick
        $imagick->readImageBlob($pdfContent);

        // Seleccionar la primera página del PDF
        $imagick->setIteratorIndex(0);

        // Establecer la calidad de compresión (valores más altos significan mejor calidad)
        $imagick->setImageCompressionQuality(100);

        // Convertir la página del PDF en una imagen
        $imagick->setImageFormat('png');
        $imageBlob = $imagick->getImagesBlob();

        // Ruta donde se guardará la imagen en la carpeta "public"
        $destinationPath = public_path('images/generated/'.$carpeta.'/');
        if (!File::isDirectory($destinationPath)) {
            File::makeDirectory($destinationPath, 0777, true);
        }

        // Nombre de archivo único para evitar colisiones
        $filename = uniqid('image_', true) . '.png';
        $filePath = $destinationPath . $filename;

        // Guardar la imagen en una ubicación
        file_put_contents($filePath, $imageBlob);

        // Obtener la URL de la imagen
        $imageUrl = asset('images/generated/'.$carpeta.'/' . $filename);

        return $imageUrl;

    }

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
            return null;
        }
    }

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
            //return response()->json(['error'=>'Factura no encontrada.'],404);
            return 'Factura no encontrada.';
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
        // $datos['PAC']['usuario'] = 'DEMO700101XXX';
        // $datos['PAC']['pass'] = 'DEMO700101XXX';
        // $datos['PAC']['produccion'] = 'NO';

        $datos['PAC']['usuario'] = 'AUMA9101171B4';
        $datos['PAC']['pass'] = 'AUMA9101171B41234';
        $datos['PAC']['produccion'] = 'SI';

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
            $datos['InformacionGlobal']['Periodicidad'] = '02'; //Mensual
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
            
            if (preg_match('/[^\w\s]/', $factura->conceptos[$i]->Descripcion)) {
                $datos['conceptos'][$i]['descripcion'] = utf8_encode($factura->conceptos[$i]->Descripcion);
            } else {
                $datos['conceptos'][$i]['descripcion'] = $factura->conceptos[$i]->Descripcion;
            }

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

        // echo "<pre>";
        // print_r($datos);
        // echo "</pre>";

        //echo "<pre>"; echo arr2cs($datos); echo "</pre>".die();
        // Se ejecuta el SDK
        //$res = mf_genera_cfdi($datos);
        $res = mf_genera_cfdi4($datos);

        file_put_contents('webhook_log_cfdi_timbrado.txt', print_r($res, true), FILE_APPEND);

        ///////////    MOSTRAR RESULTADOS DEL ARRAY $res   ///////////

        //dd($res);

        // echo "<h1>Respuesta Generar XML y Timbrado</h1>";
        // foreach ($res AS $variable => $valor) {
        //     $valor = htmlentities($valor);
        //     $valor = str_replace('&lt;br/&gt;', '<br/>', $valor);
        //     echo "<b>[$variable]=</b>$valor<hr>";
        // }

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
            // echo "<h1>Respuesta Generar XML y Timbrado</h1>";
            // foreach ($res AS $variable => $valor) {
            //     $valor = htmlentities($valor);
            //     $valor = str_replace('&lt;br/&gt;', '<br/>', $valor);
            //     echo "<b>[$variable]=</b>$valor<hr>";
            // }

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
            // return response()->json([
            //     'emisor' => $emisor,
            //     'factura'=>$factura,
            //     //'datos'=>$datos,
            // ], 200);
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

    public function emailFactura($factura_id)
    {

        $factura = CfdiComprobante::select('id','cliente_id')
            ->with(['cliente_bot' => function ($query){
                $query->select('id','logo');
            }])
            ->with(['receptor' => function ($query){
                $query->select('id','comprobante_id','Rfc','Nombre','Email');
            }])
            ->with(['archivo' => function ($query){
                $query->select('id','comprobante_id','xml_archivo','pdf');
            }])
            ->find($factura_id);
        
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

        \Mail::to($factura->receptor->Email)->send(new \App\Mail\NuevaFacturaEmail($details,$attachment1,$attachment2));


        return 1;

    }

    public function flowListaFacturas($cliente)
    {

        //quitar el flujo
        DB::table('bot_clientes')
            ->where('id', $cliente->id)
            ->update([
                'flow_id' => null,
            ]);

        $this->cancelarFlowComprobante($cliente,0);

        $user_token=User::find(56);
        $token = JWTAuth::fromUser($user_token);

        $claveAdicional = config('app.lada_d');

        $cadenaEncriptada = Crypt::encrypt($cliente->id, $claveAdicional);

        $link = 'https://social.internow.com.mx/#/cfdi-facturas-bot/'.$cadenaEncriptada.'/'.$token;

        $short_link = $this->shortenURL($link);

        $message = 'Ingresa en el siguiente enlace para ver tus facturas:

{{short_link}}';

        $message = str_replace("{{short_link}}", $short_link, $message);

        $this->messageTextToCliente($cliente,$message);

        return 1;

    }

    public function timbrarDesdePanel(Request $request, $empresa_id)
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

        $cliente=BotCliente::find($empresa->bot_cliente_id);
        if (!$cliente)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Cliente no encontrado.'], 404);
        }

        $conceptos = json_decode($request->input('conceptos'));
        if (count($conceptos) == 0) {
            // Devolvemos un código 409 Conflict. 
            return response()->json(['error'=>'Factura sin conceptos.'], 409);
        }

        $pedidoCurso = CfdiComprobante::
            where('cliente_id',$cliente->id)
            ->where('status', 3)
            ->with('receptor')
            //->with('conceptos')
            ->with(['conceptos' => function ($query){
                $query->with('mi_clave_prod_serv')
                    ->with('mi_clave_unidad');
            }])
            ->with('impuesto')
            ->with('timbre_fiscal_digital')
            ->with('archivo')
            ->with('mi_forma_pago')
            ->with('mi_metodo_pago')
            ->first();   

        //elimino cotizacion curso desde el panel en caso de que tenga
        if($pedidoCurso){
            for ($i=0; $i < count($pedidoCurso->conceptos); $i++) { 
                $pedidoCurso->conceptos[$i]->delete();
            }
            $pedidoCurso->receptor->delete();
            $pedidoCurso->delete();
        } 

        //Iniciar proceso de facturacion
        $Folio = (CfdiComprobante::count())+1;

        $Serie = (CfdiComprobante::
            where('cliente_id',$cliente->id)
            ->count())+1;

        //crear un pedido nuevo en curso
        $pedidoCurso=CfdiComprobante::create([
            'bot_id'=>$cliente->bot_id,
            'cliente_id'=>$cliente->id,
            'status'=>3,
            'flag_cancelada'=>null,
            'Serie'=>"S-".$empresa->id."-".$Serie,
            'Folio'=>"F-".$empresa->id."-".$Folio,
            'Fecha'=>date('Y-m-d\TH:i:s', time() - (60*60)),
            'Sello'=>"",
            'FormaPago'=>$request->input('FormaPago'),
            'NoCertificado'=>"",
            'Certificado'=>"",
            'CondicionesDePago'=>"",
            'Subtotal'=>$request->input('Subtotal'),
            'Descuento'=>$request->input('Descuento'),
            'Moneda'=>"MXN",
            'TipoCambio'=>"",
            'Total'=>$request->input('Total'),
            'TipoDeComprobante'=>"I",
            'Exportacion'=>"01",
            'MetodoPago'=>$request->input('MetodoPago'),
            'LugarExpedicion'=>$empresa->CP,
            'Confirmacion'=>"",
            'estado'=>null,
            'function'=>null,
            'TasaIva'=>$request->input('TasaIva'),
            'TasaIsr'=>$request->input('TasaIsr'),
            'Tipo'=>$request->input('Tipo'),
        ]);

        //crear el receptor
        $newObjReceptor=CfdiReceptor::create([
            'comprobante_id'=>$pedidoCurso->id,
            'Rfc'=>$request->input('Rfc'),
            'Nombre'=>$request->input('Nombre'),
            'DomicilioFiscalReceptor'=>$request->input('DomicilioFiscalReceptor'),
            'ResidenciaFiscal'=>null,
            'NumRegIdTrib'=>null,
            'RegimenFiscalReceptor'=>$request->input('RegimenFiscalReceptor'),
            'UsoCFDI'=>$request->input('UsoCFDI'),
            'Email'=>$request->input('Email'),
        ]);

        //crear cliente asociado al emisor
        if($request->input('flag_cliente') == 2){

            if($request->input('cliente_id') != null && $request->input('cliente_id') != ''){

                $clienteExiste = CfdiCliente::
                    where('empresa_id',$empresa_id)
                    ->where('status', 1)
                    ->where('id', '<>', $request->input('cliente_id'))
                    ->where('Rfc', $request->input('Rfc'))
                    ->with('mi_regimen_fiscal')
                    ->with('mi_uso_cfdi')
                    ->first();

                if(!$clienteExiste){
                    $newCliente=CfdiCliente::create([
                        'empresa_id'=>$empresa_id,
                        'status'=>1,
                        'Rfc'=>$request->input('Rfc'),
                        'Nombre'=>$request->input('Nombre'),
                        'DomicilioFiscalReceptor'=>$request->input('DomicilioFiscalReceptor'),
                        'ResidenciaFiscal'=>null,
                        'NumRegIdTrib'=>null,
                        'RegimenFiscalReceptor'=>$request->input('RegimenFiscalReceptor'),
                        'UsoCFDI'=>$request->input('UsoCFDI'),
                        'Email'=>$request->input('Email'),
                    ]);
                }

            }
        }

        //actualizar cliente asociado del emisor
        if($request->input('flag_cliente') == 1){

            if($request->input('cliente_id') != null && $request->input('cliente_id') != ''){

                $clienteReceptor = CfdiCliente::
                    where('empresa_id',$empresa_id)
                    ->where('status', 1)
                    ->where('id', $request->input('cliente_id'))
                    ->with('mi_regimen_fiscal')
                    ->with('mi_uso_cfdi')
                    ->first();

                if($clienteReceptor){

                    $clienteReceptor->Nombre = $request->input('Nombre');
                    $clienteReceptor->DomicilioFiscalReceptor = $request->input('DomicilioFiscalReceptor');
                    $clienteReceptor->RegimenFiscalReceptor = $request->input('RegimenFiscalReceptor');
                    $clienteReceptor->UsoCFDI = $request->input('UsoCFDI');
                    $clienteReceptor->Email = $request->input('Email');
                    $clienteReceptor->save();

                    $clienteExiste = CfdiCliente::
                        where('empresa_id',$empresa_id)
                        ->where('status', 1)
                        ->where('id', '<>', $request->input('cliente_id'))
                        ->where('Rfc', $request->input('Rfc'))
                        ->with('mi_regimen_fiscal')
                        ->with('mi_uso_cfdi')
                        ->first();

                    if(!$clienteExiste){
                        $clienteReceptor->Rfc = $request->input('Rfc');
                        $clienteReceptor->save();
                    }

                    
                }

            }
        }

        //Crear los conceptos
        for ($i=0; $i < count($conceptos); $i++) { 
            //agregar nuevo concepto
            $nuevoConcepto=CfdiConcepto::create([
                'comprobante_id' => $pedidoCurso->id,
                'ClaveProdServ' => $conceptos[$i]->ClaveProdServ,
                'NoIdentificacion' => "",
                'Cantidad' => $conceptos[$i]->Cantidad,
                'ClaveUnidad' => $conceptos[$i]->ClaveUnidad,
                'Unidad' => $conceptos[$i]->Unidad,
                'Descripcion' => $conceptos[$i]->Descripcion,
                'ValorUnitario' => $conceptos[$i]->ValorUnitario,
                'Importe' => $conceptos[$i]->Importe,
                'Descuento' => $conceptos[$i]->Descuento,
                'ObjetoImp' => $conceptos[$i]->ObjetoImp,
                'ObjetoImpRet' => $conceptos[$i]->ObjetoImpRet,
            ]);
        }

        //$resTimbrado = $this->timbrarSanbox($pedidoCurso->id);
        $resTimbrado = $this->timbrar($pedidoCurso->id);

        if($resTimbrado != 1){

            $pedidoCurso = CfdiComprobante::
                where('cliente_id',$cliente->id)
                ->where('status', 3)
                ->with('receptor')
                //->with('conceptos')
                ->with(['conceptos' => function ($query){
                    $query->with('mi_clave_prod_serv')
                        ->with('mi_clave_unidad');
                }])
                ->with('impuesto')
                ->with('timbre_fiscal_digital')
                ->with('archivo')
                ->with('mi_forma_pago')
                ->with('mi_metodo_pago')
                ->first();   

            //elimino cotizacion curso desde el panel en caso de que tenga
            if($pedidoCurso){
                for ($i=0; $i < count($pedidoCurso->conceptos); $i++) { 
                    $pedidoCurso->conceptos[$i]->delete();
                }
                $pedidoCurso->receptor->delete();
                $pedidoCurso->delete();
            }

            $message = $resTimbrado;

            // Devolvemos un código 409 Conflict. 
            return response()->json([
                'error'=>$message
            ], 409);

        }else{

            //Timbrada exitosamente
            $pedidoCurso->status = 1;
            $pedidoCurso->save();

            $count_facturas = $cliente->count_facturas + 1;
            DB::table('bot_clientes')
            ->where('id', $cliente->id)
            ->update([
                'count_facturas' => $count_facturas,
            ]);

            $document = $this->facturaPdf($pedidoCurso->id);

            DB::table('cfdi_archivos')
                ->where('comprobante_id', $pedidoCurso->id)
                ->update([
                    'pdf' => $document,
                ]);

            try {
               $this->emailFactura($pedidoCurso->id); 
            } catch (Exception $e) {
                
            }

            return response()->json([
                'message'=>'Factura timbrada exitosamente.',
                'factura_id'=>$pedidoCurso->id,
            ], 200); 
        }
  
    }

    public function timbrarSanbox($factura_id)
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
            //return response()->json(['error'=>'Factura no encontrada.'],404);
            return 'Factura no encontrada.';
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
            $datos['InformacionGlobal']['Periodicidad'] = '02'; //Mensual
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
            
            if (preg_match('/[^\w\s]/', $factura->conceptos[$i]->Descripcion)) {
                $datos['conceptos'][$i]['descripcion'] = utf8_encode($factura->conceptos[$i]->Descripcion);
            } else {
                $datos['conceptos'][$i]['descripcion'] = $factura->conceptos[$i]->Descripcion;
            }

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

        // echo "<pre>";
        // print_r($datos);
        // echo "</pre>";

        //echo "<pre>"; echo arr2cs($datos); echo "</pre>".die();
        // Se ejecuta el SDK
        //$res = mf_genera_cfdi($datos);
        $res = mf_genera_cfdi4($datos);

        file_put_contents('webhook_log_cfdi_timbrado.txt', print_r($res, true), FILE_APPEND);

        ///////////    MOSTRAR RESULTADOS DEL ARRAY $res   ///////////

        //dd($res);

        // echo "<h1>Respuesta Generar XML y Timbrado</h1>";
        // foreach ($res AS $variable => $valor) {
        //     $valor = htmlentities($valor);
        //     $valor = str_replace('&lt;br/&gt;', '<br/>', $valor);
        //     echo "<b>[$variable]=</b>$valor<hr>";
        // }

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
            // echo "<h1>Respuesta Generar XML y Timbrado</h1>";
            // foreach ($res AS $variable => $valor) {
            //     $valor = htmlentities($valor);
            //     $valor = str_replace('&lt;br/&gt;', '<br/>', $valor);
            //     echo "<b>[$variable]=</b>$valor<hr>";
            // }

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
            // return response()->json([
            //     'emisor' => $emisor,
            //     'factura'=>$factura,
            //     //'datos'=>$datos,
            // ], 200);
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
