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
use App\Models\Sistema;
use App\Models\Bot;
use App\Models\BotConfig;
use App\Models\BotCliente;
use App\Models\BotChat;

use App\Models\BotFlow;
use App\Models\BotFlowStage;
use App\Models\BotStageValidation;

use App\Models\Cotizacion;
use App\Models\CotizacionGasto;

//use Hash;
use DB;
//use Validator;
use Exception;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

use DateTime;
use Carbon\Carbon;

use App\Http\Traits\ApiWhatsAppTrait;
use App\Http\Traits\ApiTextCortexTrait;
use App\Http\Traits\ApiOpenAiTrait;
//use App\Http\Traits\BotFunctionsTrait;

use App\Http\Controllers\FlowFacturaController;

date_default_timezone_set('America/Mexico_City');

class FlowCotizacionController extends Controller
{
    use ApiWhatsAppTrait;
    use ApiTextCortexTrait;
    use ApiOpenAiTrait;
    //use BotFunctionsTrait;

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

    public function flowCotizacion($cliente)
    {

        if($cliente->hab_cotizaciones != 1){
            $this->flowNoAplicable($cliente);
            return 1;
        }

        //Indicar que el cliente inicio el primer estado de un flujo
        DB::table('bot_clientes')
            ->where('id', $cliente->id)
            ->update([
                'flow_id' => 3,
            ]);

        $bot_config = BotConfig::
            select('id','palabra_clave','flow_id','prompt')
            ->where('flow_id', 3)
            ->get();

        $prompt = $bot_config[0]->prompt;
        
        $text_mensajes = '';
        for ($i=0; $i < count($cliente->mensajes); $i++) { 
            if($i == 0){
                $text_mensajes = $cliente->mensajes[$i];
            }else{
                $text_mensajes = $text_mensajes.', '.$cliente->mensajes[$i];
            }
        }
        
        $prompt = str_replace("{{mensaje}}", $text_mensajes, $prompt);

        $pedidoCurso = Cotizacion::
            where('cliente_id',$cliente->id)
            ->where('status', 0)
            ->with('gastos')
            ->first();

        if(!$pedidoCurso){
            $prompt = str_replace("{{detalles}}", "{}", $prompt);
        }else{

            $descripciones = [];
            for ($i=0; $i < count($pedidoCurso->gastos); $i++) { 
                $resul = (object) [
                    'descripcion' => $pedidoCurso->gastos[$i]->descripcion,
                    'cantidad' => number_format(($pedidoCurso->gastos[$i]->cantidad), 2, '.', ''),
                    'valorUnitario' => number_format(($pedidoCurso->gastos[$i]->valorUnitario), 2, '.', ''),
                ];
                array_push($descripciones,$resul);
            }

            $cotizacion = [
                "tipo" => $pedidoCurso->tipo,
                "nombre" => $pedidoCurso->cliente,
                "telefono" => $pedidoCurso->telefono,
                "email" => $pedidoCurso->email,
                "envio" => $pedidoCurso->envio,
                "descripciones" => $descripciones,
            ];

            $cotizacionString = json_encode($cotizacion);
            
            $prompt = str_replace("{{detalles}}", $cotizacionString, $prompt);
        } 

        $respB = $this->_davinciRespuestaPrompt($prompt);
        if ($respB['status'] == 200) {

            $cadena = $respB['text'];

            $log = [];
            array_push($log,$text_mensajes);
            array_push($log,$cadena);

            file_put_contents('webhook_log_cotizaciones.txt', print_r($log, true), FILE_APPEND);

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
                return 0; // Retornar cadena vacía si no se encuentran los caracteres
            }

            $obj = json_decode($cadena);

            $cadenaEnMayusculas = strtoupper($text_mensajes);
            $subcadena = 'CANCELAR';

            // Buscar la subcadena en la cadena original (sensible a mayúsculas y minúsculas)
            $posicion = strpos($cadenaEnMayusculas, $subcadena);

            if ($posicion !== false) {
                $obj->nueva = 0;
                $obj->confirmar = 0;
                $obj->cancelar = 1;
                $obj->ver_cotizaciones = 0;
            }

            $subcadena = 'CONFIRMAR';

            // Buscar la subcadena en la cadena original (sensible a mayúsculas y minúsculas)
            $posicion = strpos($cadenaEnMayusculas, $subcadena);

            if ($posicion !== false) {
                $obj->nueva = 0;
                $obj->confirmar = 1;
                $obj->cancelar = 0;
                $obj->ver_cotizaciones = 0;
            }

            //fusionar los items duplicados
            $array_descripciones = [];
            for ($i=0; $i < count($obj->descripciones); $i++) {

                $esta = false;
                //$obj->descripciones[$i]->descripcion = strtoupper($obj->descripciones[$i]->descripcion);

                for ($j=0; $j < count($array_descripciones); $j++) { 
                    if(
                        $obj->descripciones[$i]->descripcion == $array_descripciones[$j]->descripcion
                    ){
                        $array_descripciones[$j]->cantidad = $array_descripciones[$j]->cantidad + $obj->descripciones[$i]->cantidad;
                        $array_descripciones[$j]->valorUnitario = $array_descripciones[$j]->valorUnitario;
                        $esta = true;
                    }
                }
                if(!$esta){
                    array_push($array_descripciones,$obj->descripciones[$i]);
                }

            }
            $obj->descripciones = $array_descripciones;

            $pedidoCurso = Cotizacion::
                where('cliente_id',$cliente->id)
                ->where('status', 0)
                ->with('gastos')
                ->first();

            /*Si no hay informacion referente la cotizacion,
            responder con el prompt general*/
            if(
                $obj->nombre == "" &&
                $obj->telefono == "" &&
                $obj->email == "" &&
                $obj->tipo == "" &&
                //$obj->envio == "" &&
                (
                    count($obj->descripciones) == 0 ||
                    (
                        count($obj->descripciones) == 1 &&
                        $obj->descripciones[0]->descripcion == ""
                    ) 
                 ) &&

                $obj->nueva == 0 &&
                $obj->confirmar == 0 &&
                $obj->cancelar == 0 &&
                $obj->ver_cotizaciones == 0 
            ){

                // //elimino cotizacion curso en caso de que tenga
                // if($pedidoCurso){
                //     for ($i=0; $i < count($pedidoCurso->gastos); $i++) { 
                //         $pedidoCurso->gastos[$i]->delete();
                //     }
                //     $pedidoCurso->delete();
                // }

                // //respondo con flujo no aplicable
                // $this->flowNoAplicable($cliente);
                // return 1;

            }

            /*Si quiere iniciar el proceso*/
            if(
                $obj->nombre == "" &&
                $obj->telefono == "" &&
                $obj->email == "" &&
                $obj->tipo == "" &&
                //$obj->envio == "" &&
                (
                    count($obj->descripciones) == 0 ||
                    (
                        count($obj->descripciones) == 1 &&
                        $obj->descripciones[0]->descripcion == ""
                    ) 
                 ) &&

                $obj->nueva == 1
            ){

                //elimino cotizacion curso en caso de que tenga
                if($pedidoCurso){
                    for ($i=0; $i < count($pedidoCurso->gastos); $i++) { 
                        $pedidoCurso->gastos[$i]->delete();
                    }
                    $pedidoCurso->delete();
                }

                $orden_count = Cotizacion::
                    where('cliente_id',$cliente->id)
                    ->count();

                //crear un pedido nuevo en curso
                $nuevoObj=Cotizacion::create([
                    'bot_id'=>$cliente->bot_id,
                    'cliente_id'=>$cliente->id,
                    'status'=>0,
                    'subtotal'=>0,
                    'iva'=>0,
                    'envio'=>0,
                    'total'=>0,
                    'orden'=>$orden_count+1,
                    'cliente'=>"",
                    'telefono'=>"",
                    'email'=>"",
                    'tipo'=>"",
                ]);

                $message = '¡Hola! 👋 Estoy aquí para ayudarte a crear una cotización. Por favor, proporciona los siguientes datos en el *formato de muestra*  👁️';

                $this->messageTextToCliente($cliente,$message);

$message = 'Tipo Más IVA o Sin IVA:
Nombre:
Teléfono:
Email:
Costo de envío (en caso tener):
Producto o servicio:
Cantidad: 
Valor Unitario:';

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

                //elimino cotizacion curso en caso de que tenga
                if($pedidoCurso){
                    for ($i=0; $i < count($pedidoCurso->gastos); $i++) { 
                        $pedidoCurso->gastos[$i]->delete();
                    }
                    $pedidoCurso->delete();
                }


                $message = 'Tu cotización ha sido cancelada correctamente';

                $this->messageTextToCliente($cliente,$message);
                
                return 1;

            }

            /*Si quiere confirmar la cotizacion*/
            if(
                $obj->confirmar == 1
            ){

                if(
                    $pedidoCurso &&
                    $pedidoCurso->cliente != "" && 
                    $pedidoCurso->telefono != "" && 
                    $pedidoCurso->email != "" &&
                    $pedidoCurso->tipo != "" && 
                    count($pedidoCurso->gastos)>0
                ){

                    //quitar el flujo
                    DB::table('bot_clientes')
                        ->where('id', $cliente->id)
                        ->update([
                            'flow_id' => null,
                        ]);

                    $salto = '
';

                    $subtotal = 0;
                    $iva = 0;
                    $envio = $pedidoCurso->envio;
                    $total = 0;
                    $descripciones = '*Descripciones:*'.$salto.$salto;
                    for ($i=0; $i < count($pedidoCurso->gastos); $i++) {

                        $descripciones = $descripciones
                        .'*Desc:* '.$pedidoCurso->gastos[$i]->descripcion.$salto
                        .'*Cant:* '.$pedidoCurso->gastos[$i]->cantidad.$salto
                        .'*Valor:* '.$pedidoCurso->gastos[$i]->valorUnitario.$salto
                        .'*Importe:* '.$pedidoCurso->gastos[$i]->importe.$salto.$salto;

                        $subtotal = $subtotal + ($pedidoCurso->gastos[$i]->importe);
                    }

                    //Con IVA
                    if($pedidoCurso->tipo == 2){
                        $iva = ($subtotal * 0.16);
                        $iva = number_format($iva, 2, '.', '');
                    }
                    //Sin IVA
                    else{
                        $iva = 0;
                    }

                    $total = $subtotal + $iva + $envio;

                    $pedidoCurso->subtotal = $subtotal;
                    $pedidoCurso->iva = $iva;
                    $pedidoCurso->envio = $envio;
                    $pedidoCurso->total = $total;

                    //pasar a confirmado
                    $pedidoCurso->status = 1;
                    $pedidoCurso->save();

                    $envio_text = '**';
                    if($envio > 0){
                        $envio_text = $envio;
                    }

                    $tipo = '';
                    if($pedidoCurso->tipo == 1){
                        $tipo = 'Sin IVA';
                    }else if($pedidoCurso->tipo == 2){
                        $tipo = 'Más IVA';
                    }

                    $message = '✅ *Cotización confirmada:*'.$salto.$salto.
                    '*Tipo:* '.$tipo.$salto.
                    '*Nombre:* '.$pedidoCurso->cliente.$salto.
                    '*Teléfono:* '.$pedidoCurso->telefono.$salto.
                    '*Email:* '.$pedidoCurso->email.$salto.
                    $salto.$descripciones.
                    'Subtotal *'.$subtotal.'* 💲'.$salto;

                    if ($pedidoCurso->tipo == 2) {
                        $message .= 'Iva *'.$iva.'* 💲'.$salto;
                    }

                    $message .= 'Envío *'.$envio_text.'* 🚚'.$salto.
                    'Total *'.$total.'* 💰';
                
                    //mensaje de texto con el detalle
                    $this->messageTextToCliente($cliente,$message);

                    $message = 'Estamos generando tu cotización. Por favor, espera un momento...';
                    $this->messageTextToCliente($cliente,$message);

                    $document = $this->cotizacionPdf($pedidoCurso->id);

                    $pedidoCurso->pdf = $document;
                    $pedidoCurso->save();

                    $this->_messageDocument($cliente->bot_id,$cliente->telefono,$document,'cotizacion');

                    $this->storeMsgChat($cliente->bot_id,$cliente->id,null,$document,0); //bot

                    $image = $this->pdfToImagen($document,'cotizaciones');

                    $pedidoCurso->imagen = $image;
                    $pedidoCurso->save();

                    $this->_messageImage($cliente->bot_id,$cliente->telefono,$image);

                    $this->storeMsgChat($cliente->bot_id,$cliente->id,null,$image,0); //bot

                    return 1;
                                        
                }else if(
                    $pedidoCurso && (
                    $pedidoCurso->cliente == "" || 
                    $pedidoCurso->telefono == "" || 
                    $pedidoCurso->email == "" || 
                    $pedidoCurso->tipo == "" ||
                    count($pedidoCurso->gastos)==0
                    )
                ){

                    $salto = '
';

                    $subtotal = 0;
                    $iva = 0;
                    $envio = $pedidoCurso->envio;
                    $total = 0;
                    $descripciones = '*Descripciones:*'.$salto.$salto;
                    for ($i=0; $i < count($pedidoCurso->gastos); $i++) {

                        $descripciones = $descripciones
                        .'*Desc:* '.$pedidoCurso->gastos[$i]->descripcion.$salto
                        .'*Cant:* '.$pedidoCurso->gastos[$i]->cantidad.$salto
                        .'*Valor:* '.$pedidoCurso->gastos[$i]->valorUnitario.$salto
                        .'*Importe:* '.$pedidoCurso->gastos[$i]->importe.$salto.$salto;

                        $subtotal = $subtotal + ($pedidoCurso->gastos[$i]->importe);
                    }

                    //Con IVA
                    if($pedidoCurso->tipo == 2){
                        $iva = ($subtotal * 0.16);
                        $iva = number_format($iva, 2, '.', '');
                    }
                    //Sin IVA
                    else{
                        $iva = 0;
                    }

                    $total = $subtotal + $iva + $envio;

                    $pedidoCurso->subtotal = $subtotal;
                    $pedidoCurso->iva = $iva;
                    $pedidoCurso->envio = $envio;
                    $pedidoCurso->total = $total;

                    //pasar a confirmado
                    $pedidoCurso->status = 1;
                    $pedidoCurso->save();

                    $envio_text = '**';
                    if($envio > 0){
                        $envio_text = $envio;
                    }

                    $tipo = '';
                    if($pedidoCurso->tipo == 1){
                        $tipo = '*Tipo:* Sin IVA';
                    }else if($pedidoCurso->tipo == 2){
                        $tipo = '*Tipo:* Más IVA';
                    }else{
                        $tipo = '*Más IVA/Sin IVA:*';
                    }

                    $message = '*Detalles:*'.$salto.$salto.
                    $tipo.$salto.
                    '*Nombre:* '.$pedidoCurso->cliente.$salto.
                    '*Teléfono:* '.$pedidoCurso->telefono.$salto.
                    '*Email:* '.$pedidoCurso->email.$salto.
                    $salto.$descripciones.
                    'Subtotal *'.$subtotal.'* 💲'.$salto;

                    if ($pedidoCurso->tipo == 2) {
                        $message .= 'Iva *'.$iva.'* 💲'.$salto;
                    }

                    $message .= 'Envío *'.$envio_text.'* 🚚'.$salto.
                    'Total *'.$total.'* 💰'.$salto.$salto.
                    '*Para poder confirmar debes completar los datos faltantes de tu cotización.*';
                
                    //mensaje de texto con el detalle
                    $this->messageTextToCliente($cliente,$message);

                    return 1;
                                        
                }else{

                    //elimino cotizacion curso en caso de que tenga
                    if($pedidoCurso){
                        for ($i=0; $i < count($pedidoCurso->gastos); $i++) { 
                            $pedidoCurso->gastos[$i]->delete();
                        }
                        $pedidoCurso->delete();
                    }

                    $orden_count = Cotizacion::
                    where('cliente_id',$cliente->id)
                    ->count();

                    //crear un pedido nuevo en curso
                    $nuevoObj=Cotizacion::create([
                        'bot_id'=>$cliente->bot_id,
                        'cliente_id'=>$cliente->id,
                        'status'=>0,
                        'subtotal'=>0,
                        'iva'=>0,
                        'envio'=>0,
                        'total'=>0,
                        'orden'=>$orden_count+1,
                        'cliente'=>"",
                        'telefono'=>"",
                        'email'=>"",
                        'tipo'=>"",
                    ]);

                    $message = 'Por favor, proporciona los datos para tu cotización en el siguiente formato:';

                    $this->messageTextToCliente($cliente,$message);

$message = 'Tipo Más IVA o Sin IVA:
Nombre:
Teléfono:
Email:
Costo de envío (en caso tener):
Producto o servicio:
Cantidad: 
Valor Unitario:';

                    $this->messageTextToCliente($cliente,$message);
                
                    return 1;
                }  

            }

            /*Si quiere ver el listado de cotizaciones*/
            if(
                $obj->ver_cotizaciones == 1
            ){

                $this->flowListaCotizaciones($cliente);
                
                return 1;

            }

            //si no tiene cotizacion en curso, creo una para las validaciones
            if(!$pedidoCurso){

                $orden_count = Cotizacion::
                    where('cliente_id',$cliente->id)
                    ->count();

                //crear un pedido nuevo en curso
                $nuevoObj=Cotizacion::create([
                    'bot_id'=>$cliente->bot_id,
                    'cliente_id'=>$cliente->id,
                    'status'=>0,
                    'subtotal'=>0,
                    'iva'=>0,
                    'envio'=>0,
                    'total'=>0,
                    'orden'=>$orden_count+1,
                    'cliente'=>"",
                    'telefono'=>"",
                    'email'=>"",
                    'tipo'=>"",
                ]);

                $pedidoCurso = Cotizacion::
                    where('cliente_id',$cliente->id)
                    ->where('status', 0)
                    ->with('gastos')
                    ->first();
            }

            if(
                $obj->nombre != ""
            ){

                $nombre = $obj->nombre;

                DB::table('cotizaciones')
                    ->where('cliente_id', $cliente->id)
                    ->where('status', 0)
                    ->update([
                        'cliente' => $nombre,
                    ]);

            }


            if(
                $obj->telefono != ""
            ){

                // Eliminar espacios en blanco y guiones si los hay
                $telefono = str_replace([' ', '-'], '', $obj->telefono);

                // Verificar que el número de teléfono solo contenga dígitos y tenga una longitud de 10
                if (!ctype_digit($telefono) || strlen($telefono) != 10) {
                    // El número de teléfono es inválido
                    $message = 'Por favor, verifica el número de teléfono. Debe contener 10 dígitos. Por favor, inténtalo nuevamente. 📞';
                    $this->messageTextToCliente($cliente,$message);
                    //return 1;
                }else{
                    DB::table('cotizaciones')
                    ->where('cliente_id', $cliente->id)
                    ->where('status', 0)
                    ->update([
                        'telefono' => $telefono,
                    ]);
                }

            }

            if(
                $obj->email != ""
            ){

                $email = $obj->email;

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    // El email es inválido
                    $message = 'Por favor, verifica el email. Por favor, inténtalo nuevamente. 📧';
                    $this->messageTextToCliente($cliente,$message);
                    //return 1;
                }else{
                    DB::table('cotizaciones')
                    ->where('cliente_id', $cliente->id)
                    ->where('status', 0)
                    ->update([
                        'email' => $email,
                    ]);
                }

            }

