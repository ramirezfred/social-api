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
use App\Models\BotCita;

use App\Models\Producto;
use App\Models\Color;
use App\Models\Tipo;
use App\Models\ProductoImagen;
use App\Models\Pedido;
use App\Models\PedidoDetalle;

use App\Models\Cotizacion;
use App\Models\CotizacionGasto;

//facturas
use App\Models\CfdiEmpresa;
use App\Models\CfdiProducto;

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
use App\Http\Traits\BotFunctionsTrait;
use App\Http\Traits\ApiChatPdfTrait;

use App\Http\Controllers\FlowCotizacionController;
use App\Http\Controllers\FlowFacturaController;

date_default_timezone_set('America/Mexico_City');

class WebhooksWhatsAppController extends Controller
{

    use ApiWhatsAppTrait;
    use ApiTextCortexTrait;
    use ApiOpenAiTrait;
    use BotFunctionsTrait;
    use ApiChatPdfTrait;

    //Facebook
    public function meta(Request $request)
    {
        //https://apisocial.internow.com.mx/api/webhooks/facebook

        $challenge = $request->input('hub_challenge');
        $verify_token = $request->input('hub_verify_token');

        if ($verify_token === 'ladawebhookmeta') {
            return $challenge;
            //return response($challenge, 200);
        }

        $input = $request->all();
        // Aquí puedes realizar cualquier acción necesaria con los datos recibidos del Webhook

        // Por ejemplo, guardar los datos recibidos en un archivo de registro
        file_put_contents('webhook_log.txt', print_r($input, true), FILE_APPEND);
    }

    public function handle(Request $request)
    {
        $input = $request->all();
        // Aquí puedes realizar cualquier acción necesaria con los datos recibidos del Webhook

        // Verificar el tipo de evento
        if ($input['object'] === 'page' && isset($input['entry'][0]['messaging'])) {
            foreach ($input['entry'][0]['messaging'] as $message) {
                if (isset($message['post'])) {
                    // Procesar el comentario en la publicación
                    $postId = $message['post']['id'];
                    $commentId = $message['post']['comments']['comment_id'];
                    $senderId = $message['sender']['id'];

                    // Ejecutar acciones específicas en base al comentario
                    // Por ejemplo, obtener el contenido del comentario
                    $comment = $this->getFacebookComment($commentId);

                    // Realizar cualquier otra acción necesaria con el comentario, como almacenarlo en la base de datos o enviar una notificación, etc.

                    // Regresar una respuesta exitosa a Facebook
                    return response('OK', 200);
                }
            }
        }

        // Si no se procesa un evento de comentario, puedes realizar otras acciones o retornar una respuesta diferente si es necesario

        // Por ejemplo, guardar los datos recibidos en un archivo de registro
        file_put_contents('webhook_log.txt', print_r($input, true), FILE_APPEND);

        // Regresar una respuesta exitosa a Facebook
        return response('OK', 200);
    }

    public function metaHandle(Request $request)
    {

        set_time_limit(500);
        
        $input = $request->all();
        // Aquí puedes realizar cualquier acción necesaria con los datos recibidos del Webhook

        // Verificar el tipo de evento
        if ($input['object'] === 'whatsapp_business_account' && isset($input['entry'][0]['changes'])) {

            $whatsapp_id = $input['entry'][0]['id'];

            foreach ($input['entry'][0]['changes'] as $change) {
                if ($change['field'] === 'messages') {


                        if(
                            isset($change['value']['contacts']) &&
                            isset($change['value']['messages']) 
                        ){

                            // Procesar el evento de messages

                            $name = $change['value']['contacts'][0]['profile']['name'];
                            $wa_id = $change['value']['contacts'][0]['wa_id'];

                            $from = $change['value']['messages'][0]['from'];
                            $id = $change['value']['messages'][0]['id'];
                            $type = $change['value']['messages'][0]['type'];

                            if ($type != 'text'){
                                file_put_contents('webhook_ws_log200.txt', print_r($input, true), FILE_APPEND);

                                return response('OK', 200);
                            }

                            $body = $change['value']['messages'][0]['text']['body'];
                            
                            // Por ejemplo, guardar los datos recibidos en un archivo de registro

                            file_put_contents('webhook_ws_log200.txt', print_r($input, true), FILE_APPEND);

                            //registro el mensaje en la BD
                            $cliente_id = $this->tratarMensaje($whatsapp_id,$name,$from,$id,$body,$type);

                            if($cliente_id){

                                // Verifica si hay una ejecución anterior en curso para este cliente
                                if (Cache::has('ejecucion:'.$cliente_id)) {
                                    // Si hay una ejecución anterior en curso, devuelve una respuesta sin esperar
                                    return response('OK', 200);
                                }

                                // Establece la variable de bloqueo para este cliente
                                Cache::put('ejecucion:'.$cliente_id, true, 4); // 4 segundos de duración de la ejecución

                                // Pausa la ejecución durante 15 segundos
                                sleep(4);

                                // Aquí puedes procesar el mensaje recibido del cliente
                                $respuesta = $this->respMsjsSinProcesar($cliente_id);

                                // Elimina la variable de bloqueo después de los 15 segundos
                                Cache::forget('ejecucion:'.$cliente_id);

                                // Regresar una respuesta exitosa a Facebook
                                return response('OK', 200);

                            }else{
                                /*Regresar una respuesta exitosa a Facebook
                                para que no me vuelva en enviar la notificacion*/
                                return response('OK', 200);
                            }
  

                        }               
 
                }
            }

            // Regresar una respuesta exitosa a Facebook
            //return response('OK', 200);
        }


        // Si no se procesa un evento de feed, puedes realizar otras acciones o retornar una respuesta diferente si es necesario

        // Por ejemplo, guardar los datos recibidos en un archivo de registro
        //file_put_contents('webhook_ws_log500.txt', print_r($input, true), FILE_APPEND);

        // Regresar una respuesta exitosa a Facebook
        return response('OK', 200);
    }

    

    private function tratarMensaje($whatsapp_id,$name,$from,$id,$body,$type)
    {
        $obj=Bot::
            where('whatsapp_id',$whatsapp_id)
            ->first();

        if (!$obj)
        {
            return 0;
        }

        $cliente_id = $this->getClienteId($obj->id,$name,$from);
        $this->storeMsgChat($obj->id,$cliente_id,$id,$body,1); //cliente

        return $cliente_id;
    }