            if(
                $obj->tipo != ""

            ){

                $tipo = $obj->tipo;

                DB::table('cotizaciones')
                    ->where('cliente_id', $cliente->id)
                    ->where('status', 0)
                    ->update([
                        'tipo' => $tipo,
                    ]);

            }

            if(
                true

            ){

                $envio = $obj->envio;

                if($envio == ""){
                    $envio = 0;
                }

                DB::table('cotizaciones')
                    ->where('cliente_id', $cliente->id)
                    ->where('status', 0)
                    ->update([
                        'envio' => $envio,
                    ]);

            }
            

            //validar formato de entrada
            for ($i=0; $i < count($obj->descripciones); $i++) { 
                if($obj->descripciones[$i]->descripcion == ""
                ){

                    $message = 'Por favor, proporciona la descripción de tu cotización en el siguiente formato:';

                    $this->messageTextToCliente($cliente,$message);

$message = 'Producto o servicio:
Cantidad: 
Valor Unitario:';


                    $this->messageTextToCliente($cliente,$message);
                    
                    return 1;

                }

                if(
                    $obj->descripciones[$i]->cantidad == "" ||
                    $obj->descripciones[$i]->cantidad < 0
                ){

                    $message = 'La propiedad Cantidad debe ser mayor o igual a cero.';

                    $this->messageTextToCliente($cliente,$message);
                    
                    return 1;

                }

                if(
                    $obj->descripciones[$i]->valorUnitario == "" ||
                    $obj->descripciones[$i]->valorUnitario < 0
                ){

                    $message = 'La propiedad Valor Unitario debe ser mayor o igual a cero.';

                    $this->messageTextToCliente($cliente,$message);
                    
                    return 1;

                }
            }

            //validar insercion o edicion
            for ($i=0; $i < count($obj->descripciones); $i++) {
                $esta = false;
                for ($j=0; $j < count($pedidoCurso->gastos); $j++) { 

                    if(
                        $obj->descripciones[$i]->descripcion == $pedidoCurso->gastos[$j]->descripcion
                    ){
                        $esta = true;

                        $importe = number_format($obj->descripciones[$i]->valorUnitario * $obj->descripciones[$i]->cantidad, 2, '.', '');

                        //modificar importe
                        $pedidoCurso->gastos[$j]->cantidad = number_format($obj->descripciones[$i]->cantidad, 2, '.', '');
                        $pedidoCurso->gastos[$j]->valorUnitario = number_format($obj->descripciones[$i]->valorUnitario, 2, '.', '');
                        $pedidoCurso->gastos[$j]->importe = number_format($importe, 2, '.', '');
                        $pedidoCurso->gastos[$j]->save();

                        if($obj->descripciones[$i]->cantidad == 0){
                            $pedidoCurso->gastos[$j]->delete();
                        }

                    }
                }
                if(!$esta){

                    if(
                        $obj->descripciones[$i]->cantidad < 0
                    ){

                        $message = 'La propiedad Cantidad debe ser mayor o igual a cero.';

                        $this->messageTextToCliente($cliente,$message);
                        
                        return 1;

                    }

                    if(
                        $obj->descripciones[$i]->valorUnitario < 0
                    ){

                        $message = 'La propiedad Valor Unitario debe ser mayor o igual a cero.';

                        $this->messageTextToCliente($cliente,$message);
                        
                        return 1;

                    }

                    $importe = number_format($obj->descripciones[$i]->valorUnitario * $obj->descripciones[$i]->cantidad, 2, '.', '');

                    //agregar nuevo gasto
                    $nuevoGasto=CotizacionGasto::create([
                        'cotizacion_id' => $pedidoCurso->id,
                        'descripcion' => $obj->descripciones[$i]->descripcion,
                        'cantidad' => number_format($obj->descripciones[$i]->cantidad, 2, '.', ''),
                        'valorUnitario' => number_format($obj->descripciones[$i]->valorUnitario, 2, '.', ''),
                        'importe' => number_format($importe, 2, '.', ''),
                    ]);
                }

            }