    private function getClienteId($bot_id, $nombre, $telefono)
    {
        $obj=BotCliente::
            where('bot_id',$bot_id)
            ->where('telefono',$telefono)
            ->first();

        if (!$obj)
        {

            //Proxima fecha de cobro
            $date = Carbon::now()->addMonth();
            $pay_next_day = $date->day;
            $pay_next_month = $date->month;
            $pay_next_year = $date->year;

            //Limite de prueba
            $date2 = Carbon::now()->addDays(3);
            $test_day = $date2->day;
            $test_month = $date2->month;
            $test_year = $date2->year;

            $nuevoObj=BotCliente::create([
                'bot_id'=>$bot_id,
                'nombre'=>$nombre,
                'telefono'=>$telefono,
                'status_empresa'=>0,
                'status'=>1,
                'pay_next_day'=>$pay_next_day,
                'pay_next_month'=>$pay_next_month,
                'pay_next_year'=>$pay_next_year,
                'pago'=>0,
                'test_day'=>$test_day,
                'test_month'=>$test_month,
                'test_year'=>$test_year,
                'count_querys'=>0,
                'flag_msg_querys'=>null,
                'flag_colores'=>null,
                'flag_stock'=>null,
                'costo_envio'=>0,
                'color_a'=>'#d6d9da',
                'color_b'=>'#618a9f',
                'color_c'=>'#1a2d4e',
                'hab_citas'=>1,
                'hab_redes'=>1,
                'hab_pedidos'=>0,
                'hab_cotizaciones'=>1,
                'hab_facturas'=>1,
                'flag_bienvenida'=>null,
                'max_facturas'=>25,
                'count_facturas'=>0,
                'count_alertas'=>0,
                'fecha_alerta'=>Carbon::now(),
                
            ]);

            //Crear la empresa emisora para las facturas
            $nuevaEmpresa=CfdiEmpresa::create([
                'bot_cliente_id'=>$nuevoObj->id,
                'tipo_persona'=>null,
                'Rfc'=>null,
                'RazonSocial'=>null,
                'RegimenFiscal'=>null,
                'FacAtrAdquirente'=>null,
                'CP'=>null,
                'cer'=>null,
                'key'=>null,
                'pass'=>null,
                'flag_descuento'=>0,
                'flag_objetoImp'=>1,
                'flag_retencion'=>0,
                'flag_producto'=>0,

            ]);

            //Crear el producto asociado a la empresa
            $nuevoProducto=CfdiProducto::create([
                'empresa_id'=>$nuevaEmpresa->id,
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


            return $nuevoObj->id;
        }

        return $obj->id;
        
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

    public function respMsjsSinProcesar($cliente_id)
    {
        $mensajes = BotChat::
            select('id','bot_id','cliente_id','text','autor','status')
            ->where('status', 0)
            ->where('autor', 1)
            ->where('cliente_id', $cliente_id)
            ->get();

        DB::table('bot_chats')
            ->where('cliente_id', $cliente_id)
            ->update(['status' => 1]);

        if(count($mensajes)==0){
            return 1;
        }

        $cliente = BotCliente::
            select('id','bot_id','nombre','telefono','empresa','status_empresa',
                'status','count_querys','flag_msg_querys','flow_id','flag_colores','flag_stock',
                'costo_envio','header','footer','logo',
                'hab_citas','hab_redes','hab_pedidos','hab_cotizaciones','hab_facturas',
                'flag_bienvenida','max_facturas','count_facturas')
            ->find($cliente_id);

        $cliente->count_querys = $cliente->count_querys + 1;
        $cliente->save();
        if($cliente->count_querys == 16 && $cliente->flag_msg_querys != 1){
            $cliente->status = 0;
            $cliente->save();
        }

        $msjs = []; 
        for ($j=0; $j < count($mensajes); $j++) { 
            array_push($msjs,$mensajes[$j]->text);
        }
        $cliente->mensajes = $msjs;

        // $log = [];
        // $resul = (object) [
        //     'bot_id' => $cliente->bot_id,
        //     'nombre' => $cliente->nombre,
        //     'telefono' => $cliente->telefono,
        //     'mensajes' => $cliente->mensajes,
        // ];
        // array_push($log,$resul);

        //Si el cliente no ha registrado su nombre comercial
        // if($cliente->status_empresa == 0 || $cliente->status_empresa == null){
        //     $this->flowNombreComercial($cliente);
        //     return 1;
        // }

        //respuesta automatica
        if(true){

            DB::table('bot_clientes')
            ->where('id', $cliente->id)
            ->update([
                'flow_id' => null,
            ]);

            // $message = 'Respuesta automática. *InterNow* 🤖';

            // $this->messageTextToCliente($cliente,$message);
                
            // return 1;

            // $respA = $this->_davinciTestBot($cliente->bot_id,$cliente->mensajes);
            // if ($respA['status'] == 200) {

            //     //array_push($log,$respA['open_ai']);

            //     //pruebas
            //     $respC = $this->_messageText($cliente->bot_id,$cliente->telefono,$respA['text']);
            //     if ($respC['status'] == 200) {
            //         $this->storeMsgChat($cliente->bot_id,$cliente_id,null,$respA['text'],0); //bot
            //         return 1;
            //     }else{
            //         return 0;
            //     }

            // }else{

            //     //errores de open_ai
            //     //array_push($log,$respA);
            //     file_put_contents('webhook_log_open_ai.txt', print_r($respA, true), FILE_APPEND);

            //     $this->messageTextToCliente($cliente,$respA['error']);
            //     return 0;

            // }

            $respA = $this->_respuestaChatPdf($cliente->mensajes);
            file_put_contents('webhook_log_chat_pdf.txt', print_r($respA, true), FILE_APPEND);
            if ($respA['status'] == 200) {

                //pruebas
                $respC = $this->_messageText($cliente->bot_id,$cliente->telefono,$respA['text']);
                file_put_contents('webhook_log_ws_message.txt', print_r($respC, true), FILE_APPEND);
                if ($respC['status'] == 200) {
                    $this->storeMsgChat($cliente->bot_id,$cliente_id,null,$respA['text'],0); //bot
                    return 1;
                }else{
                    return 0;
                }

            }else{

                //errores de chat_pdf
                //array_push($log,$respA);
                //file_put_contents('webhook_log_chat_pdf.txt', print_r($respA, true), FILE_APPEND);

                $this->messageTextToCliente($cliente,$respA['error']);
                return 0;

            }
        }

        //mensaje de bienvenida
        if($cliente->flag_bienvenida != 1){

            DB::table('bot_clientes')
            ->where('id', $cliente->id)
            ->update([
                'flag_bienvenida' => 1,
            ]);

            $message = '¡Bienvenidos a la inteligencia artificial de *InterNow*!🤖👋 

Estamos emocionados de que te unas a nuestra plataforma de inteligencia artificial. Aquí, tendrás acceso a *15 peticiones gratuitas* para experimentar y explorar las capacidades de nuestra IA🧾🛜. 

Después de tus primeras peticiones te ofrecemos la oportunidad de suscribirte a nuestro servicio y desbloquear todo el potencial ilimitado de nuestra inteligencia artificial.

¡Descubre un mundo de posibilidades para tu negocio y obtén conocimiento sin límites con InterNow! 🌎';

            $this->messageTextToCliente($cliente,$message);

            $this->flowSaludo($cliente);
                
            return 1;
        }

        //Si el cliente no ha configurado su marca
        if($cliente->header == null || $cliente->header == '' ||
            $cliente->footer == null || $cliente->footer == '' ||
            $cliente->logo == null || $cliente->logo == '' ||
            $cliente->empresa == null || $cliente->empresa == ''){

            $message = '🤖 ¡Hola! Estoy aquí para asistirte. 😊

Antes de continuar, necesito configurar tu marca para que pueda ayudarte de la mejor manera posible. 🏢✨';

            $this->messageTextToCliente($cliente,$message);

            $this->flowConfigCliente($cliente);
                
            return 1;
        }

        //si el cliente realizo todas las consultas gratis
        if($cliente->count_querys == 16 && $cliente->flag_msg_querys != 1){

            DB::table('bot_clientes')
            ->where('id', $cliente->id)
            ->update([
                'flag_msg_querys' => 1,
            ]);

            $claveAdicional = config('app.lada_d');

            $cadenaEncriptada = Crypt::encrypt($cliente->id, $claveAdicional);

            $link = 'https://social.internow.com.mx/#/pagar-bot/'.$cadenaEncriptada;

            $short_link = $this->shortenURL($link);

            $message = 'Has alcanzado el limite de consultas gratuitas.

Te invitamos a realizar tu pago en el siguiente enlace para seguir disfrutando de nuestros servicios:

{{short_link}}';

            $message = str_replace("{{short_link}}", $short_link, $message);


            $respC = $this->_messageText($cliente->bot_id,$cliente->telefono,$message);
            // array_push($log,$respC);
            // file_put_contents('webhook_log_ws_message.txt', print_r($log, true), FILE_APPEND);
            if ($respC['status'] == 200) {

                $this->storeMsgChat($cliente->bot_id,$cliente_id,null,$message,0); //bot

                return 1;

            }else{

                return 0;

            }
        }


        //si el cliente no ha pagado
        if($cliente->status != 1){

            $claveAdicional = config('app.lada_d');

            $cadenaEncriptada = Crypt::encrypt($cliente->id, $claveAdicional);

            $link = 'https://social.internow.com.mx/#/pagar-bot/'.$cadenaEncriptada;

            $short_link = $this->shortenURL($link);

            $message = 'Te invitamos a realizar tu pago en el siguiente enlace para seguir disfrutando de nuestros servicios:

{{short_link}}';

            $message = str_replace("{{short_link}}", $short_link, $message);

            $respC = $this->_messageText($cliente->bot_id,$cliente->telefono,$message);
            // array_push($log,$respC);
            // file_put_contents('webhook_log_ws_message.txt', print_r($log, true), FILE_APPEND);
            if ($respC['status'] == 200) {

                $this->storeMsgChat($cliente->bot_id,$cliente_id,null,$message,0); //bot

                return 1;

            }else{

                return 0;

            }
        }

        /*$message = 'Último mensaje: '.$msjs[count($msjs)-1];
        $respC = $this->_messageText($cliente->bot_id,$cliente->telefono,$message);
        //array_push($log,$respC);
        //file_put_contents('webhook_log_ws_message.txt', print_r($log, true), FILE_APPEND);
        if ($respC['status'] == 200) {

            $this->storeMsgChat($cliente->bot_id,$cliente_id,$message,0); //bot

        }else{

            return 0;

        }*/

        //si el cliente tiene un flujo activo, seguir con ese flujo
        if($cliente->flow_id){

            $bot_config = BotConfig::
                select('id','palabra_clave','flow_id','function')
                ->where('flow_id', $cliente->flow_id)
                ->get();
            if (count($bot_config)==0)
            {
                $message = 'flow_id no encontrado';
                $this->messageTextToCliente($cliente,$message);
                return 0;
            }

            $nomFunctionFlow = $bot_config[0]->function;
            $this->$nomFunctionFlow($cliente);
            return 1;
        }

        $respA = $this->_davinciPalabraClaveBot($cliente->bot_id,$cliente->mensajes);
        if ($respA['status'] == 200) {

            //array_push($log,$respA['open_ai']);

            $cliente->palabra_clave = $respA['text'];

            //pruebas
            // $respC = $this->_messageText($cliente->bot_id,$cliente->telefono,$respA['text']);
            // if ($respC['status'] == 200) {
            //     $this->storeMsgChat($cliente->bot_id,$cliente_id,null,$respA['text'],0); //bot
            //     return 1;
            // }else{
            //     return 0;
            // }

            file_put_contents('webhook_log_open_ai.txt', print_r($respA, true), FILE_APPEND);

            $this->checkInitFlow($cliente);
            return 1;

            
        }else{

            //errores de open_ai
            //array_push($log,$respA);
            file_put_contents('webhook_log_open_ai.txt', print_r($respA, true), FILE_APPEND);

            $this->messageTextToCliente($cliente,$respA['error']);
            return 0;

        }
        
        return 1;

        // return response()->json([
        //     //'clientes_ids'=>$clientes_ids,
        //     'cliente'=>$cliente,
        //     //'mensajes'=>$mensajes,
        // ], 200);
    }

    public function checkInitFlow($cliente)
    {
        $bot_config = BotConfig::
            select('id','palabra_clave','flow_id','function')
            ->where('palabra_clave', $cliente->palabra_clave)
            ->get();
        if (count($bot_config)==0)
        {
            // $message = 'Palabra clave no encontrada';
            // $this->messageTextToCliente($cliente,$message);
            // return 0;

            $this->flowNoAplicable($cliente);
            return 1;
        }

        $nomFunctionFlow = $bot_config[0]->function;
        $this->$nomFunctionFlow($cliente);

        return 1;

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

    public function flowSaludo($cliente)
    {

        DB::table('bot_clientes')
            ->where('id', $cliente->id)
            ->update([
                'flow_id' => null,
            ]);

        $salto = '
';

        $message = '¡Hola! ¿Cómo puedo ayudarte hoy? 😊

Estoy aquí para ayudarte con lo siguiente:'.$salto;

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

    public function flowDespedida($cliente)
    {

        DB::table('bot_clientes')
            ->where('id', $cliente->id)
            ->update([
                'flow_id' => null,
            ]);

        $message = '¡Es un placer ayudar! Si tienes alguna otra pregunta o necesitas asistencia en el futuro, no dudes en contactarme. 😊';

        $this->messageTextToCliente($cliente,$message);

        return 1;


    }

    
    public function flowCfdiFactura($cliente)
    {
        //Indicar que el cliente inicio el primer estado de un flujo
        DB::table('bot_clientes')
            ->where('id', $cliente->id)
            ->update([
                'flow_id' => 4,
            ]);

        // Crear una instancia del otro controlador
        $FlowFacturaController = new FlowFacturaController();

        // Llamar a una función del otro controlador
        $resultado = $FlowFacturaController->flowCfdiFactura($cliente);
        return 1;
    }

    public function flowProductos($cliente)
    {

        if($cliente->hab_pedidos != 1){
            $this->flowNoAplicable($cliente);
            return 1;
        }

        $pedidoCurso = Pedido::
            where('cliente_id',$cliente->id)
            ->where('status', 0)
            ->with('detalles', 'detalles.producto', 'detalles.color', 'detalles.tipo')
            ->first();

        //si no tiene un pedido en curso quito el flujo
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

        $link = 'https://social.internow.com.mx/#/productos-bot/'.$cadenaEncriptada.'/'.$token;

        $short_link = $this->shortenURL($link);

        $message = 'Ingresa en el siguiente enlace para ver y crear tus productos:

{{short_link}}';

        $message = str_replace("{{short_link}}", $short_link, $message);

        $this->messageTextToCliente($cliente,$message);

        return 1;

    }

    public function flowRedes($cliente)
    {
        if($cliente->hab_redes != 1){
            $this->flowNoAplicable($cliente);
            return 1;
        }

        DB::table('bot_clientes')
            ->where('id', $cliente->id)
            ->update([
                'flow_id' => null,
            ]);

        $user_social = User::with('marcas')
            ->where('bot_cliente_id', $cliente->id)
            ->first();

        if(!$user_social || count($user_social->marcas) == 0){

            //$url = $this->shortenURL('https://social.internow.com.mx/#/vincular-cliente');

            $user_token=User::find(56);
            $token = JWTAuth::fromUser($user_token);

            $claveAdicional = config('app.lada_d');

            $cadenaEncriptada = Crypt::encrypt($cliente->id, $claveAdicional);

            $link = 'https://social.internow.com.mx/#/vincular-cliente/'.$cadenaEncriptada.'/'.$token;

            $short_link = $this->shortenURL($link);

            $message = '¡Descubre nuestra Inteligencia Artificial en acción! 📱

Inicia sesión de tus redes sociales, carga tu identidad corporativa y realizaremos los post por ti.  Vive la experiencia InterNow, ya no te preocupes de las publicaciones. ⚙️

Loguea primero Facebook y después instagram, al detectar la IA qué logueaste las redes sociales empezará a publicar automáticamente en los horarios elegidos. 📆

{{short_link}}

¡Te encantará!🤓

PD. Si ya tienes el servicio configurado entrarás a la sección de vinculación de redes. ⛓️

Sigue todos los pasos.';

            $message = str_replace("{{short_link}}", $short_link, $message);

        }else{

            $cadena = $user_social->marcas[0]->id;

            $claveAdicional = config('app.lada_a');

            $cadenaEncriptada = Crypt::encrypt($cadena, $claveAdicional);

            $short_link = $this->shortenURL('https://social.internow.com.mx/#/vincularedes/'.$cadenaEncriptada);

            $message = '¡Descubre nuestra Inteligencia Artificial en acción! 📱

Inicia sesión de tus redes sociales, carga tu identidad corporativa y realizaremos los post por ti.  Vive la experiencia InterNow, ya no te preocupes de las publicaciones. ⚙️

Loguea primero Facebook y después instagram, al detectar la IA qué logueaste las redes sociales empezará a publicar automáticamente en los horarios elegidos. 📆

{{short_link}}

¡Te encantará!🤓

PD. Si ya tienes el servicio configurado entrarás a la sección de vinculación de redes. ⛓️';

            $message = str_replace("{{short_link}}", $short_link, $message);
        }

        $this->messageTextToCliente($cliente,$message);

        return 1;


    }

    public function flowNombreComercial($cliente)
    {

        DB::table('bot_clientes')
            ->where('id', $cliente->id)
            ->update([
                'flow_id' => null,
            ]);

        $prompt = 'Estás extrayendo el nombre comercial de un cliente a partir de los mensajes que envía el cliente. 

Los datos que has recaudado hasta el momento son:
{
   "nombre": "{{empresa}}"
}

El último mensaje que envió el cliente es el siguiente.
Mensaje: {{mensaje}}

Extrae la información del Mensaje y genera un JSON con la siguiente estructura:

{
   "nombre": "",
   "confirmar":""
}

Solo usa la información del mensaje.

El campo nombre es el nombre comercial extraido del mensaje

confirmar puede tener los valores 1 o 0.
El valor 1 indica que el cliente está confirmando que el nombre comercial es correcto.
El valor 0 indica que el cliente aún no está confirmando el nombre comercial.
Ejemplos de mensaje para confirmar:
- Los datos son correctos
- Es correcto
- Correcto
- De acuerdo
- Si
- Confirmar
- Cierto
- Apoyar
- Sostener
- Autorizar
- Secundar
- Ratificar
- Convalidar
- Revalidar
- Constatar
- Reafirmar
- Corroborar
- Comprobar
- Asegurar
- Afirmar
- Certificar
- Aseverar';

        $text_mensajes = '';
        for ($i=0; $i < count($cliente->mensajes); $i++) { 
            if($i == 0){
                $text_mensajes = $cliente->mensajes[$i];
            }else{
                $text_mensajes = $text_mensajes.', '.$cliente->mensajes[$i];
            }
        }
        
        $prompt = str_replace("{{mensaje}}", $text_mensajes, $prompt);
        $prompt = str_replace("{{empresa}}", $cliente->empresa, $prompt);

        $respA = $this->_davinciRespuestaPrompt($prompt);
        if ($respA['status'] == 200) {

            // $message = $respA['text'];
            // $this->messageTextToCliente($cliente,$message);
            // return 1;

            $cadena = $respA['text'];

            $posicionA = strpos($cadena, '{');
            $posicionB = strrpos($cadena, '}');
            $cadena = substr($cadena,$posicionA,$posicionB+1-($posicionA));

            if ($posicionA === false || $posicionB === false) {
                return 0; // Retornar cadena vacía si no se encuentran los caracteres
            }

            $obj = json_decode($cadena);

            /*si no hay informacion referente al nombre comercial*/
            if(
                $obj->nombre == "" &&

                $obj->confirmar == 0 
            ){

                $message = '🤖 ¡Hola! Estoy aquí para asistirte. 😊

Antes de continuar, necesito saber el nombre comercial de tu empresa o negocio. Por favor, indícame cómo se llama para que pueda ayudarte de la mejor manera posible. 🏢✨';

                $this->messageTextToCliente($cliente,$message);

                return 1;

            }

            if($obj->nombre != ""){
                
                // Verificar que el nombre comercial tenga una longitud de maximo 90 digitos
                if (strlen($obj->nombre) <= 90) {
                    DB::table('bot_clientes')
                    ->where('id', $cliente->id)
                    ->update([
                        'empresa' => $obj->nombre,
                    ]);
                } else {
                    // El nombre comercial es inválido
                    $message = 'Por favor, verifica el Nombre comercial. Debe contener como máximo 90 caracteres. Por favor, inténtalo nuevamente. 🏢';
                    $this->messageTextToCliente($cliente,$message);
                    return 1; 
                }
            }

            $clienteBD = BotCliente::
                select('id','empresa','status')
                ->find($cliente->id);

            $message = '';

            /*si ya estan todos los campos y no ha confirmado
            pedir confirmacion*/
            if(
                $clienteBD->empresa &&
                $obj->confirmar == 0
            ){

                $message = 'Por favor, confirma que tu nombre comercial es correcto:

🏢 Nombre comercial: {{empresa}}

Espero tu confirmación.';

                $message = str_replace("{{empresa}}", $clienteBD->empresa, $message);

            }

            /*si ya estan todos los campos y está confirmado*/
            if(
                $clienteBD->empresa &&
                $obj->confirmar == 1
            ){

                DB::table('bot_clientes')
                    ->where('id', $cliente->id)
                    ->update([
                        'status_empresa' => 1,
                    ]);

                $message = 'Tu nombre comercial ha sido actualizado correctamente.';

            }

            /*si aun falta algun dato*/
            if(
                $clienteBD->empresa == '' ||
                $clienteBD->empresa == null
            ){

                $message = 'Antes de continuar, necesito saber el nombre comercial de tu empresa o negocio. Por favor, indícame cómo se llama para que pueda ayudarte de la mejor manera posible. 🏢✨';

            }

            $this->messageTextToCliente($cliente,$message);
            return 1;
            
        }else{
            return 0;
        }

    }

    public function flowCita($cliente)
    {
        if($cliente->hab_citas != 1){
            $this->flowNoAplicable($cliente);
            return 1;
        }

        //Indicar que el cliente inicio el primer estado de un flujo
        DB::table('bot_clientes')
            ->where('id', $cliente->id)
            ->update([
                'flow_id' => 1,
            ]);

        $bot_config = BotConfig::
            select('id','palabra_clave','flow_id','prompt')
            ->where('flow_id', 1)
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
        $prompt = $this->contextoFecha($prompt);

        $citaCurso = BotCita::
            where('cliente_id',$cliente->id)
            ->where('status', 0)
            ->first();

        if(!$citaCurso){
            $prompt = str_replace("{{nombre}}", "", $prompt);
            $prompt = str_replace("{{telefono}}", "", $prompt);
            $prompt = str_replace("{{fecha}}", "", $prompt);
            $prompt = str_replace("{{hora}}", "", $prompt);
            $prompt = str_replace("{{motivo}}", "", $prompt);
        }else{
            $cita_nombre = "";
            $cita_telefono = "";
            $cita_fecha = "";
            $cita_hora = "";
            $cita_motivo = "";

            if($citaCurso->nombre != null && $citaCurso->nombre != ""){
                $cita_nombre = $citaCurso->nombre;
            }
            if($citaCurso->telefono != null && $citaCurso->telefono != ""){
                $cita_telefono = $citaCurso->telefono;
            }
            if($citaCurso->fecha != null && $citaCurso->fecha != ""){
                $cita_fecha = $citaCurso->fecha;
            }
            if($citaCurso->hora != null && $citaCurso->hora != ""){
                $cita_hora = $citaCurso->hora;
            }
            if($citaCurso->motivo != null && $citaCurso->motivo != ""){
                $cita_motivo = $citaCurso->motivo;
            }

            $prompt = str_replace("{{nombre}}", $cita_nombre, $prompt);
            $prompt = str_replace("{{telefono}}", $cita_telefono, $prompt);
            $prompt = str_replace("{{fecha}}", $cita_fecha, $prompt);
            $prompt = str_replace("{{hora}}", $cita_hora, $prompt);
            $prompt = str_replace("{{motivo}}", $cita_motivo, $prompt);
        } 

        $respB = $this->_davinciRespuestaPrompt($prompt);
        if ($respB['status'] == 200) {

            $cadena = $respB['text'];

            //$this->messageTextToCliente($cliente,$cadena);
            //quitar el flujo
            // DB::table('bot_clientes')
            //     ->where('id', $cliente->id)
            //     ->update([
            //         'flow_id' => null,
            //     ]);
            //return 1;

            $log = [];
            array_push($log,$text_mensajes);
            array_push($log,$cadena);

            file_put_contents('webhook_log_cita.txt', print_r($log, true), FILE_APPEND);

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
                $obj->ver_citas = 0;
            }

            $subcadena = 'CONFIRMAR';

            // Buscar la subcadena en la cadena original (sensible a mayúsculas y minúsculas)
            $posicion = strpos($cadenaEnMayusculas, $subcadena);

            if ($posicion !== false) {
                $obj->nueva = 0;
                $obj->confirmar = 1;
                $obj->cancelar = 0;
                $obj->ver_citas = 0;
            }

            /*Si no hay informacion referente a la cita,
            responder con el prompt general*/
            if(
                $obj->cita->nombre == "" &&
                $obj->cita->telefono == "" &&
                $obj->cita->motivo == "" &&
                $obj->cita->fecha == "" &&
                $obj->cita->hora == "" &&

                $obj->nueva == 0 &&
                $obj->confirmar == 0 &&
                $obj->cancelar == 0 &&
                $obj->ver_citas == 0
            ){

                //elimino la cita en curso
                DB::table('bot_citas')
                    ->where('cliente_id', $cliente->id)
                    ->where('status', 0)
                    ->delete();

                //respondo con flujo no aplicable
                $this->flowNoAplicable($cliente);
                return 1;

            }

            /*Si quiere iniciar el proceso*/
            if(
                $obj->cita->nombre == "" &&
                $obj->cita->telefono == "" &&
                $obj->cita->motivo == "" &&
                //$obj->cita->fecha == "" &&
                $obj->cita->hora == "" &&

                $obj->nueva == 1
            ){

                //elimino la cita en curso en caso de que tenga
                DB::table('bot_citas')
                    ->where('cliente_id', $cliente->id)
                    ->where('status', 0)
                    ->delete();

                //crear una cita nueva en curso
                $nuevoObj=BotCita::create([
                    'bot_id'=>$cliente->bot_id,
                    'cliente_id'=>$cliente->id,
                    'status'=>0,
                ]);

                $message = 'Vamos a crear una cita 📇, me pudes enviar los datos en este formato 🤗:';

                $this->messageTextToCliente($cliente,$message);

                $message = 'Nombre cliente: 
Teléfono cliente: 
Fecha de cita: {{fecha}}
Hora de cita (hh:mm AM/PM): 
Motivo de la cita:';

                $hoy = date("d/m/Y");
                $manana = date("d/m/Y", strtotime("+1 day"));

                $message = str_replace("{{fecha}}", $manana, $message);

                $this->messageTextToCliente($cliente,$message);

                $user_token=User::find(56);
                $token = JWTAuth::fromUser($user_token);

                $claveAdicional = config('app.lada_d');

                $cadenaEncriptada = Crypt::encrypt($cliente->id, $claveAdicional);

                $link = 'https://social.internow.com.mx/#/store-cita-bot/'.$cadenaEncriptada.'/'.$token;

                $short_link = $this->shortenURL($link);

                $message = 'O puedes agendar dando click aquí 
{{short_link}}';

                $message = str_replace("{{short_link}}", $short_link, $message);

                $this->messageTextToCliente($cliente,$message);
                
                return 1;

            }


            $citaCurso = BotCita::
                where('cliente_id',$cliente->id)
                ->where('status', 0)
                ->first();

            if(!$citaCurso){
               //crear una cita nueva en curso
                $citaCurso=BotCita::create([
                    'bot_id'=>$cliente->bot_id,
                    'cliente_id'=>$cliente->id,
                    'status'=>0,
                ]);
            }

            if($obj->cita->nombre != ""){
                DB::table('bot_citas')
                    ->where('cliente_id', $cliente->id)
                    ->where('status', 0)
                    ->update([
                        'nombre' => $obj->cita->nombre,
                    ]);
            }

            if($obj->cita->telefono != ""){

                // Eliminar espacios en blanco y guiones si los hay
                $telefono = str_replace([' ', '-'], '', $obj->cita->telefono);

                // Verificar que el número de teléfono solo contenga dígitos y tenga una longitud de 10
                if (ctype_digit($telefono) && strlen($telefono) === 10) {
                    DB::table('bot_citas')
                        ->where('cliente_id', $cliente->id)
                        ->where('status', 0)
                        ->update([
                            'telefono' => $telefono,
                        ]);
                } else {
                    // El número de teléfono es inválido
                    $message = 'Por favor, verifica el número de teléfono. Debe contener 10 dígitos. Por favor, inténtalo nuevamente. 📞';
                    $this->messageTextToCliente($cliente,$message);
                    return 1; 
                }

                
            }

            if($obj->cita->fecha != "" && $obj->cita->fecha != $citaCurso->fecha){

                $fecha_nlp = $obj->cita->fecha;

                // Define el formato esperado
                $formatoEsperado = 'd/m/Y';
                // Intenta crear un objeto DateTime a partir de la fecha proporcionada
                $fechaObj = DateTime::createFromFormat($formatoEsperado, $fecha_nlp);
                // Verifica si la fecha coincide con el formato esperado
                $formatoChek = $fechaObj && $fechaObj->format($formatoEsperado) === $fecha_nlp;

                if(!$formatoChek){

                    $log = [];
                    $resul = (object) [
                        'fecha_nlp' => $fecha_nlp,
                    ];
                    array_push($log,$resul);

                    $resp = $this->_davinciFechaNLP($fecha_nlp);

                    array_push($log,$resp);
                        file_put_contents('log_fechas.txt', print_r($log, true), FILE_APPEND);

                    if ($resp['status'] == 200) {

                        $cadena = $resp['text'];

                        $posicionA = strpos($cadena, '{');
                        $posicionB = strrpos($cadena, '}');
                        $cadena = substr($cadena,$posicionA,$posicionB+1-($posicionA));

                        if ($posicionA === false || $posicionB === false) {
                            $message = 'Por favor, verifica la fecha y asegúrate de utilizar el formato día/mes/año. Por favor, inténtalo nuevamente. 📅';
                            $this->messageTextToCliente($cliente,$message);
                            return 1;
                        }

                        $obj_fecha_ia = json_decode($cadena);

                        if($obj_fecha_ia && $obj_fecha_ia->date_ia && $obj_fecha_ia->date_ia != ""){
                            DB::table('bot_citas')
                                ->where('cliente_id', $cliente->id)
                                ->where('status', 0)
                                ->update([
                                    'fecha' => $obj_fecha_ia->date_ia,
                                ]);

                            $partes = explode('/', $obj_fecha_ia->date_ia);
                            $dia = $partes[0];
                            $mes = $partes[1];
                            $anio = $partes[2];

                            DB::table('bot_citas')
                                ->where('cliente_id', $cliente->id)
                                ->where('status', 0)
                                ->update([
                                    'day' => $dia,
                                    'month' => $mes,
                                    'year' => $anio,
                                ]);
                        }
                        
                    }else{
                        $message = 'Por favor, verifica la fecha y asegúrate de utilizar el formato día/mes/año. Por favor, inténtalo nuevamente. 📅📅';
                        $this->messageTextToCliente($cliente,$message);
                        return 1;   
                    }
                }else{
                    DB::table('bot_citas')
                        ->where('cliente_id', $cliente->id)
                        ->where('status', 0)
                        ->update([
                            'fecha' => $fecha_nlp,
                        ]);

                    $partes = explode('/', $fecha_nlp);
                    $dia = $partes[0];
                    $mes = $partes[1];
                    $anio = $partes[2];

                    DB::table('bot_citas')
                        ->where('cliente_id', $cliente->id)
                        ->where('status', 0)
                        ->update([
                            'day' => $dia,
                            'month' => $mes,
                            'year' => $anio,
                        ]);
                }
                
            }

            if($obj->cita->hora != "" && $obj->cita->hora != $citaCurso->hora){

                $hora_nlp = $obj->cita->hora;

                $resp = $this->_davinciHoraNLP($hora_nlp);
                if ($resp['status'] == 200) {

                    $cadena = $resp['text'];

                    $posicionA = strpos($cadena, '{');
                    $posicionB = strrpos($cadena, '}');
                    $cadena = substr($cadena,$posicionA,$posicionB+1-($posicionA));

                    if ($posicionA === false || $posicionB === false) {
                        $message = 'Por favor, verifica la hora y asegúrate de utilizar el formato de doce horas hh:mm AM/PM. Por favor, inténtalo nuevamente. ⏰';
                        $this->messageTextToCliente($cliente,$message);
                        return 1;
                    }

                    $obj_hora_ia = json_decode($cadena);

                    if($obj_hora_ia && $obj_hora_ia->hora_ia && $obj_hora_ia->hora_ia != ""){

                        DB::table('bot_citas')
                            ->where('cliente_id', $cliente->id)
                            ->where('status', 0)
                            ->update([
                                'hora' => $obj_hora_ia->hora_ia,
                            ]);

                        $timestamp = strtotime($obj_hora_ia->hora_ia);
                        $hora_24h = date('H:i', $timestamp);
                        $partes = explode(':', $hora_24h);
                        $hora = $partes[0];
                        $minutos = $partes[1];

                        DB::table('bot_citas')
                            ->where('cliente_id', $cliente->id)
                            ->where('status', 0)
                            ->update([
                                'hour' => $hora,
                                'minutes' => $minutos,
                            ]);
                    }
                    
                }else{
                    $message = 'Por favor, verifica la hora y asegúrate de utilizar el formato de doce horas hh:mm AM/PM. Por favor, inténtalo nuevamente. ⏰';
                    $this->messageTextToCliente($cliente,$message);
                    return 1;  
                }
                
            }

            if($obj->cita->motivo != ""){
                DB::table('bot_citas')
                    ->where('cliente_id', $cliente->id)
                    ->where('status', 0)
                    ->update([
                        'motivo' => $obj->cita->motivo,
                    ]);
            }

            $citaBD = BotCita::
                where('cliente_id',$cliente->id)
                ->where('status', 0)
                ->first();

            // if(!$citaBD){
            //     return 0;
            // }

            $message = '';

            /*si ya estan todos los campos y no ha confirmado
            pedir confirmacion*/
            if(
                $citaBD->nombre &&
                $citaBD->telefono &&
                $citaBD->motivo &&
                $citaBD->fecha &&
                $citaBD->hora &&

                $obj->confirmar == 0
            ){

                $cadenaMsg = 'Por favor, confirma que los datos para tu cita son correctos:

👤 Nombre del cliente: {{nombre}}
📞 Teléfono del cliente: {{telefono}}
📅 Fecha deseada para la cita: {{fecha}}
⌚ Hora deseada para la cita: {{hora}}
📝 Motivo de la cita: {{motivo}}

Espero tu confirmación.';

                $cadenaMsg = str_replace("{{nombre}}", $citaBD->nombre, $cadenaMsg);
                $cadenaMsg = str_replace("{{telefono}}", $citaBD->telefono, $cadenaMsg);
                $cadenaMsg = str_replace("{{fecha}}", $citaBD->fecha, $cadenaMsg);
                $cadenaMsg = str_replace("{{hora}}", $citaBD->hora, $cadenaMsg);
                $cadenaMsg = str_replace("{{motivo}}", $citaBD->motivo, $cadenaMsg);

                $message = $cadenaMsg;

            }

            /*si ya estan todos los campos y está confirmado*/
            if(
                $citaBD->nombre &&
                $citaBD->telefono &&
                $citaBD->motivo &&
                $citaBD->fecha &&
                $citaBD->hora &&

                $obj->confirmar == 1
            ){

                DB::table('bot_citas')
                    ->where('cliente_id', $cliente->id)
                    ->where('status', 0)
                    ->update([
                        'status' => 1,
                    ]);

                //quitar el flujo
                DB::table('bot_clientes')
                    ->where('id', $cliente->id)
                    ->update([
                        'flow_id' => null,
                    ]);

                $cadenaMsg = 'Tu cita ha sido agendada correctamente.

El cliente será notificado el día de su cita por SMS.';

                $message = $cadenaMsg;

            }

            /*si aun falta algun dato*/
            if(
                !$citaBD->nombre ||
                !$citaBD->telefono ||
                !$citaBD->motivo ||
                !$citaBD->fecha ||
                !$citaBD->hora 
            ){

                $cadenaMsg = 'Para terminar de agendar tu cita aún faltan datos. Por favor, proporciona los datos faltantes:

👤 Nombre del cliente: {{nombre}}
📞 Teléfono del cliente: {{telefono}}
📅 Fecha deseada para la cita: {{fecha}}
⌚ Hora deseada para la cita: {{hora}}
📝 Motivo de la cita: {{motivo}}

Espero tus respuestas para agendar la cita correctamente.';

                $cadenaMsg = str_replace("{{nombre}}", $citaBD->nombre, $cadenaMsg);
                $cadenaMsg = str_replace("{{telefono}}", $citaBD->telefono, $cadenaMsg);
                $cadenaMsg = str_replace("{{fecha}}", $citaBD->fecha, $cadenaMsg);
                $cadenaMsg = str_replace("{{hora}}", $citaBD->hora, $cadenaMsg);
                $cadenaMsg = str_replace("{{motivo}}", $citaBD->motivo, $cadenaMsg);

                $message = $cadenaMsg;

            }

            /*si quiere cancelar*/
            if(
                $obj->cancelar == 1
            ){

                //quitar el flujo
                DB::table('bot_clientes')
                    ->where('id', $cliente->id)
                    ->update([
                        'flow_id' => null,
                    ]);

                DB::table('bot_citas')
                    ->where('cliente_id', $cliente->id)
                    ->where('status', 0)
                    ->delete();

                $cadenaMsg = 'Proceso cancelado correctamente...';

                $message = $cadenaMsg;

            }

            /*si quiere ver sus citas*/
            if(
                $obj->ver_citas == 1
            ){

                //quitar el flujo
                DB::table('bot_clientes')
                    ->where('id', $cliente->id)
                    ->update([
                        'flow_id' => null,
                    ]);

                DB::table('bot_citas')
                    ->where('cliente_id', $cliente->id)
                    ->where('status', 0)
                    ->delete();

                $user_token=User::find(56);
                $token = JWTAuth::fromUser($user_token);

                $claveAdicional = config('app.lada_d');
                $cadenaEncriptada = Crypt::encrypt($cliente->id, $claveAdicional);

                $link = 'https://social.internow.com.mx/#/citas-bot/'.$cadenaEncriptada.'/'.$token;

                $short_link = $this->shortenURL($link);

                $message = 'Ingresa en el siguiente enlace para ver tus citas:

{{short_link}}';

                $message = str_replace("{{short_link}}", $short_link, $message);

            }

            //$message = $respB['text'];

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

    public function flowListaPedidos($cliente)
    {

        $pedidoCurso = Pedido::
            where('cliente_id',$cliente->id)
            ->where('status', 0)
            ->with('detalles', 'detalles.producto', 'detalles.color', 'detalles.tipo')
            ->first();

        //si no tiene un pedido en curso quito el flujo
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

        $link = 'https://social.internow.com.mx/#/pedidos-bot/'.$cadenaEncriptada.'/'.$token;

        $short_link = $this->shortenURL($link);

        $message = 'Ingresa en el siguiente enlace para ver tus pedidos:

{{short_link}}';

        $message = str_replace("{{short_link}}", $short_link, $message);

        $this->messageTextToCliente($cliente,$message);

        return 1;

    }

    public function flowPedido($cliente)
    {
        if($cliente->hab_pedidos != 1){
            $this->flowNoAplicable($cliente);
            return 1;
        }

        //Indicar que el cliente inicio el primer estado de un flujo
        DB::table('bot_clientes')
            ->where('id', $cliente->id)
            ->update([
                'flow_id' => 2,
            ]);

        if($cliente->flag_colores === null){
            $message = '🛍️ Para crear un pedido, primero debes configurar tus productos. 📦';

            $this->messageTextToCliente($cliente,$message);

            $this->flowProductos($cliente);
                
            return 1;
        }else if($cliente->flag_colores == 1){
            $this->flowPedidoA($cliente);
            return 1;
        }else if($cliente->flag_colores == 0){
            $this->flowPedidoB($cliente);
            return 1;
        }

    }

    public function flowPedidoA($cliente)
    {

        $bot_config = BotConfig::
            select('id','palabra_clave','flow_id','prompt')
            ->where('flow_id', 2)
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
        $prompt = $this->contextoFecha($prompt);

        $pedidoCurso = Pedido::
            where('cliente_id',$cliente->id)
            ->where('status', 0)
            ->with('detalles', 'detalles.producto', 'detalles.color', 'detalles.tipo')
            ->first();

        if(!$pedidoCurso){
            $prompt = str_replace("{{detalles}}", "{}", $prompt);
        }else{

            $detalles = [];
            for ($i=0; $i < count($pedidoCurso->detalles); $i++) { 
                $resul = (object) [
                    'producto' => $pedidoCurso->detalles[$i]->producto->nombre,
                    'color' => $pedidoCurso->detalles[$i]->color->nombre,
                    'talla' => $pedidoCurso->detalles[$i]->tipo->nombre,
                    'cantidad' => $pedidoCurso->detalles[$i]->cantidad,
                ];
                array_push($detalles,$resul);
            }

            $pedido = [
                "envio" => $pedidoCurso->envio,
                "pedido" => $detalles,
            ];

            $pedidoString = json_encode($pedido);
            
            $prompt = str_replace("{{detalles}}", $pedidoString, $prompt);
        } 

        $respB = $this->_davinciRespuestaPrompt($prompt);
        if ($respB['status'] == 200) {

            $cadena = $respB['text'];

            $log = [];
            array_push($log,$text_mensajes);
            array_push($log,$cadena);

            file_put_contents('webhook_log_pedidos.txt', print_r($log, true), FILE_APPEND);

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

            //fusionar los items duplicados
            $array_pedido = [];
            for ($i=0; $i < count($obj->pedido); $i++) {

                $esta = false;
                $obj->pedido[$i]->producto = strtoupper($obj->pedido[$i]->producto);
                $obj->pedido[$i]->color = strtoupper($obj->pedido[$i]->color); 
                $obj->pedido[$i]->talla = strtoupper($obj->pedido[$i]->talla); 

                for ($j=0; $j < count($array_pedido); $j++) { 
                    if(
                        $obj->pedido[$i]->producto == $array_pedido[$j]->producto &&
                        $obj->pedido[$i]->color == $array_pedido[$j]->color &&
                        $obj->pedido[$i]->talla == $array_pedido[$j]->talla 
                    ){
                        $esta = true;
                        $array_pedido[$j]->cantidad = $array_pedido[$j]->cantidad + $obj->pedido[$i]->cantidad;
                    }
                }
                if(!$esta){
                    array_push($array_pedido,$obj->pedido[$i]);
                }

            }
            $obj->pedido = $array_pedido;

            $pedidoCurso = Pedido::
                where('cliente_id',$cliente->id)
                ->where('status', 0)
                ->with('detalles', 'detalles.producto', 'detalles.color', 'detalles.tipo')
                ->first();

            /*Si no hay informacion referente al pedido,
            responder con el prompt general*/
            if(
                (
                    count($obj->pedido) == 0 ||
                    (
                        count($obj->pedido) == 1 &&
                        $obj->pedido[0]->producto == "" &&
                        $obj->pedido[0]->color == "" &&
                        $obj->pedido[0]->talla == ""
                    ) 
                 ) &&

                $obj->nuevo == 0 &&
                $obj->confirmar == 0 &&
                $obj->cancelar == 0 &&
                $obj->ver_pedidos == 0 &&
                $obj->ver_productos == 0
            ){

                //elimino pedido curso en caso de que tenga
                if($pedidoCurso){
                    for ($i=0; $i < count($pedidoCurso->detalles); $i++) { 
                        $pedidoCurso->detalles[$i]->tipo->stock = $pedidoCurso->detalles[$i]->tipo->stock + $pedidoCurso->detalles[$i]->cantidad;
                        $pedidoCurso->detalles[$i]->tipo->save();
                        $pedidoCurso->detalles[$i]->delete();
                    }
                    $pedidoCurso->delete();
                }

                //respondo con flujo no aplicable
                $this->flowNoAplicable($cliente);
                return 1;

            }

            /*Si quiere iniciar el proceso*/
            if(
                (
                    count($obj->pedido) == 0 ||
                    (
                        count($obj->pedido) == 1 &&
                        $obj->pedido[0]->producto == "" &&
                        $obj->pedido[0]->color == "" &&
                        $obj->pedido[0]->talla == "" 
                    ) 
                 ) &&

                $obj->nuevo == 1
            ){

                //elimino el pedido en curso en caso de que tenga
                if($pedidoCurso){
                    for ($i=0; $i < count($pedidoCurso->detalles); $i++) { 
                        $pedidoCurso->detalles[$i]->tipo->stock = $pedidoCurso->detalles[$i]->tipo->stock + $pedidoCurso->detalles[$i]->cantidad;
                        $pedidoCurso->detalles[$i]->tipo->save();
                        $pedidoCurso->detalles[$i]->delete();
                    }
                    $pedidoCurso->delete();
                }

                $orden_count = Pedido::
                    where('cliente_id',$cliente->id)
                    ->count();

                $costo_envio = 0;
                if($cliente->costo_envio > 0){
                    $costo_envio = $cliente->costo_envio;
                }

                //crear un pedido nuevo en curso
                $nuevoObj=Pedido::create([
                    'bot_id'=>$cliente->bot_id,
                    'cliente_id'=>$cliente->id,
                    'status'=>0,
                    'subtotal'=>0,
                    'envio'=>$costo_envio,
                    'total'=>0,
                    'orden'=>$orden_count+1,
                ]);

                $message = '¡Hola! 👋 Estoy aquí para ayudarte a crear un pedido. Por favor, proporciona los productos que deseas solicitar en el siguiente formato:

Ejemplo:
🛍️ Producto: Camiseta
🎨 Color: Rojo
👕 Talla: XL
🔢 Cantidad: 3

Puedes escribir uno o muchos productos en un pedido. 🖊️

*Espero tus respuestas para crear el pedido correctamente.*';

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

                //elimino el pedido en curso en caso de que tenga
                if($pedidoCurso){
                    for ($i=0; $i < count($pedidoCurso->detalles); $i++) { 
                        $pedidoCurso->detalles[$i]->tipo->stock = $pedidoCurso->detalles[$i]->tipo->stock + $pedidoCurso->detalles[$i]->cantidad;
                        $pedidoCurso->detalles[$i]->tipo->save();
                        $pedidoCurso->detalles[$i]->delete();
                    }
                    $pedidoCurso->delete();
                }


                $message = 'Tu pedido ha sido cancelado correctamente';

                $this->messageTextToCliente($cliente,$message);
                
                return 1;

            }

            /*Si quiere confirmar el pedido*/
            if(
                $obj->confirmar == 1
            ){

                if($pedidoCurso && count($pedidoCurso->detalles)>0){

                    //quitar el flujo
                    DB::table('bot_clientes')
                        ->where('id', $cliente->id)
                        ->update([
                            'flow_id' => null,
                        ]);

                    $salto = '
';

                    $subtotal = 0;
                    $envio = $pedidoCurso->envio;
                    $total = 0;
                    $pedido_detalle = '*Cant Producto Color Talla*'.$salto;
                    for ($i=0; $i < count($pedidoCurso->detalles); $i++) {

                        $pedido_detalle = $pedido_detalle.$salto.'*'.$pedidoCurso->detalles[$i]->cantidad.'* '.$pedidoCurso->detalles[$i]->producto->nombre.' '.$pedidoCurso->detalles[$i]->color->nombre.' '.$pedidoCurso->detalles[$i]->tipo->nombre;

                        $subtotal = $subtotal + ($pedidoCurso->detalles[$i]->cantidad*$pedidoCurso->detalles[$i]->precio_unitario);
                    }

                    $total = $subtotal + $envio;

                    $pedidoCurso->subtotal = $subtotal;
                    $pedidoCurso->envio = $envio;
                    $pedidoCurso->total = $total;

                    //pasar a confirmado
                    $pedidoCurso->status = 1;
                    $pedidoCurso->save();

                    $envio_text = '**';
                    if($envio > 0){
                        $envio_text = $envio;
                    }

                    $message = '✅ *Pedido confirmado:*'.$salto.$salto.$pedido_detalle.$salto.$salto.'Subtotal *'.$subtotal.'* 💲'.$salto.'Envío *'.$envio_text.'* 🚚'.$salto.'Total *'.$total.'* 💰';
                
                    //mensaje de texto con el detalle
                    $this->messageTextToCliente($cliente,$message);

                    $message = 'Estamos generando tu pedido. Por favor, espera un momento...';
                    $this->messageTextToCliente($cliente,$message);

                    $document = $this->pedidoPdf($pedidoCurso->id,'pedidoA');

                    $pedidoCurso->pdf = $document;
                    $pedidoCurso->save();

                    $this->_messageDocument($cliente->bot_id,$cliente->telefono,$document,'pedido');

                    $this->storeMsgChat($cliente->bot_id,$cliente->id,null,$document,0); //bot

                    $image = $this->pdfToImagen($document,'pedidos');

                    $pedidoCurso->imagen = $image;
                    $pedidoCurso->save();

                    $this->_messageImage($cliente->bot_id,$cliente->telefono,$image);

                    $this->storeMsgChat($cliente->bot_id,$cliente->id,null,$image,0); //bot

                    return 1;
                                        
                }else{

                    //elimino el pedido en curso en caso de que tenga
                    if($pedidoCurso){
                        for ($i=0; $i < count($pedidoCurso->detalles); $i++) { 
                            $pedidoCurso->detalles[$i]->tipo->stock = $pedidoCurso->detalles[$i]->tipo->stock + $pedidoCurso->detalles[$i]->cantidad;
                            $pedidoCurso->detalles[$i]->tipo->save();
                            $pedidoCurso->detalles[$i]->delete();
                        }
                        $pedidoCurso->delete();
                    }

                    $orden_count = Pedido::
                        where('cliente_id',$cliente->id)
                        ->count();

                    $costo_envio = 0;
                    if($cliente->costo_envio > 0){
                        $costo_envio = $cliente->costo_envio;
                    }

                    //crear un pedido nuevo en curso
                    $nuevoObj=Pedido::create([
                        'bot_id'=>$cliente->bot_id,
                        'cliente_id'=>$cliente->id,
                        'status'=>0,
                        'subtotal'=>0,
                        'envio'=>$costo_envio,
                        'total'=>0,
                        'orden'=>$orden_count+1,
                    ]);

                    $message = 'Tu pedido no tiene productos. Por favor, proporciona los productos que deseas solicitar en el siguiente formato:

Ejemplo:
🛍️ Producto: Camiseta
🎨 Color: Rojo
👕 Talla: XL
🔢 Cantidad: 3

Puedes escribir uno o muchos productos en un pedido. 🖊️

*Espero tus respuestas para crear el pedido correctamente.*';

                    $this->messageTextToCliente($cliente,$message);
                
                    return 1;
                }  

            }

            /*Si quiere ver el listado de pedidos*/
            if(
                $obj->ver_pedidos == 1
            ){

                $this->flowListaPedidos($cliente);
                
                return 1;

            }

            /*Si quiere ver el listado de productos*/
            if(
                $obj->ver_productos == 1
            ){

                $this->flowProductos($cliente);
                
                return 1;

            }

            //si no tiene pedido en curso, creo uno para las validaciones
            if(!$pedidoCurso){

                $orden_count = Pedido::
                    where('cliente_id',$cliente->id)
                    ->count();

                $costo_envio = 0;
                if($cliente->costo_envio > 0){
                    $costo_envio = $cliente->costo_envio;
                }

                $pedidoCurso=Pedido::create([
                    'bot_id'=>$cliente->bot_id,
                    'cliente_id'=>$cliente->id,
                    'status'=>0,
                    'subtotal'=>0,
                    'envio'=>$costo_envio,
                    'total'=>0,
                    'orden'=>$orden_count+1,
                ]);

                $pedidoCurso = Pedido::
                    where('cliente_id',$cliente->id)
                    ->where('status', 0)
                    ->with('detalles', 'detalles.producto', 'detalles.color', 'detalles.tipo')
                    ->first();
            }

            

            //validar formato de entrada
            for ($i=0; $i < count($obj->pedido); $i++) { 
                if($obj->pedido[$i]->producto == "" ||
                    $obj->pedido[$i]->color == "" ||
                    $obj->pedido[$i]->talla == ""
                ){

                    $message = 'Por favor, proporciona los productos que deseas solicitar en el siguiente formato:

Ejemplo:
🛍️ Producto: Camiseta
🎨 Color: Rojo
👕 Talla: XL
🔢 Cantidad: 3

Puedes escribir uno o muchos productos en un pedido. 🖊️

*Espero tus respuestas para crear el pedido correctamente.*';

                    $this->messageTextToCliente($cliente,$message);
                    
                    return 1;

                }

                if(
                    $obj->pedido[$i]->cantidad == "" /*||
                    $obj->pedido[$i]->cantidad <= 0*/
                ){

                    $message = 'La cantidad de producto a solicitar debe ser mayor a cero.';

                    $this->messageTextToCliente($cliente,$message);
                    
                    return 1;

                }
            }

            //Validar existencias
            for ($i=0; $i < count($obj->pedido); $i++) {

                $message = 'El producto:

🛍️ Producto: '.$obj->pedido[$i]->producto.'
🎨 Color: '.$obj->pedido[$i]->color.'
👕 Talla: '.$obj->pedido[$i]->talla.'
🔢 Cantidad: '.$obj->pedido[$i]->cantidad.'

No está disponible en estos momentos.';

                $producto = Producto::
                    whereNull('eliminado')
                    ->where('nombre',$obj->pedido[$i]->producto)
                    ->where('status', 1)
                    ->first();

                if(!$producto){
                    
                    $this->messageTextToCliente($cliente,$message);
                    return 1;
                }

                $color = Color::
                    whereNull('eliminado')
                    ->where('nombre',$obj->pedido[$i]->color)
                    ->where('status', 1)
                    ->where('producto_id', $producto->id)
                    ->first();

                if(!$color){
                    $this->messageTextToCliente($cliente,$message);  
                    return 1;
                }

                $tipo = Tipo::
                    whereNull('eliminado')
                    ->where('nombre',$obj->pedido[$i]->talla)
                    ->where('status', 1)
                    ->where('color_id', $color->id)
                    ->first();

                if(!$tipo){
                    $this->messageTextToCliente($cliente,$message);
                    return 1;
                }

            }

            //validar insercion o edicion
            for ($i=0; $i < count($obj->pedido); $i++) {
                $esta = false;
                for ($j=0; $j < count($pedidoCurso->detalles); $j++) { 

                    if(
                        $obj->pedido[$i]->producto == strtoupper($pedidoCurso->detalles[$j]->producto->nombre) &&
                        $obj->pedido[$i]->color == strtoupper($pedidoCurso->detalles[$j]->color->nombre) &&
                        $obj->pedido[$i]->talla == strtoupper($pedidoCurso->detalles[$j]->tipo->nombre)
                    ){
                        $esta = true;

                        //validar stock
                        $stock_actual = $pedidoCurso->detalles[$j]->tipo->stock + $pedidoCurso->detalles[$j]->cantidad;


                        if(
                            ($stock_actual <= 0 && $obj->pedido[$i]->cantidad > 0) ||
                            $stock_actual < $obj->pedido[$i]->cantidad
                        ){
                            $message = 'Solo hay '.$stock_actual.' unidades disponibles del producto:

🛍️ Producto: '.$obj->pedido[$i]->producto.'
🎨 Color: '.$obj->pedido[$i]->color.'
👕 Talla: '.$obj->pedido[$i]->talla;

                            $this->messageTextToCliente($cliente,$message);
                            
                            return 1;
                        }

                        //modificar stock
                        $stock_final = $stock_actual - $obj->pedido[$i]->cantidad;
                        $pedidoCurso->detalles[$j]->tipo->stock = $stock_final;
                        $pedidoCurso->detalles[$j]->tipo->save();

                        //modificar cantidad solicitada
                        $pedidoCurso->detalles[$j]->cantidad = $obj->pedido[$i]->cantidad;
                        $pedidoCurso->detalles[$j]->save();

                        if($obj->pedido[$i]->cantidad == 0){
                            $pedidoCurso->detalles[$j]->delete();
                        }

                    }
                }
                if(!$esta){

                    if(
                        $obj->pedido[$i]->cantidad <= 0
                    ){

                        $message = 'La cantidad de producto a solicitar debe ser mayor a cero.';

                        $this->messageTextToCliente($cliente,$message);
                        
                        return 1;

                    }

                    //validar stock
                    $producto = Producto::
                        whereNull('eliminado')
                        ->where('nombre',$obj->pedido[$i]->producto)
                        ->where('status', 1)
                        ->first();

                    $color = Color::
                        whereNull('eliminado')
                        ->where('nombre',$obj->pedido[$i]->color)
                        ->where('status', 1)
                        ->where('producto_id', $producto->id)
                        ->first();

                    $tipo = Tipo::
                        whereNull('eliminado')
                        ->where('nombre',$obj->pedido[$i]->talla)
                        ->where('status', 1)
                        ->where('color_id', $color->id)
                        ->first();


                    $stock_actual = $tipo->stock;

                    if($stock_actual <= 0 ||
                        $stock_actual < $obj->pedido[$i]->cantidad
                    ){
                        $message = 'Solo hay '.$stock_actual.' unidades disponibles del producto:

🛍️ Producto: '.$obj->pedido[$i]->producto.'
🎨 Color: '.$obj->pedido[$i]->color.'
👕 Talla: '.$obj->pedido[$i]->talla;

                        $this->messageTextToCliente($cliente,$message);
                        
                        return 1;
                    }

                    //modificar stock
                    $stock_final = $stock_actual - $obj->pedido[$i]->cantidad;
                    $tipo->stock = $stock_final;
                    $tipo->save();

                    //agregar producto al pedido
                    $nuevoDetalle=PedidoDetalle::create([
                        'pedido_id' => $pedidoCurso->id,
                        'producto_id' => $producto->id,
                        'color_id' => $color->id,
                        'tipo_id' => $tipo->id,
                        'cantidad' => $obj->pedido[$i]->cantidad,
                        'precio_unitario' => $tipo->precio,
                    ]);
                }

            }

            //validar eliminacion
            $pedidoCurso = Pedido::
                where('cliente_id',$cliente->id)
                ->where('status', 0)
                ->with('detalles', 'detalles.producto', 'detalles.color', 'detalles.tipo')
                ->first();
            for ($i=0; $i < count($pedidoCurso->detalles); $i++) {
                $esta = false;
                for ($j=0; $j < count($obj->pedido); $j++) {
                    if(
                        strtoupper($pedidoCurso->detalles[$i]->producto->nombre) == $obj->pedido[$j]->producto  &&
                        strtoupper($pedidoCurso->detalles[$i]->color->nombre) == $obj->pedido[$j]->color  &&
                        strtoupper($pedidoCurso->detalles[$i]->tipo->nombre) == $obj->pedido[$j]->talla 
                    ){
                        $esta = true;
                    }
                }
                if(!$esta){
                    //modificar stock
                    $stock_final = $pedidoCurso->detalles[$i]->tipo->stock + $pedidoCurso->detalles[$i]->cantidad;
                    $pedidoCurso->detalles[$i]->tipo->stock = $stock_final;
                    $pedidoCurso->detalles[$i]->tipo->save();
                    //eliminar detalle
                    $pedidoCurso->detalles[$i]->delete();
                }
            }

            //Actualizar datos del pedido
            $pedidoCurso->envio = $obj->envio;
            $pedidoCurso->save();

            $pedidoCurso = Pedido::
                where('cliente_id',$cliente->id)
                ->where('status', 0)
                ->with('detalles', 'detalles.producto', 'detalles.color', 'detalles.tipo')
                ->first();

            $salto = '
';

            $message = 'Tu pedido no tiene productos. Por favor, proporciona los productos que deseas solicitar en el siguiente formato:

Ejemplo:
🛍️ Producto: Camiseta
🎨 Color: Rojo
👕 Talla: XL
🔢 Cantidad: 3

Puedes escribir uno o muchos productos en un pedido. 🖊️

*Espero tus respuestas para crear el pedido correctamente.*';

            if($pedidoCurso && count($pedidoCurso->detalles)>0){
                $subtotal = 0;
                $envio = $pedidoCurso->envio;
                $total = 0;
                $pedido_detalle = '*Cant Producto Color Talla*'.$salto;
                for ($i=0; $i < count($pedidoCurso->detalles); $i++) { 

                    $pedido_detalle = $pedido_detalle.$salto.'*'.$pedidoCurso->detalles[$i]->cantidad.'* '.$pedidoCurso->detalles[$i]->producto->nombre.' '.$pedidoCurso->detalles[$i]->color->nombre.' '.$pedidoCurso->detalles[$i]->tipo->nombre;

                    $subtotal = $subtotal + ($pedidoCurso->detalles[$i]->cantidad*$pedidoCurso->detalles[$i]->precio_unitario);
                }

                $total = $subtotal + $envio;

                $pedidoCurso->subtotal = $subtotal;
                $pedidoCurso->envio = $envio;
                $pedidoCurso->total = $total;
                $pedidoCurso->save();

                $envio_text = '**';
                if($envio > 0){
                    $envio_text = $envio;
                }

                $message = '*Detalles:*'.$salto.$salto.
                $pedido_detalle.$salto.$salto.
                'Subtotal *'.$subtotal.'* 💲'.$salto.
                'Envío *'.$envio_text.'* 🚚'.$salto.
                'Total *'.$total.'* 💰'.$salto.$salto.
                '*Escribe la Opción:*'.$salto.$salto.
                'Cancelar pedido'.$salto.
                'Confirmar pedido'.$salto.$salto.
                'Ó'.$salto.$salto.
                'Envíame más productos para completar tu orden. Ejemplo👇🏻:'.$salto.$salto.
                'Agrega este otro producto'.$salto.
                'Producto: Camiseta'.$salto.
                'Color: Rojo'.$salto.
                'Talla: XL'.$salto.
                'Cantidad: 3';
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

    public function flowPedidoB($cliente)
    {

        $bot_config = BotConfig::
            select('id','palabra_clave','flow_id','prompt2')
            ->where('flow_id', 2)
            ->get();

        $prompt = $bot_config[0]->prompt2;
        
        $text_mensajes = '';
        for ($i=0; $i < count($cliente->mensajes); $i++) { 
            if($i == 0){
                $text_mensajes = $cliente->mensajes[$i];
            }else{
                $text_mensajes = $text_mensajes.', '.$cliente->mensajes[$i];
            }
        }
        
        $prompt = str_replace("{{mensaje}}", $text_mensajes, $prompt);
        $prompt = $this->contextoFecha($prompt);

        $pedidoCurso = Pedido::
            where('cliente_id',$cliente->id)
            ->where('status', 0)
            ->with('detalles', 'detalles.producto', 'detalles.color', 'detalles.tipo')
            ->first();

        if(!$pedidoCurso){
            $prompt = str_replace("{{detalles}}", "{}", $prompt);
        }else{

            $detalles = [];
            for ($i=0; $i < count($pedidoCurso->detalles); $i++) { 
                $resul = (object) [
                    'producto' => $pedidoCurso->detalles[$i]->producto->nombre,
                    'cantidad' => $pedidoCurso->detalles[$i]->cantidad,
                ];
                array_push($detalles,$resul);
            }

            $pedido = [
                "envio" => $pedidoCurso->envio,
                "pedido" => $detalles,
            ];

            $pedidoString = json_encode($pedido);
            
            $prompt = str_replace("{{detalles}}", $pedidoString, $prompt);
        } 

        $respB = $this->_davinciRespuestaPrompt($prompt);
        if ($respB['status'] == 200) {

            $cadena = $respB['text'];

            $log = [];
            array_push($log,$text_mensajes);
            array_push($log,$cadena);

            file_put_contents('webhook_log_pedidos.txt', print_r($log, true), FILE_APPEND);

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

            //fusionar los items duplicados
            $array_pedido = [];
            for ($i=0; $i < count($obj->pedido); $i++) {

                $esta = false;
                $obj->pedido[$i]->producto = strtoupper($obj->pedido[$i]->producto);

                for ($j=0; $j < count($array_pedido); $j++) { 
                    if(
                        $obj->pedido[$i]->producto == $array_pedido[$j]->producto
                    ){
                        $esta = true;
                        $array_pedido[$j]->cantidad = $array_pedido[$j]->cantidad + $obj->pedido[$i]->cantidad;
                    }
                }
                if(!$esta){
                    array_push($array_pedido,$obj->pedido[$i]);
                }

            }
            $obj->pedido = $array_pedido;

            $pedidoCurso = Pedido::
                where('cliente_id',$cliente->id)
                ->where('status', 0)
                ->with('detalles', 'detalles.producto', 'detalles.color', 'detalles.tipo')
                ->first();

            /*Si no hay informacion referente al pedido,
            responder con el prompt general*/
            if(
                (
                    count($obj->pedido) == 0 ||
                    (
                        count($obj->pedido) == 1 &&
                        $obj->pedido[0]->producto == ""
                    ) 
                 ) &&

                $obj->nuevo == 0 &&
                $obj->confirmar == 0 &&
                $obj->cancelar == 0 &&
                $obj->ver_pedidos == 0 &&
                $obj->ver_productos == 0
            ){

                //elimino pedido curso en caso de que tenga
                if($pedidoCurso){
                    for ($i=0; $i < count($pedidoCurso->detalles); $i++) { 
                        if($cliente->flag_stock == 1){
                            $pedidoCurso->detalles[$i]->tipo->stock = $pedidoCurso->detalles[$i]->tipo->stock + $pedidoCurso->detalles[$i]->cantidad;
                            $pedidoCurso->detalles[$i]->tipo->save();

                            $pedidoCurso->detalles[$i]->producto->stock = $pedidoCurso->detalles[$i]->tipo->stock;
                            $pedidoCurso->detalles[$i]->producto->save();

                        }
                        $pedidoCurso->detalles[$i]->delete();
                    }
                    $pedidoCurso->delete();
                }

                //respondo con flujo no aplicable
                $this->flowNoAplicable($cliente);
                return 1;

            }

            /*Si quiere iniciar el proceso*/
            if(
                (
                    count($obj->pedido) == 0 ||
                    (
                        count($obj->pedido) == 1 &&
                        $obj->pedido[0]->producto == ""
                    ) 
                 ) &&

                $obj->nuevo == 1
            ){

                //elimino el pedido en curso en caso de que tenga
                if($pedidoCurso){
                    for ($i=0; $i < count($pedidoCurso->detalles); $i++) { 
                        if($cliente->flag_stock == 1){
                            $pedidoCurso->detalles[$i]->tipo->stock = $pedidoCurso->detalles[$i]->tipo->stock + $pedidoCurso->detalles[$i]->cantidad;
                            $pedidoCurso->detalles[$i]->tipo->save();

                            $pedidoCurso->detalles[$i]->producto->stock = $pedidoCurso->detalles[$i]->tipo->stock;
                            $pedidoCurso->detalles[$i]->producto->save();
                        }
                        $pedidoCurso->detalles[$i]->delete();
                    }
                    $pedidoCurso->delete();
                }

                $orden_count = Pedido::
                    where('cliente_id',$cliente->id)
                    ->count();

                $costo_envio = 0;
                if($cliente->costo_envio > 0){
                    $costo_envio = $cliente->costo_envio;
                }

                //crear un pedido nuevo en curso
                $nuevoObj=Pedido::create([
                    'bot_id'=>$cliente->bot_id,
                    'cliente_id'=>$cliente->id,
                    'status'=>0,
                    'subtotal'=>0,
                    'envio'=>$costo_envio,
                    'total'=>0,
                    'orden'=>$orden_count+1,
                ]);

                $message = '¡Hola! 👋 Estoy aquí para ayudarte a crear un pedido. Por favor, proporciona los productos que deseas solicitar en el siguiente formato:

Ejemplo:
🛍️ Producto: Camiseta
🔢 Cantidad: 3

Puedes escribir uno o muchos productos en un pedido. 🖊️

*Espero tus respuestas para crear el pedido correctamente.*';

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

                //elimino el pedido en curso en caso de que tenga
                if($pedidoCurso){
                    for ($i=0; $i < count($pedidoCurso->detalles); $i++) { 
                        if($cliente->flag_stock == 1){
                            $pedidoCurso->detalles[$i]->tipo->stock = $pedidoCurso->detalles[$i]->tipo->stock + $pedidoCurso->detalles[$i]->cantidad;
                            $pedidoCurso->detalles[$i]->tipo->save();

                            $pedidoCurso->detalles[$i]->producto->stock = $pedidoCurso->detalles[$i]->tipo->stock;
                            $pedidoCurso->detalles[$i]->producto->save();
                        }
                        $pedidoCurso->detalles[$i]->delete();
                    }
                    $pedidoCurso->delete();
                }


                $message = 'Tu pedido ha sido cancelado correctamente';

                $this->messageTextToCliente($cliente,$message);
                
                return 1;

            }

            /*Si quiere confirmar el pedido*/
            if(
                $obj->confirmar == 1
            ){

                if($pedidoCurso && count($pedidoCurso->detalles)>0){

                    //quitar el flujo
                    DB::table('bot_clientes')
                        ->where('id', $cliente->id)
                        ->update([
                            'flow_id' => null,
                        ]);

                    $salto = '
';

                    $subtotal = 0;
                    $envio = $pedidoCurso->envio;
                    $total = 0;
                    $pedido_detalle = '*Cant Producto*'.$salto;
                    for ($i=0; $i < count($pedidoCurso->detalles); $i++) {

                        $pedido_detalle = $pedido_detalle.$salto.'*'.$pedidoCurso->detalles[$i]->cantidad.'* '.$pedidoCurso->detalles[$i]->producto->nombre;

                        $subtotal = $subtotal + ($pedidoCurso->detalles[$i]->cantidad*$pedidoCurso->detalles[$i]->precio_unitario);
                    }

                    $total = $subtotal + $envio;

                    $pedidoCurso->subtotal = $subtotal;
                    $pedidoCurso->envio = $envio;
                    $pedidoCurso->total = $total;

                    //pasar a confirmado
                    $pedidoCurso->status = 1;
                    $pedidoCurso->save();

                    $envio_text = '**';
                    if($envio > 0){
                        $envio_text = $envio;
                    }

                    $message = '✅ *Pedido confirmado:*'.$salto.$salto.$pedido_detalle.$salto.$salto.'Subtotal *'.$subtotal.'* 💲'.$salto.'Envío *'.$envio_text.'* 🚚'.$salto.'Total *'.$total.'* 💰';
                
                    //mensaje de texto con el detalle
                    $this->messageTextToCliente($cliente,$message);

                    $message = 'Estamos generando tu pedido. Por favor, espera un momento...';
                    $this->messageTextToCliente($cliente,$message);

                    $document = $this->pedidoPdf($pedidoCurso->id,'pedidoB');

                    $pedidoCurso->pdf = $document;
                    $pedidoCurso->save();

                    $this->_messageDocument($cliente->bot_id,$cliente->telefono,$document,'pedido');

                    $this->storeMsgChat($cliente->bot_id,$cliente->id,null,$document,0); //bot

                    $image = $this->pdfToImagen($document,'pedidos');

                    $pedidoCurso->imagen = $image;
                    $pedidoCurso->save();

                    $this->_messageImage($cliente->bot_id,$cliente->telefono,$image);

                    $this->storeMsgChat($cliente->bot_id,$cliente->id,null,$image,0); //bot

                    return 1;
                                        
                }else{

                    //elimino el pedido en curso en caso de que tenga
                    if($pedidoCurso){
                        for ($i=0; $i < count($pedidoCurso->detalles); $i++) { 
                            if($cliente->flag_stock == 1){
                                $pedidoCurso->detalles[$i]->tipo->stock = $pedidoCurso->detalles[$i]->tipo->stock + $pedidoCurso->detalles[$i]->cantidad;
                                $pedidoCurso->detalles[$i]->tipo->save();

                                $pedidoCurso->detalles[$i]->producto->stock = $pedidoCurso->detalles[$i]->tipo->stock;
                                $pedidoCurso->detalles[$i]->producto->save();
                            }
                            $pedidoCurso->detalles[$i]->delete();
                        }
                        $pedidoCurso->delete();
                    }

                    $orden_count = Pedido::
                        where('cliente_id',$cliente->id)
                        ->count();

                    $costo_envio = 0;
                    if($cliente->costo_envio > 0){
                        $costo_envio = $cliente->costo_envio;
                    }

                    //crear un pedido nuevo en curso
                    $nuevoObj=Pedido::create([
                        'bot_id'=>$cliente->bot_id,
                        'cliente_id'=>$cliente->id,
                        'status'=>0,
                        'subtotal'=>0,
                        'envio'=>$costo_envio,
                        'total'=>0,
                        'orden'=>$orden_count+1,
                    ]);

                    $message = 'Tu pedido no tiene productos. Por favor, proporciona los productos que deseas solicitar en el siguiente formato:

Ejemplo:
🛍️ Producto: Camiseta
🔢 Cantidad: 3

Puedes escribir uno o muchos productos en un pedido. 🖊️

*Espero tus respuestas para crear el pedido correctamente.*';

                    $this->messageTextToCliente($cliente,$message);
                
                    return 1;
                }  

            }

            /*Si quiere ver el listado de pedidos*/
            if(
                $obj->ver_pedidos == 1
            ){

                $this->flowListaPedidos($cliente);
                
                return 1;

            }

            /*Si quiere ver el listado de productos*/
            if(
                $obj->ver_productos == 1
            ){

                $this->flowProductos($cliente);
                
                return 1;

            }

            //si no tiene pedido en curso, creo uno para las validaciones
            if(!$pedidoCurso){

                $orden_count = Pedido::
                    where('cliente_id',$cliente->id)
                    ->count();

                $costo_envio = 0;
                if($cliente->costo_envio > 0){
                    $costo_envio = $cliente->costo_envio;
                }

                $pedidoCurso=Pedido::create([
                    'bot_id'=>$cliente->bot_id,
                    'cliente_id'=>$cliente->id,
                    'status'=>0,
                    'subtotal'=>0,
                    'envio'=>$costo_envio,
                    'total'=>0,
                    'orden'=>$orden_count+1,
                ]);

                $pedidoCurso = Pedido::
                    where('cliente_id',$cliente->id)
                    ->where('status', 0)
                    ->with('detalles', 'detalles.producto', 'detalles.color', 'detalles.tipo')
                    ->first();
            }

            

            //validar formato de entrada
            for ($i=0; $i < count($obj->pedido); $i++) { 
                if($obj->pedido[$i]->producto == ""
                ){

                    $message = 'Por favor, proporciona los productos que deseas solicitar en el siguiente formato:

Ejemplo:
🛍️ Producto: Camiseta
🔢 Cantidad: 3

Puedes escribir uno o muchos productos en un pedido. 🖊️

*Espero tus respuestas para crear el pedido correctamente.*';

                    $this->messageTextToCliente($cliente,$message);
                    
                    return 1;

                }

                if(
                    $obj->pedido[$i]->cantidad == "" /*||
                    $obj->pedido[$i]->cantidad <= 0*/
                ){

                    $message = 'La cantidad de producto a solicitar debe ser mayor a cero.';

                    $this->messageTextToCliente($cliente,$message);
                    
                    return 1;

                }
            }

            //Validar existencias
            for ($i=0; $i < count($obj->pedido); $i++) {

                $message = 'El producto:

🛍️ Producto: '.$obj->pedido[$i]->producto.'
🔢 Cantidad: '.$obj->pedido[$i]->cantidad.'

No está disponible en estos momentos.';

                $producto = Producto::
                    whereNull('eliminado')
                    ->where('nombre',$obj->pedido[$i]->producto)
                    ->where('status', 1)
                    ->first();

                if(!$producto){
                    
                    $this->messageTextToCliente($cliente,$message);
                    return 1;
                }

                $color = Color::
                    whereNull('eliminado')
                    //->where('nombre',$obj->pedido[$i]->color)
                    ->where('status', 1)
                    ->where('producto_id', $producto->id)
                    ->first();

                if(!$color){
                    $this->messageTextToCliente($cliente,$message);  
                    return 1;
                }

                $tipo = Tipo::
                    whereNull('eliminado')
                    //->where('nombre',$obj->pedido[$i]->talla)
                    ->where('status', 1)
                    ->where('color_id', $color->id)
                    ->first();

                if(!$tipo){
                    $this->messageTextToCliente($cliente,$message);
                    return 1;
                }

            }

            //validar insercion o edicion
            for ($i=0; $i < count($obj->pedido); $i++) {
                $esta = false;
                for ($j=0; $j < count($pedidoCurso->detalles); $j++) { 

                    if(
                        $obj->pedido[$i]->producto == strtoupper($pedidoCurso->detalles[$j]->producto->nombre)
                    ){
                        $esta = true;

                        if($cliente->flag_stock == 1){

                            //validar stock
                            $stock_actual = $pedidoCurso->detalles[$j]->tipo->stock + $pedidoCurso->detalles[$j]->cantidad;

                            if(
                                ($stock_actual <= 0 && $obj->pedido[$i]->cantidad > 0) ||
                                $stock_actual < $obj->pedido[$i]->cantidad
                            ){
                                $message = 'Solo hay '.$stock_actual.' unidades disponibles del producto:

🛍️ Producto: '.$obj->pedido[$i]->producto;

                                $this->messageTextToCliente($cliente,$message);
                                
                                return 1;
                            }

                            //modificar stock
                            $stock_final = $stock_actual - $obj->pedido[$i]->cantidad;
                            $pedidoCurso->detalles[$j]->tipo->stock = $stock_final;
                            $pedidoCurso->detalles[$j]->tipo->save();

                            $pedidoCurso->detalles[$i]->producto->stock = $pedidoCurso->detalles[$i]->tipo->stock;
                            $pedidoCurso->detalles[$i]->producto->save();

                        }

                        //modificar cantidad solicitada
                        $pedidoCurso->detalles[$j]->cantidad = $obj->pedido[$i]->cantidad;
                        $pedidoCurso->detalles[$j]->save();

                        if($obj->pedido[$i]->cantidad == 0){
                            $pedidoCurso->detalles[$j]->delete();
                        }

                    }
                }
                if(!$esta){

                    if(
                        $obj->pedido[$i]->cantidad <= 0
                    ){

                        $message = 'La cantidad de producto a solicitar debe ser mayor a cero.';

                        $this->messageTextToCliente($cliente,$message);
                        
                        return 1;

                    }

                    //validar stock
                    $producto = Producto::
                        whereNull('eliminado')
                        ->where('nombre',$obj->pedido[$i]->producto)
                        ->where('status', 1)
                        ->first();

                    $color = Color::
                        whereNull('eliminado')
                        //->where('nombre',$obj->pedido[$i]->color)
                        ->where('status', 1)
                        ->where('producto_id', $producto->id)
                        ->first();

                    $tipo = Tipo::
                        whereNull('eliminado')
                        //->where('nombre',$obj->pedido[$i]->talla)
                        ->where('status', 1)
                        ->where('color_id', $color->id)
                        ->first();

                    if($cliente->flag_stock == 1){

                        $stock_actual = $tipo->stock;

                        if($stock_actual <= 0 ||
                            $stock_actual < $obj->pedido[$i]->cantidad
                        ){
                            $message = 'Solo hay '.$stock_actual.' unidades disponibles del producto:

🛍️ Producto: '.$obj->pedido[$i]->producto;

                            $this->messageTextToCliente($cliente,$message);
                            
                            return 1;
                        }

                        //modificar stock
                        $stock_final = $stock_actual - $obj->pedido[$i]->cantidad;
                        $tipo->stock = $stock_final;
                        $tipo->save();

                        $producto->stock = $tipo->stock;
                        $producto->save();

                    }

                    //agregar producto al pedido
                    $nuevoDetalle=PedidoDetalle::create([
                        'pedido_id' => $pedidoCurso->id,
                        'producto_id' => $producto->id,
                        'color_id' => $color->id,
                        'tipo_id' => $tipo->id,
                        'cantidad' => $obj->pedido[$i]->cantidad,
                        'precio_unitario' => $tipo->precio,
                    ]);
                }

            }

            //validar eliminacion
            $pedidoCurso = Pedido::
                where('cliente_id',$cliente->id)
                ->where('status', 0)
                ->with('detalles', 'detalles.producto', 'detalles.color', 'detalles.tipo')
                ->first();
            for ($i=0; $i < count($pedidoCurso->detalles); $i++) {
                $esta = false;
                for ($j=0; $j < count($obj->pedido); $j++) {
                    if(
                        strtoupper($pedidoCurso->detalles[$i]->producto->nombre) == $obj->pedido[$j]->producto
                    ){
                        $esta = true;
                    }
                }
                if(!$esta){
                    if($cliente->flag_stock == 1){
                        //modificar stock
                        $stock_final = $pedidoCurso->detalles[$i]->tipo->stock + $pedidoCurso->detalles[$i]->cantidad;
                        $pedidoCurso->detalles[$i]->tipo->stock = $stock_final;
                        $pedidoCurso->detalles[$i]->tipo->save();

                        $pedidoCurso->detalles[$i]->producto->stock = $pedidoCurso->detalles[$i]->tipo->stock;
                        $pedidoCurso->detalles[$i]->producto->save();
                    }
                    //eliminar detalle
                    $pedidoCurso->detalles[$i]->delete();
                }
            }

            //Actualizar datos del pedido
            $pedidoCurso->envio = $obj->envio;
            $pedidoCurso->save();

            $pedidoCurso = Pedido::
                where('cliente_id',$cliente->id)
                ->where('status', 0)
                ->with('detalles', 'detalles.producto', 'detalles.color', 'detalles.tipo')
                ->first();

            $salto = '
';

            $message = 'Tu pedido no tiene productos. Por favor, proporciona los productos que deseas solicitar en el siguiente formato:

Ejemplo:
🛍️ Producto: Camiseta
🔢 Cantidad: 3

Puedes escribir uno o muchos productos en un pedido. 🖊️

*Espero tus respuestas para crear el pedido correctamente.*';

            if($pedidoCurso && count($pedidoCurso->detalles)>0){
                $subtotal = 0;
                $envio = $pedidoCurso->envio;
                $total = 0;
                $pedido_detalle = '*Cant Producto*'.$salto;
                for ($i=0; $i < count($pedidoCurso->detalles); $i++) { 

                    $pedido_detalle = $pedido_detalle.$salto.'*'.$pedidoCurso->detalles[$i]->cantidad.'* '.$pedidoCurso->detalles[$i]->producto->nombre;

                    $subtotal = $subtotal + ($pedidoCurso->detalles[$i]->cantidad*$pedidoCurso->detalles[$i]->precio_unitario);
                }

                $total = $subtotal + $envio;

                $pedidoCurso->subtotal = $subtotal;
                $pedidoCurso->envio = $envio;
                $pedidoCurso->total = $total;
                $pedidoCurso->save();

                $envio_text = '**';
                if($envio > 0){
                    $envio_text = $envio;
                }

                $message = '*Detalles:*'.$salto.$salto.
                $pedido_detalle.$salto.$salto.
                'Subtotal *'.$subtotal.'* 💲'.$salto.
                'Envío *'.$envio_text.'* 🚚'.$salto.
                'Total *'.$total.'* 💰'.$salto.$salto.
                '*Escribe la Opción:*'.$salto.$salto.
                'Cancelar pedido'.$salto.
                'Confirmar pedido'.$salto.$salto.
                'Ó'.$salto.$salto.
                'Envíame más productos para completar tu orden. Ejemplo👇🏻:'.$salto.$salto.
                'Agrega este otro producto'.$salto.
                'Producto: Camiseta'.$salto.
                'Cantidad: 3';
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

    public function contextoFecha($prompt){

        $date_hoy = Carbon::now();
        $dia = $date_hoy->dayOfWeek;
        $mes = $date_hoy->month;

        $fecha_hoy = $date_hoy->format('d/m/Y');
        $dia_actual = '';
        $mes_actual = '';

        $date_manana = Carbon::tomorrow();
        $fecha_manana = $date_manana->format('d/m/Y');

        $date_pasado_manana = Carbon::now()->addDays(2);
        $fecha_pasado_manana = $date_pasado_manana->format('d/m/Y');

        if($dia == 1){ //lunes
            $dia_actual = 'Lunes';

            $prox_fechas = 'Próximo Martes: {{prox_martes}}
Próximo Miércoles: {{prox_miercoles}}
Próximo Jueves: {{prox_jueves}}
Próximo Viernes: {{prox_viernes}}
Próximo Sabado: {{prox_sabado}}
Próximo Domingo: {{prox_domingo}}
Próximo Lunes: {{prox_lunes}}';

        }else if($dia == 2){ //martes
            $dia_actual = 'Martes';

            $prox_fechas = 'Próximo Miércoles: {{prox_miercoles}}
Próximo Jueves: {{prox_jueves}}
Próximo Viernes: {{prox_viernes}}
Próximo Sabado: {{prox_sabado}}
Próximo Domingo: {{prox_domingo}}
Próximo Lunes: {{prox_lunes}}
Próximo Martes: {{prox_martes}}';

        }else if($dia == 3){ //miercoles
            $dia_actual = 'Miércoles';

            $prox_fechas = 'Próximo Jueves: {{prox_jueves}}
Próximo Viernes: {{prox_viernes}}
Próximo Sabado: {{prox_sabado}}
Próximo Domingo: {{prox_domingo}}
Próximo Lunes: {{prox_lunes}}
Próximo Martes: {{prox_martes}}
Próximo Miércoles: {{prox_miercoles}}';

        }else if($dia == 4){ //jueves
            $dia_actual = 'Jueves';

            $prox_fechas = 'Próximo Viernes: {{prox_viernes}}
Próximo Sabado: {{prox_sabado}}
Próximo Domingo: {{prox_domingo}}
Próximo Lunes: {{prox_lunes}}
Próximo Martes: {{prox_martes}}
Próximo Miércoles: {{prox_miercoles}}
Próximo Jueves: {{prox_jueves}}';

        }else if($dia == 5){ //viernes
            $dia_actual = 'Viernes';

            $prox_fechas = 'Próximo Sabado: {{prox_sabado}}
Próximo Domingo: {{prox_domingo}}
Próximo Lunes: {{prox_lunes}}
Próximo Martes: {{prox_martes}}
Próximo Miércoles: {{prox_miercoles}}
Próximo Jueves: {{prox_jueves}}
Próximo Viernes: {{prox_viernes}}';

        }else if($dia == 6){ //sabado
            $dia_actual = 'Sábado';

            $prox_fechas = 'Próximo Domingo: {{prox_domingo}}
Próximo Lunes: {{prox_lunes}}
Próximo Martes: {{prox_martes}}
Próximo Miércoles: {{prox_miercoles}}
Próximo Jueves: {{prox_jueves}}
Próximo Viernes: {{prox_viernes}}
Próximo Sabado: {{prox_sabado}}';

        }else if($dia == 0){ //domingo
            $dia_actual = 'Domingo';

            $prox_fechas = 'Próximo Lunes: {{prox_lunes}}
Próximo Martes: {{prox_martes}}
Próximo Miércoles: {{prox_miercoles}}
Próximo Jueves: {{prox_jueves}}
Próximo Viernes: {{prox_viernes}}
Próximo Sabado: {{prox_sabado}}
Próximo Domingo: {{prox_domingo}}';
        }

        if ($mes == 1) { // Enero
            $mes_actual = 'Enero';
        } else if ($mes == 2) { // Febrero
            $mes_actual = 'Febrero';
        } else if ($mes == 3) { // Marzo
            $mes_actual = 'Marzo';
        } else if ($mes == 4) { // Abril
            $mes_actual = 'Abril';
        } else if ($mes == 5) { // Mayo
            $mes_actual = 'Mayo';
        } else if ($mes == 6) { // Junio
            $mes_actual = 'Junio';
        } else if ($mes == 7) { // Julio
            $mes_actual = 'Julio';
        } else if ($mes == 8) { // Agosto
            $mes_actual = 'Agosto';
        } else if ($mes == 9) { // Septiembre
            $mes_actual = 'Septiembre';
        } else if ($mes == 10) { // Octubre
            $mes_actual = 'Octubre';
        } else if ($mes == 11) { // Noviembre
            $mes_actual = 'Noviembre';
        } else if ($mes == 12) { // Diciembre
            $mes_actual = 'Diciembre';
        }

        $date1 = Carbon::now();
        $prox_lunes = $date1->next('Monday')->format('d/m/Y');
        $date2 = Carbon::now();
        $prox_martes = $date2->next('Tuesday')->format('d/m/Y');
        $date3 = Carbon::now();
        $prox_miercoles = $date3->next('Wednesday')->format('d/m/Y');
        $date4 = Carbon::now();
        $prox_jueves = $date4->next('Thursday')->format('d/m/Y');
        $date5 = Carbon::now();
        $prox_viernes = $date5->next('Friday')->format('d/m/Y');
        $date6 = Carbon::now();
        $prox_sabado = $date6->next('Saturday')->format('d/m/Y');
        $date7 = Carbon::now();
        $prox_domingo = $date7->next('Sunday')->format('d/m/Y');

        $prompt = str_replace("{{dia_actual}}", $dia_actual, $prompt);
        $prompt = str_replace("{{mes_actual}}", $mes_actual, $prompt);
        $prompt = str_replace("{{fecha_hoy}}", $fecha_hoy, $prompt);
        $prompt = str_replace("{{fecha_manana}}", $fecha_manana, $prompt);
        $prompt = str_replace("{{fecha_pasado_manana}}", $fecha_pasado_manana, $prompt);
        $prompt = str_replace("{{prox_fechas}}", $prox_fechas, $prompt);
        $prompt = str_replace("{{prox_lunes}}", $prox_lunes, $prompt);
        $prompt = str_replace("{{prox_martes}}", $prox_martes, $prompt);
        $prompt = str_replace("{{prox_miercoles}}", $prox_miercoles, $prompt);
        $prompt = str_replace("{{prox_jueves}}", $prox_jueves, $prompt);
        $prompt = str_replace("{{prox_viernes}}", $prox_viernes, $prompt);
        $prompt = str_replace("{{prox_sabado}}", $prox_sabado, $prompt);
        $prompt = str_replace("{{prox_domingo}}", $prox_domingo, $prompt);

        return $prompt;

    }

    public function messageInteractiveToCliente($cliente){

        $detalles_aux = [];
        $pedidoCurso = Pedido::
            where('cliente_id',$cliente->id)
            ->where('status', 0)
            ->with('detalles', 'detalles.producto', 'detalles.color', 'detalles.tipo')
            ->first();
        if($pedidoCurso){

            $rows = array();

            for ($i=0; $i < count($pedidoCurso->detalles); $i++) { 
                $resul = (object) [
                    'producto' => $pedidoCurso->detalles[$i]->producto->nombre,
                    'color' => $pedidoCurso->detalles[$i]->color->nombre,
                    'tipo' => $pedidoCurso->detalles[$i]->tipo->nombre,
                    'cantidad' => $pedidoCurso->detalles[$i]->cantidad,
                ];
                array_push($detalles_aux,$resul);

                $title = $pedidoCurso->detalles[$i]->producto->nombre.' - '.$pedidoCurso->detalles[$i]->cantidad;

                $description = $pedidoCurso->detalles[$i]->color->nombre.' '.$pedidoCurso->detalles[$i]->tipo->nombre;

                $row = array(
                    'id' => $pedidoCurso->detalles[$i]->producto->id,
                    'title' => $title,
                    'description' => $description
                );
                array_push($rows,$row);
            }
            $message = json_encode($detalles_aux);

            $body = array(
                'type' => 'list',
                'header' => array(
                    'type' => 'text',
                    'text' => 'Total 100 $'
                ),
                'body' => array('text' => 'Subotal 90 $'),
                'footer' => array('text' => 'Envío 10 $'),
                'action' => array(
                    'button' => 'DETALLES',
                    'sections' => array(
                        array(
                            'title' => 'PRODUCTOS',
                            'rows' => $rows
                        )
                    )
                ),
            );

            $respC = $this->_messageInteractive($cliente->bot_id,$cliente->telefono,$body);

            if ($respC['status'] == 200) {

                $this->storeMsgChat($cliente->bot_id,$cliente->id,null,$message,0); //bot

                return 1;

            }else{

              return 0;  

            }

        }else{
            return 0;
        }
        
    }

    public function pedidoPdf($pedido_id,$vista)
    {

        set_time_limit(500);

        $pedido = Pedido::
            with('detalles', 'detalles.producto', 'detalles.color', 'detalles.tipo')
            ->find($pedido_id);

        $cliente = BotCliente::find($pedido->cliente_id);

        $data = [
            'header' => $cliente->header,
            'footer' => $cliente->footer,
            'pedido' => $pedido
        ];

        //$pdf = Pdf::loadView('pedidos.pedidoA', $data);
        //$pdf = Pdf::loadView('pedidos.'.$vista, $data);
        // Crea una instancia de Pdf y establece el tamaño de papel en hoja carta
        $pdf = Pdf::loadView('pedidos.'.$vista, $data)->setPaper('letter');
        $pdfContent = $pdf->output();

        // Genera un nombre de archivo único
        $nombreArchivo = 'pdf_' . uniqid() . '.pdf';

        // Guarda el PDF en la carpeta "public" del directorio raíz
        Storage::disk('public_root')->put('pdfs/pedidos/'.$nombreArchivo, $pdf->output());

        // Obtiene la URL del archivo guardado
        $url = asset('pdfs/pedidos/' . $nombreArchivo);

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


    public function flowCotizacion($cliente)
    {
        //Indicar que el cliente inicio el primer estado de un flujo
        DB::table('bot_clientes')
            ->where('id', $cliente->id)
            ->update([
                'flow_id' => 3,
            ]);

        // Crear una instancia del otro controlador
        $FlowCotizacionController = new FlowCotizacionController();

        // Llamar a una función del otro controlador
        $resultado = $FlowCotizacionController->flowCotizacion($cliente);
        return 1;

    }

    public function flowConfigCliente($cliente)
    {

        //quitar el flujo
        DB::table('bot_clientes')
            ->where('id', $cliente->id)
            ->update([
                'flow_id' => null,
            ]);

        $user_token=User::find(56);
        $token = JWTAuth::fromUser($user_token);

        $claveAdicional = config('app.lada_d');

        $cadenaEncriptada = Crypt::encrypt($cliente->id, $claveAdicional);

        $link = 'https://social.internow.com.mx/#/config-cliente-bot/'.$cadenaEncriptada.'/'.$token;

        $short_link = $this->shortenURL($link);

        $message = 'Ingresa en el siguiente enlace para configurar tu marca:

{{short_link}}';

        $message = str_replace("{{short_link}}", $short_link, $message);

        $this->messageTextToCliente($cliente,$message);

        return 1;

    }

   


}