            //validar eliminacion
            $pedidoCurso = Cotizacion::
                where('cliente_id',$cliente->id)
                ->where('status', 0)
                ->with('gastos')
                ->first();
            for ($i=0; $i < count($pedidoCurso->gastos); $i++) {
                $esta = false;
                for ($j=0; $j < count($obj->descripciones); $j++) {
                    if(
                        $pedidoCurso->gastos[$i]->descripcion == $obj->descripciones[$j]->descripcion
                    ){
                        $esta = true;
                    }
                }
                if(!$esta){
                    //eliminar gasto
                    $pedidoCurso->gastos[$i]->delete();
                }
            }

            $pedidoCurso = Cotizacion::
                where('cliente_id',$cliente->id)
                ->where('status', 0)
                ->with('gastos')
                ->first();

            $salto = '
';

            $message = '';

            
            $subtotal = 0;
            $iva = 0;
            $envio = $pedidoCurso->envio;
            $total = 0;
            $descripciones = '*Descripciones:*'.$salto.$salto;
            for ($i=0; $i < count($pedidoCurso->gastos); $i++) {

                $descripciones = $descripciones
                .'*Desc:* '.$pedidoCurso->gastos[$i]->descripcion.$salto
                .'*Cant:* '.$pedidoCurso->gastos[$i]->cantidad.$salto
                .'*Valor:* '.$pedidoCurso->gastos[$i]->valorUnitario.$salto
                .'*Importe:* '.$pedidoCurso->gastos[$i]->importe.$salto.$salto;

                $subtotal = $subtotal + ($pedidoCurso->gastos[$i]->importe);
            }

            //Con IVA
            if($pedidoCurso->tipo == 2){
                $iva = ($subtotal * 0.16);
                $iva = number_format($iva, 2, '.', '');
            }
            //Sin IVA
            else{
                $iva = 0;
            }

            $total = $subtotal + $iva + $envio;

            $pedidoCurso->subtotal = $subtotal;
            $pedidoCurso->iva = $iva;
            $pedidoCurso->envio = $envio;
            $pedidoCurso->total = $total;
            $pedidoCurso->save();

            $envio_text = '**';
            if($envio > 0){
                $envio_text = $envio;
            }

            $tipo = '';
            if($pedidoCurso->tipo == 1){
                $tipo = '*Tipo:* Sin IVA';
            }else if($pedidoCurso->tipo == 2){
                $tipo = '*Tipo:* Más IVA';
            }else{
                $tipo = '*Más IVA/Sin IVA:*';
            }

            $message = '*Detalles:*'.$salto.$salto.
            $tipo.$salto.
            '*Nombre:* '.$pedidoCurso->cliente.$salto.
            '*Teléfono:* '.$pedidoCurso->telefono.$salto.
            '*Email:* '.$pedidoCurso->email.$salto.
            $salto.$descripciones.
            'Subtotal *'.$subtotal.'* 💲'.$salto;

            if ($pedidoCurso->tipo == 2) {
                $message .= 'Iva *'.$iva.'* 💲'.$salto;
            }

            $message .= 'Envío *'.$envio_text.'* 🚚'.$salto.
            'Total *'.$total.'* 💰'.$salto.$salto.
            '*Escribe la Opción:*'.$salto.$salto.
            'Cancelar cotización'.$salto.
            'Confirmar cotización'.$salto.$salto.
            'Ó'.$salto.$salto.
            'Envíame más datos, ejemplo 👇🏻:';

            $this->messageTextToCliente($cliente,$message);

            $message = 'Agrega esta otra descripción'.$salto.
            'Producto o servicio:'.$salto.
            'Cantidad:'.$salto.
            'Valor Unitario:';

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

    public function flowListaCotizaciones($cliente)
    {

        $pedidoCurso = Cotizacion::
            where('cliente_id',$cliente->id)
            ->where('status', 0)
            ->with('gastos')
            ->first();

        //si no tiene una cotizacioon en curso quito el flujo
        if(!$pedidoCurso){
            //quitar el flujo
            DB::table('bot_clientes')
                ->where('id', $cliente->id)
                ->update([
                    'flow_id' => null,
                ]);
        }

        $user_token=User::find(56);
        $token = JWTAuth::fromUser($user_token);

        $claveAdicional = config('app.lada_d');

        $cadenaEncriptada = Crypt::encrypt($cliente->id, $claveAdicional);

        $link = 'https://social.internow.com.mx/#/cotizaciones-bot/'.$cadenaEncriptada.'/'.$token;

        $short_link = $this->shortenURL($link);

        $message = 'Ingresa en el siguiente enlace para ver tus cotizaciones:

{{short_link}}';

        $message = str_replace("{{short_link}}", $short_link, $message);

        $this->messageTextToCliente($cliente,$message);

        return 1;

    }

    public function cotizacionPdf($cotizacion_id)
    {

        set_time_limit(500);

        $cotizacion = Cotizacion::
            with('gastos')
            ->find($cotizacion_id);

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
        Storage::disk('public_root')->put('pdfs/cotizaciones/'.$nombreArchivo, $pdf->output());

        // Obtiene la URL del archivo guardado
        $url = asset('pdfs/cotizaciones/' . $nombreArchivo);

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



}
